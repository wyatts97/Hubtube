<?php

use App\Models\User;
use App\Services\WalletService;

it('credits a user wallet and records a transaction', function () {
    $user = User::factory()->create(['wallet_balance' => 10]);
    $service = app(WalletService::class);

    $transaction = $service->credit($user, 5, 'gift_received', 'test credit');

    expect($user->fresh()->wallet_balance)->toEqual(15.0);
    expect($transaction->balance_after)->toEqual(15.0);
});

it('debits a user wallet and records a transaction', function () {
    $user = User::factory()->create(['wallet_balance' => 10]);
    $service = app(WalletService::class);

    $transaction = $service->debit($user, 4, 'gift_sent', 'test debit');

    expect($user->fresh()->wallet_balance)->toEqual(6.0);
    expect($transaction->balance_after)->toEqual(6.0);
});

it('throws on insufficient balance and leaves the balance untouched', function () {
    $user = User::factory()->create(['wallet_balance' => 3]);
    $service = app(WalletService::class);

    expect(fn () => $service->debit($user, 10, 'gift_sent'))
        ->toThrow(Exception::class, 'Insufficient balance');

    expect($user->fresh()->wallet_balance)->toEqual(3.0);
});

it('reflects the locked balance back onto the passed-in user instance', function () {
    $user = User::factory()->create(['wallet_balance' => 10]);
    $service = app(WalletService::class);

    $service->credit($user, 7, 'gift_received');

    // The in-memory $user instance (not just the DB row) should be updated,
    // since callers often read $user->wallet_balance right after this call.
    expect($user->wallet_balance)->toEqual(17.0);
});

it('applies sequential credits and debits correctly without losing updates', function () {
    $user = User::factory()->create(['wallet_balance' => 0]);
    $service = app(WalletService::class);

    foreach (range(1, 20) as $i) {
        $service->credit($user->fresh(), 1, 'gift_received');
    }

    expect($user->fresh()->wallet_balance)->toEqual(20.0);
});
