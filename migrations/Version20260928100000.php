<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a code prefix to worksheet types and populates the existing worksheet type prefixes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE worksheettype ADD prefix VARCHAR(10) NOT NULL DEFAULT ''");
        $this->addSql("UPDATE worksheettype SET prefix = CASE code WHEN 'MACHINE_DISPOSAL' THEN 'GS' WHEN 'MACHINE_MOVE_BETWEEN_LOCATIONS' THEN 'GM' WHEN 'MACHINE_RETURN' THEN 'GV' WHEN 'MACHINE_HANDOVER' THEN 'GA' WHEN 'ERROR_REPORT' THEN 'HJ' ELSE prefix END");
        $this->addSql('ALTER TABLE worksheettype MODIFY prefix VARCHAR(10) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worksheettype DROP prefix');
    }
}
