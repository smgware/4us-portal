<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909211500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates worksheets and worksheet attachments, including file metadata and audit fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE worksheet (
    id INT AUTO_INCREMENT NOT NULL,
    worksheet_type_id INT NOT NULL,
    worksheet_status_type_id INT NOT NULL,
    partner_id INT DEFAULT NULL,
    machine_id INT DEFAULT NULL,
    machine_rental_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    code VARCHAR(255) NOT NULL,
    data JSON DEFAULT NULL,
    status VARCHAR(32) DEFAULT '1' NOT NULL,
    uid_add INT DEFAULT NULL,
    uid_last INT DEFAULT NULL,
    datetime_add DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    datetime_last DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    datetime_open DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    datetime_closed DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_WORKSHEET_TYPE (worksheet_type_id),
    INDEX IDX_WORKSHEET_STATUS_TYPE (worksheet_status_type_id),
    INDEX IDX_WORKSHEET_PARTNER (partner_id),
    INDEX IDX_WORKSHEET_MACHINE (machine_id),
    INDEX IDX_WORKSHEET_MACHINE_RENTAL (machine_rental_id),
    UNIQUE INDEX UNIQ_WORKSHEET_CODE (code),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB
SQL);
        $this->addSql('ALTER TABLE worksheet ADD CONSTRAINT FK_WORKSHEET_TYPE FOREIGN KEY (worksheet_type_id) REFERENCES worksheettype (id)');
        $this->addSql('ALTER TABLE worksheet ADD CONSTRAINT FK_WORKSHEET_STATUS_TYPE FOREIGN KEY (worksheet_status_type_id) REFERENCES worksheet_status_types (id)');
        $this->addSql('ALTER TABLE worksheet ADD CONSTRAINT FK_WORKSHEET_PARTNER FOREIGN KEY (partner_id) REFERENCES partner (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
CREATE TABLE worksheet_attachment (
    id INT AUTO_INCREMENT NOT NULL,
    worksheet_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    data JSON DEFAULT NULL,
    uid_add INT DEFAULT NULL,
    uid_last INT DEFAULT NULL,
    datetime_add DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    datetime_last DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    datetime_open DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    datetime_closed DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    status VARCHAR(32) DEFAULT '1' NOT NULL,
    INDEX IDX_WORKSHEET_ATTACHMENT_WORKSHEET (worksheet_id),
    INDEX IDX_WORKSHEET_ATTACHMENT_STATUS (status),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB
SQL);
        $this->addSql('ALTER TABLE worksheet_attachment ADD CONSTRAINT FK_WORKSHEET_ATTACHMENT_WORKSHEET FOREIGN KEY (worksheet_id) REFERENCES worksheet (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worksheet_attachment DROP FOREIGN KEY FK_WORKSHEET_ATTACHMENT_WORKSHEET');
        $this->addSql('DROP TABLE worksheet_attachment');
        $this->addSql('ALTER TABLE worksheet DROP FOREIGN KEY FK_WORKSHEET_TYPE');
        $this->addSql('ALTER TABLE worksheet DROP FOREIGN KEY FK_WORKSHEET_STATUS_TYPE');
        $this->addSql('ALTER TABLE worksheet DROP FOREIGN KEY FK_WORKSHEET_PARTNER');
        $this->addSql('DROP TABLE worksheet');
    }
}
