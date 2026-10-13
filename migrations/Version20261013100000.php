<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a code to worksheet description templates.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE worksheet_description_template ADD code VARCHAR(255) NOT NULL DEFAULT '' AFTER title");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worksheet_description_template DROP code');
    }
}
