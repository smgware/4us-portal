<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restores the machine table when an earlier migration removed it.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('machine')) {
            return;
        }

        $this->addSql(<<<'SQL'
CREATE TABLE machine (
    id INT AUTO_INCREMENT NOT NULL,
    project_id INT DEFAULT NULL,
    machine_category_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    code VARCHAR(255) NOT NULL,
    data JSON DEFAULT NULL,
    uid_add INT DEFAULT NULL,
    uid_last INT DEFAULT NULL,
    datetime_add DATETIME DEFAULT NULL,
    datetime_last DATETIME DEFAULT NULL,
    status VARCHAR(255) DEFAULT '1' NOT NULL,
    INDEX IDX_MACHINE_PROJECT (project_id),
    INDEX IDX_MACHINE_CATEGORY (machine_category_id),
    INDEX IDX_MACHINE_STATUS (status),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB
SQL);
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_MACHINE_PROJECT FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_MACHINE_CATEGORY FOREIGN KEY (machine_category_id) REFERENCES machinecategory (id)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'This recovery migration does not remove the machine table because it may contain pre-existing data.',
        );
    }
}
