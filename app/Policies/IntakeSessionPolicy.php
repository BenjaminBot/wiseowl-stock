<?php

namespace App\Policies;

use App\Models\IntakeSession;
use App\Models\ReceiptLine;
use App\Models\User;

class IntakeSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_staff;
    }

    public function view(User $user, IntakeSession $session): bool
    {
        return $user->is_staff;
    }

    public function create(User $user): bool
    {
        return $user->is_staff;
    }

    public function update(User $user, IntakeSession $session): bool
    {
        return $user->is_staff && $session->status === 'open' && ! ReceiptLine::where('intake_session_id', $session->id)->exists();
    }

    public function delete(User $user, IntakeSession $session): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
