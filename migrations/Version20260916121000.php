<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916121000 extends AbstractMigration
{
    private const TABLES = [
        'notification_events',
        'project',
        'user_notification_event',
        'worksheet_status_types',
    ];

    public function getDescription(): string
    {
        return 'Replaces every remaining utf8mb4_0900_ai_ci collation with utf8mb4_general_ci.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');

        foreach (self::TABLES as $table) {
            $this->addSql(sprintf(
                'ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
                $table,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

        foreach (self::TABLES as $table) {
            $this->addSql(sprintf(
                'ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
                $table,
            ));
        }
    }
}
