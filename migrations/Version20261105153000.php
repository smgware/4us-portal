<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates the quote_maker_quote table.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('quote_maker_quote')) {
            return;
        }

        $this->addSql(<<<'SQL'
CREATE TABLE quote_maker_quote (
    id INT AUTO_INCREMENT NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    code VARCHAR(255) DEFAULT NULL,
    data JSON DEFAULT NULL,
    datetime_add DATETIME DEFAULT NULL,
    datetime_last DATETIME DEFAULT NULL,
    uid_add INT DEFAULT NULL,
    uid_last INT DEFAULT NULL,
    INDEX IDX_QUOTE_MAKER_QUOTE_CODE (code),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('quote_maker_quote')) {
            $this->addSql('DROP TABLE quote_maker_quote');
        }
    }
}
