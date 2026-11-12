<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The persona's workspace (calendar and files, looked up during the call) and what they did with it.
 */
final class Version20261114060614 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scenario workspace and call actions, for the persona agent and its tools.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE practice_call ADD COLUMN actions CLOB DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE scenario ADD COLUMN workspace CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // SQLite cannot drop a column in place: the tables are rebuilt without them.
        $this->addSql('CREATE TEMPORARY TABLE __temp__practice_call AS SELECT id, started_at, ended_at, status, ended_by, lines, revealed, debrief, score, replay_of_id, member_id, scenario_id FROM practice_call');
        $this->addSql('DROP TABLE practice_call');
        $this->addSql('CREATE TABLE practice_call (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, started_at DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL, status VARCHAR(20) NOT NULL, ended_by VARCHAR(20) DEFAULT NULL, lines CLOB NOT NULL, revealed CLOB NOT NULL, debrief CLOB DEFAULT NULL, score INTEGER DEFAULT NULL, replay_of_id INTEGER DEFAULT NULL, member_id INTEGER NOT NULL, scenario_id INTEGER NOT NULL, CONSTRAINT FK_17E9A06DA7FCFC8 FOREIGN KEY (replay_of_id) REFERENCES practice_call (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_17E9A067597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_17E9A06E04E49DF FOREIGN KEY (scenario_id) REFERENCES scenario (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO practice_call (id, started_at, ended_at, status, ended_by, lines, revealed, debrief, score, replay_of_id, member_id, scenario_id) SELECT id, started_at, ended_at, status, ended_by, lines, revealed, debrief, score, replay_of_id, member_id, scenario_id FROM __temp__practice_call');
        $this->addSql('DROP TABLE __temp__practice_call');
        $this->addSql('CREATE INDEX practice_call_member_started ON practice_call (member_id, started_at)');
        $this->addSql('CREATE INDEX practice_call_member_status ON practice_call (member_id, status)');
        $this->addSql('CREATE INDEX IDX_17E9A06DA7FCFC8 ON practice_call (replay_of_id)');
        $this->addSql('CREATE INDEX IDX_17E9A067597D3FE ON practice_call (member_id)');
        $this->addSql('CREATE INDEX IDX_17E9A06E04E49DF ON practice_call (scenario_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__scenario AS SELECT id, created_at, active, situation, focus, material, objectives, persona, brief, title, type, difficulty, minutes, context, goal, opening, author_id FROM scenario');
        $this->addSql('DROP TABLE scenario');
        $this->addSql('CREATE TABLE scenario (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, active BOOLEAN NOT NULL, situation CLOB DEFAULT NULL, focus VARCHAR(40) DEFAULT NULL, material CLOB DEFAULT NULL, objectives CLOB NOT NULL, persona CLOB NOT NULL, brief CLOB NOT NULL, title VARCHAR(160) NOT NULL, type VARCHAR(30) NOT NULL, difficulty INTEGER NOT NULL, minutes INTEGER NOT NULL, context CLOB NOT NULL, goal CLOB NOT NULL, opening CLOB NOT NULL, author_id INTEGER DEFAULT NULL, CONSTRAINT FK_3E45C8D8F675F31B FOREIGN KEY (author_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO scenario (id, created_at, active, situation, focus, material, objectives, persona, brief, title, type, difficulty, minutes, context, goal, opening, author_id) SELECT id, created_at, active, situation, focus, material, objectives, persona, brief, title, type, difficulty, minutes, context, goal, opening, author_id FROM __temp__scenario');
        $this->addSql('DROP TABLE __temp__scenario');
        $this->addSql('CREATE INDEX IDX_3E45C8D8F675F31B ON scenario (author_id)');
    }
}
