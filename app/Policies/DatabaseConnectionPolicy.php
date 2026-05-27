<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DatabaseConnection;
use App\Models\User;

class DatabaseConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DatabaseConnection $connection): bool
    {
        return $connection->user_id === $user->id || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, DatabaseConnection $connection): bool
    {
        return $connection->user_id === $user->id;
    }

    public function delete(User $user, DatabaseConnection $connection): bool
    {
        return $connection->user_id === $user->id;
    }

    /**
     * Switching to a connection requires ownership — admins can VIEW
     * other users' connections (read-only) but not activate them, since
     * "active connection" grants live data access using stored credentials.
     */
    public function activate(User $user, DatabaseConnection $connection): bool
    {
        return $connection->user_id === $user->id;
    }
}
