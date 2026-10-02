<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds an optional project relation to machine rentals.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine_rental ADD project_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_MACHINE_RENTAL_PROJECT ON machine_rental (project_id)');
        $this->addSql('ALTER TABLE machine_rental ADD CONSTRAINT FK_MACHINE_RENTAL_PROJECT FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine_rental DROP FOREIGN KEY FK_MACHINE_RENTAL_PROJECT');
        $this->addSql('DROP INDEX IDX_MACHINE_RENTAL_PROJECT ON machine_rental');
        $this->addSql('ALTER TABLE machine_rental DROP project_id');
    }
}
