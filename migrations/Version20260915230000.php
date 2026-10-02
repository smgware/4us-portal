<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stores the partner contacts selected for worksheet notifications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE worksheet_notificated_contact (
    id INT AUTO_INCREMENT NOT NULL,
    worksheet_id INT NOT NULL,
    partner_id INT NOT NULL,
    partner_contact_id INT NOT NULL,
    INDEX IDX_WORKSHEET_NOTIFICATED_WORKSHEET (worksheet_id),
    INDEX IDX_WORKSHEET_NOTIFICATED_PARTNER (partner_id),
    INDEX IDX_WORKSHEET_NOTIFICATED_CONTACT (partner_contact_id),
    UNIQUE INDEX UNIQ_WORKSHEET_NOTIFICATED_CONTACT (worksheet_id, partner_contact_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB
SQL);
        $this->addSql('ALTER TABLE worksheet_notificated_contact ADD CONSTRAINT FK_WORKSHEET_NOTIFICATED_WORKSHEET FOREIGN KEY (worksheet_id) REFERENCES worksheet (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE worksheet_notificated_contact ADD CONSTRAINT FK_WORKSHEET_NOTIFICATED_PARTNER FOREIGN KEY (partner_id) REFERENCES partner (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE worksheet_notificated_contact ADD CONSTRAINT FK_WORKSHEET_NOTIFICATED_CONTACT FOREIGN KEY (partner_contact_id) REFERENCES partner_contact (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE worksheet_notificated_contact');
    }
}
