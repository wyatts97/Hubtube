<?php

namespace App\Http\Controllers;

use App\Filament\Resources\WithdrawalRequestResource;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\WalletService;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    public function index(Request $request): Response
    {
        $transactions = $request->user()
            ->walletTransactions()
            ->completed()
            ->latest()
            ->paginate(20);

        return Inertia::render('Wallet/Index', [
            'balance' => $request->user()->wallet_balance,
            'transactions' => $transactions,
            'minWithdrawal' => (int) Setting::get('min_withdrawal', 50),
            'depositEnabled' => false,
        ]);
    }

    public function deposit(Request $request): Response
    {
        return Inertia::render('Wallet/Deposit', [
            'balance' => $request->user()->wallet_balance,
            'depositEnabled' => false,
        ]);
    }

    public function processDeposit(Request $request): RedirectResponse
    {
        return redirect()->route('wallet.index')
            ->with('error', 'Deposits are temporarily unavailable. Please try again later.');
    }

    public function withdraw(Request $request): Response
    {
        Gate::authorize('withdraw');

        $pendingWithdrawals = $request->user()
            ->withdrawalRequests()
            ->where('status', 'pending')
            ->sum('amount');

        return Inertia::render('Wallet/Withdraw', [
            'balance' => $request->user()->wallet_balance,
            'pendingWithdrawals' => $pendingWithdrawals,
            'minWithdrawal' => (int) Setting::get('min_withdrawal', 50),
        ]);
    }

    public function processWithdraw(Request $request): RedirectResponse
    {
        Gate::authorize('withdraw');

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:' . Setting::get('min_withdrawal', 50),
                'max:' . $request->user()->wallet_balance,
            ],
            'payment_method' => 'required|in:paypal,bank,crypto',
            'payment_details' => 'required|array',
        ]);

        // The `max:` rule above reads the balance outside any lock, so it is only a
        // UX check. Creating the request and debiting the balance must happen in one
        // transaction: WalletService::debit() takes a row lock and throws on an
        // overdraft, and without a surrounding transaction the WithdrawalRequest row
        // stayed committed — leaving an orphaned request the admin queue would honour.
        try {
            $withdrawal = DB::transaction(function () use ($request, $validated) {
                $withdrawal = WithdrawalRequest::create([
                    'user_id' => $request->user()->id,
                    'amount' => $validated['amount'],
                    'payment_method' => $validated['payment_method'],
                    'payment_details' => $validated['payment_details'],
                ]);

                // Deduct balance immediately to prevent double-withdrawal.
                $this->walletService->debit(
                    $request->user(),
                    $validated['amount'],
                    'withdrawal_hold',
                    "Withdrawal request #{$withdrawal->id}",
                    $withdrawal
                );

                return $withdrawal;
            });
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'amount' => 'Your balance changed while this request was being processed. '
                    . 'Please check your balance and try again.',
            ]);
        }

        $admins = User::admins()->get();
        if ($admins->isNotEmpty()) {
            Notification::make()
                ->title('New withdrawal request')
                ->body("New withdrawal request — \${$validated['amount']} from {$request->user()->username}")
                ->icon('phosphor-currency-dollar')
                ->actions([
                    NotificationAction::make('view')
                        ->label('Review')
                        ->url(WithdrawalRequestResource::getUrl('index'))
                        ->button(),
                ])
                ->warning()
                ->sendToDatabase($admins);
        }

        return redirect()->route('wallet.index')
            ->with('success', 'Withdrawal request submitted. Processing takes 3-5 business days.');
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = $request->user()
            ->walletTransactions()
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->completed()
            ->latest()
            ->paginate(20);

        return response()->json($transactions);
    }
}
