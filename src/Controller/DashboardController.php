<?php

namespace App\Controller;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends BaseController
{
    #[Route('/dashboard', name: 'index_dashboard')]
    public function index(Connection $connection): Response
    {
        $companySites = $connection->executeQuery(<<<'SQL'
SELECT
    company_site.id,
    company_site.title,
    company_site.code,
    COUNT(machine.id) AS total_machines,
    COALESCE(SUM(
        CASE
            WHEN machine.id IS NOT NULL
                AND machine.status = '1'
                AND open_repair.machine_id IS NULL
                AND open_rental.machine_id IS NULL
            THEN 1 ELSE 0
        END
    ), 0) AS free_machines,
    COALESCE(SUM(
        CASE
            WHEN machine.id IS NOT NULL
                AND machine.status = '1'
                AND open_repair.machine_id IS NULL
                AND open_rental.machine_id IS NOT NULL
            THEN 1 ELSE 0
        END
    ), 0) AS rented_machines,
    COALESCE(SUM(
        CASE
            WHEN machine.id IS NOT NULL
                AND machine.status = '1'
                AND open_repair.machine_id IS NOT NULL
            THEN 1 ELSE 0
        END
    ), 0) AS repair_machines,
    COALESCE(SUM(
        CASE
            WHEN machine.id IS NOT NULL AND machine.status != '1'
            THEN 1 ELSE 0
        END
    ), 0) AS inactive_machines
FROM company_sites company_site
LEFT JOIN machine machine ON machine.company_site_id = company_site.id
LEFT JOIN (
    SELECT DISTINCT worksheet.machine_id
    FROM worksheet worksheet
    INNER JOIN worksheettype worksheet_type ON worksheet_type.id = worksheet.worksheet_type_id
    INNER JOIN worksheet_status_types worksheet_status ON worksheet_status.id = worksheet.worksheet_status_type_id
    WHERE worksheet.status = '1'
        AND worksheet.machine_id IS NOT NULL
        AND worksheet_type.code IN (:errorWorksheetTypeCodes)
        AND worksheet_status.code NOT IN (:finalWorksheetStatusCodes)
) open_repair ON open_repair.machine_id = machine.id
LEFT JOIN (
    SELECT DISTINCT machine_rental.machine_id
    FROM machine_rental machine_rental
    WHERE machine_rental.status = '1'
        AND NOT EXISTS (
            SELECT 1
            FROM worksheet return_worksheet
            INNER JOIN worksheettype return_worksheet_type
                ON return_worksheet_type.id = return_worksheet.worksheet_type_id
            INNER JOIN worksheet_status_types return_worksheet_status
                ON return_worksheet_status.id = return_worksheet.worksheet_status_type_id
            WHERE return_worksheet.machine_rental_id = machine_rental.id
                AND return_worksheet.status = '1'
                AND return_worksheet_type.code = 'MACHINE_RETURN'
                AND return_worksheet_status.code IN (:finalWorksheetStatusCodes)
        )
) open_rental ON open_rental.machine_id = machine.id
WHERE company_site.status = '1'
    AND company_site.title != :unassignedSiteTitle
    AND UPPER(TRIM(company_site.code)) != 'UNASSIGNED'
GROUP BY company_site.id, company_site.title, company_site.code
ORDER BY company_site.title ASC
SQL, [
            'errorWorksheetTypeCodes' => ['ERROR_REPORT', 'ERROR_REPORTING'],
            'finalWorksheetStatusCodes' => ['CLOSED', 'TAKEN_BACK', 'REPAIRED_RETURNED'],
            'unassignedSiteTitle' => 'Nincs megadva',
        ], [
            'errorWorksheetTypeCodes' => ArrayParameterType::STRING,
            'finalWorksheetStatusCodes' => ArrayParameterType::STRING,
        ])->fetchAllAssociative();

        $summary = [
            'site_count' => count($companySites),
            'total_machines' => 0,
            'free_machines' => 0,
            'rented_machines' => 0,
            'repair_machines' => 0,
            'inactive_machines' => 0,
        ];

        foreach ($companySites as &$companySite) {
            foreach (['id', 'total_machines', 'free_machines', 'rented_machines', 'repair_machines', 'inactive_machines'] as $field) {
                $companySite[$field] = (int) $companySite[$field];
            }

            $companySite['active_machines'] = max(
                0,
                $companySite['total_machines'] - $companySite['inactive_machines'],
            );
            $companySite['free_percent'] = $companySite['total_machines'] > 0
                ? round(($companySite['free_machines'] / $companySite['total_machines']) * 100, 1)
                : 0.0;
            $companySite['rented_percent'] = $companySite['total_machines'] > 0
                ? round(($companySite['rented_machines'] / $companySite['total_machines']) * 100, 1)
                : 0.0;
            $companySite['repair_percent'] = $companySite['total_machines'] > 0
                ? round(($companySite['repair_machines'] / $companySite['total_machines']) * 100, 1)
                : 0.0;
            $companySite['inactive_percent'] = $companySite['total_machines'] > 0
                ? round(($companySite['inactive_machines'] / $companySite['total_machines']) * 100, 1)
                : 0.0;

            foreach (['total_machines', 'free_machines', 'rented_machines', 'repair_machines', 'inactive_machines'] as $field) {
                $summary[$field] += $companySite[$field];
            }
        }
        unset($companySite);

        return $this->render('dashboard/index.html.twig', [
            'companySites' => $companySites,
            'summary' => $summary,
            'generatedAt' => new \DateTimeImmutable(),
        ]);
    }
}
