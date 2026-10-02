<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stores the destination company site on worksheets.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worksheet ADD company_site_id INT DEFAULT NULL AFTER machine_rental_id');
        $this->addSql('CREATE INDEX IDX_WORKSHEET_COMPANY_SITE ON worksheet (company_site_id)');
        $this->addSql('ALTER TABLE worksheet ADD CONSTRAINT FK_WORKSHEET_COMPANY_SITE FOREIGN KEY (company_site_id) REFERENCES company_sites (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worksheet DROP FOREIGN KEY FK_WORKSHEET_COMPANY_SITE');
        $this->addSql('DROP INDEX IDX_WORKSHEET_COMPANY_SITE ON worksheet');
        $this->addSql('ALTER TABLE worksheet DROP company_site_id');
    }
}
