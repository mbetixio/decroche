<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Indexes the calls as they are read: a member's calls, newest first, and the one they are in.
 */
final class Version20261113133954 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Calls indexed by member and start, and by member and status (instead of status alone).';
    }

    public function up(Schema $schema): void
    {
        // Indexes only: SQLite creates and drops them in place, the table is left as it is.
        $this->addSql('DROP INDEX IDX_17E9A067B00651C');
        $this->addSql('CREATE INDEX practice_call_member_started ON practice_call (member_id, started_at)');
        $this->addSql('CREATE INDEX practice_call_member_status ON practice_call (member_id, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX practice_call_member_status');
        $this->addSql('DROP INDEX practice_call_member_started');
        $this->addSql('CREATE INDEX IDX_17E9A067B00651C ON practice_call (status)');
    }
}
