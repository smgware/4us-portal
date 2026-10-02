<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates company sites and replaces the machine current-location text with a required company-site relation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE company_sites (
    id INT AUTO_INCREMENT NOT NULL,
    title VARCHAR(255) NOT NULL,
    code VARCHAR(255) NOT NULL,
    uid_add INT DEFAULT NULL,
    uid_last INT DEFAULT NULL,
    datetime_add DATETIME DEFAULT NULL,
    datetime_last DATETIME DEFAULT NULL,
    status VARCHAR(255) NOT NULL,
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB
SQL);

        $this->addSql('ALTER TABLE machine ADD company_site_id INT DEFAULT NULL AFTER machine_category_id');

        $this->addSql(<<<'SQL'
INSERT INTO company_sites (title, code, uid_add, uid_last, datetime_add, datetime_last, status)
SELECT DISTINCT TRIM(current_location), '', NULL, NULL, NOW(), NOW(), '1'
FROM machine
WHERE current_location IS NOT NULL AND TRIM(current_location) <> ''
SQL);

        $this->addSql(<<<'SQL'
INSERT INTO company_sites (title, code, uid_add, uid_last, datetime_add, datetime_last, status)
SELECT 'Nincs megadva', 'UNASSIGNED', NULL, NULL, NOW(), NOW(), '1'
WHERE EXISTS (
    SELECT 1 FROM machine WHERE current_location IS NULL OR TRIM(current_location) = ''
)
SQL);

        $this->addSql(<<<'SQL'
UPDATE machine machine_record
INNER JOIN company_sites company_site ON company_site.title = TRIM(machine_record.current_location)
SET machine_record.company_site_id = company_site.id
WHERE machine_record.current_location IS NOT NULL AND TRIM(machine_record.current_location) <> ''
SQL);

        $this->addSql(<<<'SQL'
UPDATE machine machine_record
INNER JOIN company_sites company_site ON company_site.code = 'UNASSIGNED'
SET machine_record.company_site_id = company_site.id
WHERE machine_record.company_site_id IS NULL
SQL);

        $this->addSql('ALTER TABLE machine MODIFY company_site_id INT NOT NULL');
        $this->addSql('CREATE INDEX IDX_MACHINE_COMPANY_SITE ON machine (company_site_id)');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_MACHINE_COMPANY_SITE FOREIGN KEY (company_site_id) REFERENCES company_sites (id)');
        $this->addSql('ALTER TABLE machine DROP current_location');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine ADD current_location VARCHAR(255) DEFAULT NULL AFTER data');
        $this->addSql(<<<'SQL'
UPDATE machine machine_record
INNER JOIN company_sites company_site ON company_site.id = machine_record.company_site_id
SET machine_record.current_location = CASE
    WHEN company_site.code = 'UNASSIGNED' THEN NULL
    ELSE company_site.title
END
SQL);
        $this->addSql('ALTER TABLE machine DROP FOREIGN KEY FK_MACHINE_COMPANY_SITE');
        $this->addSql('DROP INDEX IDX_MACHINE_COMPANY_SITE ON machine');
        $this->addSql('ALTER TABLE machine DROP company_site_id');
        $this->addSql('DROP TABLE company_sites');
    }
}
