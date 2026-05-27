<?php

declare(strict_types=1);

namespace App\Application\Connections;

use App\Infrastructure\Database\DatabaseConnectionManager;
use App\Models\DatabaseConnection;

class DeleteConnectionAction
{
    public function __construct(private readonly DatabaseConnectionManager $manager) {}

    public function execute(DatabaseConnection $connection): void
    {
        $poolId = $connection->poolId();
        $connection->delete();
        $this->manager->close($poolId);
    }
}
