<?php

namespace App\Services;

use Exception;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function credit(
        User $user,
        float $amount,
        string $type,
        ?string $description = null,
        ?Model $reference = null
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $amount, $type, $description, $reference) {
            // Re-fetch and lock the row inside the transaction. lockForUpdate() is a query
            // builder method, not a Model method — calling it on an already-loaded $user
            // instance silently builds and discards a fresh, unexecuted query instead of
            // actually locking anything, which allows concurrent credit/debit calls on the
            // same user to race. Locking the freshly-fetched row closes that gap.
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            $newBalance = $locked->wallet_balance + $amount;
            $locked->forceFill(['wallet_balance' => $newBalance])->save();
            $user->wallet_balance = $newBalance;

            return WalletTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => $description,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->id,
                'status' => WalletTransaction::STATUS_COMPLETED,
            ]);
        });
    }

    public function debit(
        User $user,
        float $amount,
        string $type,
        ?string $description = null,
        ?Model $reference = null
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $amount, $type, $description, $reference) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            if ($locked->wallet_balance < $amount) {
                throw new Exception('Insufficient balance');
            }

            $newBalance = $locked->wallet_balance - $amount;
            $locked->forceFill(['wallet_balance' => $newBalance])->save();
            $user->wallet_balance = $newBalance;

            return WalletTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => -$amount,
                'balance_after' => $newBalance,
                'description' => $description,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->id,
                'status' => WalletTransaction::STATUS_COMPLETED,
            ]);
        });
    }

    public function transfer(
        User $from,
        User $to,
        float $amount,
        string $type,
        ?string $description = null,
        float $platformCut = 0
    ): array {
        return DB::transaction(function () use ($from, $to, $amount, $type, $description, $platformCut) {
            $debit = $this->debit($from, $amount, $type . '_sent', $description);
            
            $receiverAmount = $amount - $platformCut;
            $credit = $this->credit($to, $receiverAmount, $type . '_received', $description);

            return [
                'debit' => $debit,
                'credit' => $credit,
                'platformCut' => $platformCut,
            ];
        });
    }

    public function getBalance(User $user): float
    {
        return $user->wallet_balance;
    }

    public function canAfford(User $user, float $amount): bool
    {
        return $user->wallet_balance >= $amount;
    }
}
