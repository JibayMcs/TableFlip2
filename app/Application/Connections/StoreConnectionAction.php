<?php

declare(strict_types=1);

namespace App\Application\Connections;

use App\Models\DatabaseConnection;
use App\Models\User;

class StoreConnectionAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): DatabaseConnection
    {
        $data['user_id'] = $user->id;
        $data['color'] = ConnectionColors::isValid($data['color'] ?? '')
            ? $data['color']
            : ConnectionColors::DEFAULT;

        return DatabaseConnection::create($data);
    }
}
