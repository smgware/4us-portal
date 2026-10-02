<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the current machine location and machine attachments.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('machine') && !$schema->getTable('machine')->hasColumn('current_location')) {
            $this->addSql('ALTER TABLE machine ADD current_location VARCHAR(255) DEFAULT NULL AFTER data');
        }

        if (!$schema->hasTable('machine_attachment')) {
            $this->addSql(<<<'SQL'
CREATE TABLE machine_attachment (
    id INT AUTO_INCREMENT NOT NULL,
    machine_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    data JSON DEFAULT NULL,
    uid_add INT DEFAULT NULL,
    uid_last INT DEFAULT NULL,
    datetime_add DATETIME DEFAULT NULL,
    datetime_last DATETIME DEFAULT NULL,
    status VARCHAR(1) DEFAULT '1' NOT NULL,
    INDEX IDX_MACHINE_ATTACHMENT_MACHINE (machine_id),
    INDEX IDX_MACHINE_ATTACHMENT_DATETIME_ADD (datetime_add),
    INDEX IDX_MACHINE_ATTACHMENT_STATUS (status),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB
SQL);
            $this->addSql('ALTER TABLE machine_attachment ADD CONSTRAINT FK_MACHINE_ATTACHMENT_MACHINE FOREIGN KEY (machine_id) REFERENCES machine (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('machine_attachment')) {
            $this->addSql('DROP TABLE machine_attachment');
        }

        if ($schema->hasTable('machine') && $schema->getTable('machine')->hasColumn('current_location')) {
            $this->addSql('ALTER TABLE machine DROP current_location');
        }
    }
}
