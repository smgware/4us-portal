<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replaces notification events with add and modify events for every active worksheet type.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM user_notification_event');
        $this->addSql('DELETE FROM notification_events');
        $this->addSql("INSERT INTO notification_events (name, code, status, uid_add, uid_last, datetime_add, datetime_last) SELECT CONCAT(title, ' - hozzáadás'), CONCAT(code, '_ADD'), '1', NULL, NULL, NOW(), NOW() FROM worksheettype WHERE status = '1'");
        $this->addSql("INSERT INTO notification_events (name, code, status, uid_add, uid_last, datetime_add, datetime_last) SELECT CONCAT(title, ' - módosítás'), CONCAT(code, '_MODIFY'), '1', NULL, NULL, NOW(), NOW() FROM worksheettype WHERE status = '1'");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The preceding notification events and their user assignments were deleted.');
    }
}
