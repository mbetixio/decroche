<?php

declare(strict_types=1);

namespace App\Company;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Empties the instance: the company, its members, calls, scenarios, missions and invitations all go, the schema and
 * its migrations stay. The next visit opens the onboarding again, as on first boot.
 */
final readonly class InstanceReset
{
    /** Kept: the migrations already run, and SQLite's own bookkeeping. */
    private const array KEPT = ['doctrine_migration_versions', 'sqlite_sequence'];

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function reset(): void
    {
        $connection = $this->em->getConnection();
        $platform = $connection->getDatabasePlatform();
        $tables = array_filter(
            $connection->createSchemaManager()->introspectTableNames(),
            static fn (OptionallyQualifiedName $name): bool => !\in_array(strtolower($name->getUnqualifiedName()->toString()), self::KEPT, true),
        );
        // Every row goes, whatever the order: the foreign keys are checked again afterwards.
        $connection->executeStatement('PRAGMA foreign_keys = OFF');
        try {
            $connection->transactional(static function (Connection $connection) use ($tables, $platform): void {
                foreach ($tables as $table) {
                    $connection->executeStatement('DELETE FROM '.$table->toSQL($platform));
                }
            });
        } finally {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
        }
        $this->em->clear();
    }
}
