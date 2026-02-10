<?php

namespace App\Services;

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
            $user->lockForUpdate();
            
            $newBalance = $user->wallet_balance + $amount;
            $user->forceFill(['wallet_balance' => $newBalance])->save();

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
            $user->lockForUpdate();

            if ($user->wallet_balance < $amount) {
                throw new \Exception('Insufficient balance');
            }

            $newBalance = $user->wallet_balance - $amount;
            $user->forceFill(['wallet_balance' => $newBalance])->save();

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
