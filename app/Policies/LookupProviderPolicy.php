<?php

namespace App\Policies;

use App\Models\LookupProvider;
use App\Models\User;

class LookupProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_staff && $user->is_manager;
    }

    public function view(User $user, LookupProvider $provider): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, LookupProvider $provider): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, LookupProvider $provider): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
