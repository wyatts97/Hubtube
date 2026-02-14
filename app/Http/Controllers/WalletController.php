<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

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

        $withdrawal = WithdrawalRequest::create([
            'user_id' => $request->user()->id,
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_details' => $validated['payment_details'],
        ]);

        // Deduct balance immediately to prevent double-withdrawal
        $this->walletService->debit(
            $request->user(),
            $validated['amount'],
            'withdrawal_hold',
            "Withdrawal request #{$withdrawal->id}",
            $withdrawal
        );

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
