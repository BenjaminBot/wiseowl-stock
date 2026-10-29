<?php

namespace App\Policies;

use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ConfigurationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_staff;
    }

    public function view(User $user, Model $model): bool
    {
        return $user->is_staff;
    }

    public function create(User $user): bool
    {
        return $user->is_staff && $user->is_manager;
    }

    public function update(User $user, Model $model): bool
    {
        return $this->create($user) && ! ($model instanceof SalesChannel && $model->is_system);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
