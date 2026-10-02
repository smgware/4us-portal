<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260909165245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE machine DROP FOREIGN KEY `FK_1505DF84166D1F9C`');
        $this->addSql('ALTER TABLE machine DROP FOREIGN KEY `FK_DAB8E618B3E3AB84`');
        $this->addSql('DROP TABLE machine');
        $this->addSql('ALTER TABLE project ADD status VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE machine (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, code VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, data LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_bin`, uid_add INT DEFAULT NULL, uid_last INT DEFAULT NULL, datetime_add DATETIME DEFAULT NULL, datetime_last DATETIME DEFAULT NULL, status VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, machine_category_id INT NOT NULL, project_id INT DEFAULT NULL, INDEX IDX_1505DF84166D1F9C (project_id), INDEX IDX_1505DF84B3E3AB84 (machine_category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT `FK_1505DF84166D1F9C` FOREIGN KEY (project_id) REFERENCES project (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT `FK_DAB8E618B3E3AB84` FOREIGN KEY (machine_category_id) REFERENCES machinecategory (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE project DROP status');
        $this->addSql('ALTER TABLE `user` CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
    }
}
