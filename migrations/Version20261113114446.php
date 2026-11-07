<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113114446 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The initial schema: the company, its members and invitations, scenarios, calls and missions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE company (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(120) NOT NULL, language VARCHAR(5) NOT NULL, website VARCHAR(255) DEFAULT NULL, about CLOB NOT NULL, offer CLOB NOT NULL, sectors CLOB NOT NULL, situations CLOB NOT NULL, criteria CLOB DEFAULT NULL, analyzed_at DATETIME DEFAULT NULL, step VARCHAR(20) NOT NULL, onboarded_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE TABLE invitation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, email VARCHAR(180) NOT NULL, token VARCHAR(64) NOT NULL, accepted_at DATETIME DEFAULT NULL, role VARCHAR(20) NOT NULL, invited_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_F11D61A2A7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F11D61A25F37A13B ON invitation (token)');
        $this->addSql('CREATE INDEX IDX_F11D61A2A7B4A7E3 ON invitation (invited_by_id)');
        $this->addSql('CREATE TABLE member (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, profile CLOB NOT NULL, mcp_key_hash VARCHAR(64) DEFAULT NULL, name VARCHAR(120) NOT NULL, role VARCHAR(20) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_70E4FA78E7927C74 ON member (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_70E4FA786D213697 ON member (mcp_key_hash)');
        $this->addSql('CREATE TABLE mission (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, calls INTEGER NOT NULL, target INTEGER NOT NULL, situation VARCHAR(40) DEFAULT NULL, due_at DATE DEFAULT NULL, note VARCHAR(200) NOT NULL, created_at DATETIME NOT NULL, assignee_id INTEGER DEFAULT NULL, created_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_9067F23C59EC7D60 FOREIGN KEY (assignee_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9067F23CB03A8386 FOREIGN KEY (created_by_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_9067F23C59EC7D60 ON mission (assignee_id)');
        $this->addSql('CREATE INDEX IDX_9067F23CB03A8386 ON mission (created_by_id)');
        $this->addSql('CREATE TABLE practice_call (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, started_at DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL, status VARCHAR(20) NOT NULL, ended_by VARCHAR(20) DEFAULT NULL, lines CLOB NOT NULL, revealed CLOB NOT NULL, debrief CLOB DEFAULT NULL, score INTEGER DEFAULT NULL, replay_of_id INTEGER DEFAULT NULL, member_id INTEGER NOT NULL, scenario_id INTEGER NOT NULL, CONSTRAINT FK_17E9A06DA7FCFC8 FOREIGN KEY (replay_of_id) REFERENCES practice_call (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_17E9A067597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_17E9A06E04E49DF FOREIGN KEY (scenario_id) REFERENCES scenario (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_17E9A067B00651C ON practice_call (status)');
        $this->addSql('CREATE INDEX IDX_17E9A06DA7FCFC8 ON practice_call (replay_of_id)');
        $this->addSql('CREATE INDEX IDX_17E9A067597D3FE ON practice_call (member_id)');
        $this->addSql('CREATE INDEX IDX_17E9A06E04E49DF ON practice_call (scenario_id)');
        $this->addSql('CREATE TABLE scenario (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, active BOOLEAN NOT NULL, situation CLOB DEFAULT NULL, material CLOB DEFAULT NULL, objectives CLOB NOT NULL, persona CLOB NOT NULL, brief CLOB NOT NULL, title VARCHAR(160) NOT NULL, type VARCHAR(30) NOT NULL, difficulty INTEGER NOT NULL, minutes INTEGER NOT NULL, context CLOB NOT NULL, goal CLOB NOT NULL, opening CLOB NOT NULL, author_id INTEGER DEFAULT NULL, CONSTRAINT FK_3E45C8D8F675F31B FOREIGN KEY (author_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3E45C8D8F675F31B ON scenario (author_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE invitation');
        $this->addSql('DROP TABLE member');
        $this->addSql('DROP TABLE mission');
        $this->addSql('DROP TABLE practice_call');
        $this->addSql('DROP TABLE scenario');
    }
}
