<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Removes the project relation from worksheets.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worksheet DROP FOREIGN KEY FK_WORKSHEET_PROJECT');
        $this->addSql('DROP INDEX IDX_WORKSHEET_PROJECT ON worksheet');
        $this->addSql('ALTER TABLE worksheet DROP project_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worksheet ADD project_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_WORKSHEET_PROJECT ON worksheet (project_id)');
        $this->addSql('ALTER TABLE worksheet ADD CONSTRAINT FK_WORKSHEET_PROJECT FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
    }
}
