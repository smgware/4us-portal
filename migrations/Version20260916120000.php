<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Changes the email_logs table collation to utf8mb4_general_ci.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
    }
}
