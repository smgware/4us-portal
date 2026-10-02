<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates worksheet description templates managed from settings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE worksheet_description_template (
    id INT AUTO_INCREMENT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT NOT NULL,
    uid_add INT DEFAULT NULL,
    uid_last INT DEFAULT NULL,
    datetime_add DATETIME DEFAULT NULL,
    datetime_last DATETIME DEFAULT NULL,
    status VARCHAR(255) NOT NULL,
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE worksheet_description_template');
    }
}
