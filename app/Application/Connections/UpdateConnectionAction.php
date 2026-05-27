<?php

declare(strict_types=1);

namespace App\Application\Connections;

use App\Infrastructure\Database\DatabaseConnectionManager;
use App\Models\DatabaseConnection;

class UpdateConnectionAction
{
    public function __construct(private readonly DatabaseConnectionManager $manager) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(DatabaseConnection $connection, array $data): DatabaseConnection
    {
        // Keep the existing password if the new one is empty (edit flow where
        // we never preload the existing password into the form).
        if (! array_key_exists('password', $data) || $data['password'] === null || $data['password'] === '') {
            unset($data['password']);
        }

        if (isset($data['color']) && ! ConnectionColors::isValid($data['color'])) {
            $data['color'] = ConnectionColors::DEFAULT;
        }

        $connection->update($data);

        // Drop any cached pool entry so subsequent uses pick up the new config.
        $this->manager->close($connection->poolId());

        return $connection->fresh() ?? $connection;
    }
}
