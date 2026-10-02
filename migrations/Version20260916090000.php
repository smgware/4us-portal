<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the base64 encoded signature image to users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD signature LONGTEXT DEFAULT NULL AFTER image');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP signature');
    }
}
