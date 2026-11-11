<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111141200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow albums without a release date';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE album CHANGE release_date release_date DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE album CHANGE release_date release_date DATETIME NOT NULL');
    }
}
