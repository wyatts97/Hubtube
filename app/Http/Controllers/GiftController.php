<?php

namespace App\Http\Controllers;

use App\Events\GiftSent;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Models\LiveStream;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    public function index(): JsonResponse
    {
        $gifts = Gift::active()->ordered()->get();

        return response()->json($gifts);
    }

    public function send(Request $request, LiveStream $liveStream): JsonResponse
    {
        $validated = $request->validate([
            'gift_id' => 'required|exists:gifts,id',
        ]);

        if (!$liveStream->isLive()) {
            return response()->json(['error' => 'Stream is not live'], 422);
        }

        if (!$liveStream->gifts_enabled) {
            return response()->json(['error' => 'Gifts are disabled for this stream'], 422);
        }

        $gift = Gift::findOrFail($validated['gift_id']);
        $user = $request->user();

        if ($user->wallet_balance < $gift->price) {
            return response()->json(['error' => 'Insufficient balance'], 422);
        }

        $platformCut = $gift->price * (config('hubtube.monetization.gift_platform_cut') / 100);
        $receiverAmount = $gift->price - $platformCut;

        $transaction = GiftTransaction::create([
            'gift_id' => $gift->id,
            'sender_id' => $user->id,
            'receiver_id' => $liveStream->user_id,
            'live_stream_id' => $liveStream->id,
            'amount' => $gift->price,
            'platform_cut' => $platformCut,
            'receiver_amount' => $receiverAmount,
        ]);

        $this->walletService->debit(
            $user,
            $gift->price,
            'gift_sent',
            "Sent {$gift->name} gift",
            $transaction
        );

        $this->walletService->credit(
            $liveStream->user,
            $receiverAmount,
            'gift_received',
            "Received {$gift->name} gift from {$user->username}",
            $transaction
        );

        $liveStream->addGiftAmount($gift->price);

        event(new GiftSent($transaction));

        return response()->json([
            'success' => true,
            'gift' => $gift,
            'newBalance' => $user->fresh()->wallet_balance,
        ]);
    }
}
