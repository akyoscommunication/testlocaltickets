<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table app_configuration pour paramètres UI';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_configuration (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, settings CLOB NOT NULL, updated_at DATETIME NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_configuration');
    }
}
