<?php

namespace App\Controller;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends BaseController
{
    private const ERROR_REPORTING_TYPE_CODES = ['ERROR_REPORTING', 'ERROR_REPORT'];
    private const NEW_STATUS_CODES = ['NEW', 'OPEN'];
    private const CLOSED_STATUS_CODES = ['CLOSED', 'RETURNED', 'REPAIRED_ISSUED'];

    #[Route('/dashboard', name: 'index_dashboard')]
    public function index(Connection $connection): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'dashboard' => [
                'machines' => $this->getMachineCounters($connection),
                'errorTickets' => $this->getWorksheetCounters($connection, self::ERROR_REPORTING_TYPE_CODES),
                'worksheets' => $this->getWorksheetCounters($connection),
            ],
        ]);
    }

    private function getMachineCounters(Connection $connection): array
    {
        return [
            'total' => $this->fetchInt($connection, 'SELECT COUNT(*) FROM machine'),
            'issued' => $this->fetchInt(
                $connection,
                'SELECT COUNT(DISTINCT machine_id) FROM machine_rental WHERE status = :status',
                ['status' => '1']
            ),
            'returned' => $this->fetchInt(
                $connection,
                'SELECT COUNT(DISTINCT machine_id) FROM machine_rental WHERE status = :status',
                ['status' => '0']
            ),
            'repair' => $this->fetchInt(
                $connection,
                <<<SQL
                    SELECT COUNT(DISTINCT mr.machine_id)
                    FROM machine_rental mr
                    INNER JOIN machine m ON m.id = mr.machine_id
                    WHERE mr.status = :rentalStatus
                      AND EXISTS (
                          SELECT 1
                          FROM worksheet w
                          INNER JOIN worksheettype wt ON wt.id = w.worksheet_type_id
                          INNER JOIN worksheet_status_types wst ON wst.id = w.worksheet_status_type_id
                          WHERE wt.code IN (:errorReportingTypeCodes)
                            AND wst.code NOT IN (:closedStatusCodes)
                            AND (
                                w.rental_id = mr.id
                                OR JSON_UNQUOTE(JSON_EXTRACT(w.data, '$.machine_id')) = CAST(m.id AS CHAR)
                                OR JSON_UNQUOTE(JSON_EXTRACT(w.data, '$.machine_code')) = m.code
                            )
                      )
                SQL,
                [
                    'rentalStatus' => '1',
                    'errorReportingTypeCodes' => self::ERROR_REPORTING_TYPE_CODES,
                    'closedStatusCodes' => self::CLOSED_STATUS_CODES,
                ],
                [
                    'errorReportingTypeCodes' => ArrayParameterType::STRING,
                    'closedStatusCodes' => ArrayParameterType::STRING,
                ]
            ),
        ];
    }

    private function getWorksheetCounters(Connection $connection, ?array $worksheetTypeCodes = null): array
    {
        $typeJoin = ' INNER JOIN worksheet_status_types wst ON wst.id = w.worksheet_status_type_id';
        $typeWhere = '';
        $params = $this->last30DaysParams();
        $types = [];

        if ($worksheetTypeCodes !== null) {
            $typeJoin .= ' INNER JOIN worksheettype wt ON wt.id = w.worksheet_type_id';
            $typeWhere = ' AND wt.code IN (:worksheetTypeCodes)';
            $params['worksheetTypeCodes'] = $worksheetTypeCodes;
            $types['worksheetTypeCodes'] = ArrayParameterType::STRING;
        }

        $from = ' FROM worksheet w' . $typeJoin . ' WHERE 1 = 1' . $typeWhere
            . ' AND w.datetime_add BETWEEN :dateFrom AND :dateTo';

        return [
            'total' => $this->fetchInt($connection, 'SELECT COUNT(*)' . $from, $params, $types),
            'new' => $this->fetchInt(
                $connection,
                'SELECT COUNT(*)' . $from . ' AND wst.code IN (:newStatusCodes)',
                $params + ['newStatusCodes' => self::NEW_STATUS_CODES],
                $types + ['newStatusCodes' => ArrayParameterType::STRING]
            ),
            'closed' => $this->fetchInt(
                $connection,
                'SELECT COUNT(*)' . $from . ' AND wst.code IN (:closedStatusCodes)',
                $params + ['closedStatusCodes' => self::CLOSED_STATUS_CODES],
                $types + ['closedStatusCodes' => ArrayParameterType::STRING]
            ),
            'inProgress' => $this->fetchInt(
                $connection,
                'SELECT COUNT(*)' . $from . ' AND wst.code NOT IN (:excludedStatusCodes)',
                $params + ['excludedStatusCodes' => array_merge(self::NEW_STATUS_CODES, self::CLOSED_STATUS_CODES)],
                $types + ['excludedStatusCodes' => ArrayParameterType::STRING]
            ),
        ];
    }

    private function last30DaysParams(): array
    {
        $today = new \DateTimeImmutable('today');

        return [
            'dateFrom' => $today->modify('-30 days')->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
            'dateTo' => $today->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
        ];
    }

    private function fetchInt(Connection $connection, string $sql, array $params = [], array $types = []): int
    {
        return (int) $connection->executeQuery($sql, $params, $types)->fetchOne();
    }
}
