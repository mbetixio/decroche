<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remembers the criterion a call trains, when the seller asked for it from a debrief.
 */
final class Version20261113155011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scenario focus: the scorecard criterion the call was built to train.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scenario ADD COLUMN focus VARCHAR(40) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // SQLite cannot drop a column in place: the table is rebuilt without it.
        $this->addSql('CREATE TEMPORARY TABLE __temp__scenario AS SELECT id, created_at, active, situation, material, objectives, persona, brief, title, type, difficulty, minutes, context, goal, opening, author_id FROM scenario');
        $this->addSql('DROP TABLE scenario');
        $this->addSql('CREATE TABLE scenario (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, active BOOLEAN NOT NULL, situation CLOB DEFAULT NULL, material CLOB DEFAULT NULL, objectives CLOB NOT NULL, persona CLOB NOT NULL, brief CLOB NOT NULL, title VARCHAR(160) NOT NULL, type VARCHAR(30) NOT NULL, difficulty INTEGER NOT NULL, minutes INTEGER NOT NULL, context CLOB NOT NULL, goal CLOB NOT NULL, opening CLOB NOT NULL, author_id INTEGER DEFAULT NULL, CONSTRAINT FK_3E45C8D8F675F31B FOREIGN KEY (author_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO scenario (id, created_at, active, situation, material, objectives, persona, brief, title, type, difficulty, minutes, context, goal, opening, author_id) SELECT id, created_at, active, situation, material, objectives, persona, brief, title, type, difficulty, minutes, context, goal, opening, author_id FROM __temp__scenario');
        $this->addSql('DROP TABLE __temp__scenario');
        $this->addSql('CREATE INDEX IDX_3E45C8D8F675F31B ON scenario (author_id)');
    }
}
