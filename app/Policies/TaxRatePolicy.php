<?php

namespace App\Policies;

use App\Models\TaxRate;
use App\Models\User;

class TaxRatePolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TaxRate $taxRate): bool
    {
        return (int) $taxRate->user_id === (int) $user->id;
    }

    public function delete(User $user, TaxRate $taxRate): bool
    {
        return (int) $taxRate->user_id === (int) $user->id;
    }
}
