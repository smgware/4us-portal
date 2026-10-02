<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restores machines and adds machine rentals used by the rental editor and worksheet workflow.';
    }

    public function up(Schema $schema): void
    {
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
    datetime_add DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    datetime_last DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    status VARCHAR(255) DEFAULT '1' NOT NULL,
    INDEX IDX_MACHINE_PROJECT (project_id),
    INDEX IDX_MACHINE_CATEGORY (machine_category_id),
    INDEX IDX_MACHINE_STATUS (status),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB
SQL);
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_MACHINE_PROJECT FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_MACHINE_CATEGORY FOREIGN KEY (machine_category_id) REFERENCES machinecategory (id)');

        $this->addSql(<<<'SQL'
CREATE TABLE machine_rental (
    id INT AUTO_INCREMENT NOT NULL,
    partner_id INT NOT NULL,
    machine_id INT NOT NULL,
    code VARCHAR(255) NOT NULL,
    datetime_rental_start DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    datetime_rental_end DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    status VARCHAR(255) DEFAULT '1' NOT NULL,
    uid_add INT DEFAULT NULL,
    uid_last INT DEFAULT NULL,
    datetime_add DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    datetime_last DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_MACHINE_RENTAL_PARTNER (partner_id),
    INDEX IDX_MACHINE_RENTAL_MACHINE (machine_id),
    INDEX IDX_MACHINE_RENTAL_STATUS (status),
    UNIQUE INDEX UNIQ_MACHINE_RENTAL_CODE (code),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB
SQL);
        $this->addSql('ALTER TABLE machine_rental ADD CONSTRAINT FK_MACHINE_RENTAL_PARTNER FOREIGN KEY (partner_id) REFERENCES partner (id)');
        $this->addSql('ALTER TABLE machine_rental ADD CONSTRAINT FK_MACHINE_RENTAL_MACHINE FOREIGN KEY (machine_id) REFERENCES machine (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine_rental DROP FOREIGN KEY FK_MACHINE_RENTAL_PARTNER');
        $this->addSql('ALTER TABLE machine_rental DROP FOREIGN KEY FK_MACHINE_RENTAL_MACHINE');
        $this->addSql('DROP TABLE machine_rental');
        $this->addSql('ALTER TABLE machine DROP FOREIGN KEY FK_MACHINE_PROJECT');
        $this->addSql('ALTER TABLE machine DROP FOREIGN KEY FK_MACHINE_CATEGORY');
        $this->addSql('DROP TABLE machine');
    }
}
