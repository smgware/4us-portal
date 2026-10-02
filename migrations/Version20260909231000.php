<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909231000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalizes immutable datetime columns for the current Doctrine DBAL metadata format.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine CHANGE datetime_add datetime_add DATETIME DEFAULT NULL, CHANGE datetime_last datetime_last DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE machine_rental CHANGE datetime_rental_start datetime_rental_start DATETIME DEFAULT NULL, CHANGE datetime_rental_end datetime_rental_end DATETIME DEFAULT NULL, CHANGE datetime_add datetime_add DATETIME DEFAULT NULL, CHANGE datetime_last datetime_last DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE worksheet CHANGE datetime_add datetime_add DATETIME DEFAULT NULL, CHANGE datetime_last datetime_last DATETIME DEFAULT NULL, CHANGE datetime_open datetime_open DATETIME DEFAULT NULL, CHANGE datetime_closed datetime_closed DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE worksheet_attachment CHANGE datetime_add datetime_add DATETIME DEFAULT NULL, CHANGE datetime_last datetime_last DATETIME DEFAULT NULL, CHANGE datetime_open datetime_open DATETIME DEFAULT NULL, CHANGE datetime_closed datetime_closed DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE machine CHANGE datetime_add datetime_add DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE datetime_last datetime_last DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql("ALTER TABLE machine_rental CHANGE datetime_rental_start datetime_rental_start DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE datetime_rental_end datetime_rental_end DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE datetime_add datetime_add DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE datetime_last datetime_last DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql("ALTER TABLE worksheet CHANGE datetime_add datetime_add DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE datetime_last datetime_last DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE datetime_open datetime_open DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE datetime_closed datetime_closed DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql("ALTER TABLE worksheet_attachment CHANGE datetime_add datetime_add DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE datetime_last datetime_last DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE datetime_open datetime_open DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE datetime_closed datetime_closed DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }
}
