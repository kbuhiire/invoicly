<?php

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\User;

class CreditNotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        return (int) $creditNote->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CreditNote $creditNote): bool
    {
        return (int) $creditNote->user_id === (int) $user->id;
    }
}
