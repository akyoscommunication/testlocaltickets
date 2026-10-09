<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260526115231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial Phase 1 : ticket_processing, agent_run, file_exchange, automation_log, messenger';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE agent_run (id BLOB NOT NULL, run_id VARCHAR(32) NOT NULL, phase VARCHAR(32) NOT NULL, template_key VARCHAR(64) NOT NULL, template_version INTEGER NOT NULL, rendered_prompt_path VARCHAR(255) DEFAULT NULL, request_path VARCHAR(255) DEFAULT NULL, response_path VARCHAR(255) DEFAULT NULL, context_pack_path VARCHAR(255) DEFAULT NULL, status VARCHAR(32) NOT NULL, duration_ms INTEGER DEFAULT NULL, started_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, error_message CLOB DEFAULT NULL, ticket_processing_id BLOB NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_AC4401AA1A6A8874 FOREIGN KEY (ticket_processing_id) REFERENCES ticket_processing (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_AC4401AA1A6A8874 ON agent_run (ticket_processing_id)');
        $this->addSql('CREATE TABLE automation_log (id BLOB NOT NULL, level VARCHAR(16) NOT NULL, message CLOB NOT NULL, ticket_number VARCHAR(64) DEFAULT NULL, run_id VARCHAR(64) DEFAULT NULL, context CLOB DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE file_exchange (id BLOB NOT NULL, ticket_number VARCHAR(64) NOT NULL, logical_path VARCHAR(512) NOT NULL, content_hash VARCHAR(64) DEFAULT NULL, processed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE ticket_processing (id BLOB NOT NULL, ticket_number VARCHAR(64) NOT NULL, input_source VARCHAR(32) NOT NULL, source_ref VARCHAR(255) DEFAULT NULL, assigned_node_id VARCHAR(64) DEFAULT NULL, status VARCHAR(255) NOT NULL, loop_count INTEGER NOT NULL, payload CLOB NOT NULL, analysis_summary CLOB DEFAULT NULL, resolved_project_key VARCHAR(64) DEFAULT NULL, repo_resolution VARCHAR(16) DEFAULT NULL, last_resolution VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_ticket_number ON ticket_processing (ticket_number)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE agent_run');
        $this->addSql('DROP TABLE automation_log');
        $this->addSql('DROP TABLE file_exchange');
        $this->addSql('DROP TABLE ticket_processing');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
