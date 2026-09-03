<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReportsController extends BaseController
{
    #[Route('/reports', name: 'index_reports')]
    public function index(Request $request, Connection $connection): Response
    {
        $filters = $this->buildFilters($request);

        return $this->render('reports/index.html.twig', [
            'filters' => $filters,
            'machineCategories' => $connection->fetchAllAssociative('SELECT id, title, code FROM machinecategory ORDER BY title ASC'),
        ]);
    }

    #[Route('/reports/data', name: 'index_reports_data', methods: ['POST'])]
    public function data(Request $request, Connection $connection): Response
    {
        $filters = $this->buildFilters($request);

        return $this->json([
            'html' => $this->renderView('reports/_data.html.twig', [
                'report' => $this->buildReport($connection, $filters),
            ]),
        ]);
    }

    private function buildFilters(Request $request): array
    {
        $input = $request->isMethod('POST') ? $request->request : $request->query;
        $period = (string) $input->get('period', 'last30');
        $today = new \DateTimeImmutable('today');

        [$dateFrom, $dateTo] = match ($period) {
            'current_month' => [$today->modify('first day of this month'), $today],
            'previous_month' => [
                $today->modify('first day of previous month'),
                $today->modify('last day of previous month'),
            ],
            'current_year' => [$today->setDate((int) $today->format('Y'), 1, 1), $today],
            'custom' => [
                $this->parseDate((string) $input->get('date_from', '')) ?? $today->modify('-30 days'),
                $this->parseDate((string) $input->get('date_to', '')) ?? $today,
            ],
            default => [$today->modify('-30 days'), $today],
        };

        if ($dateTo < $dateFrom) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [
            'period' => $period,
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d'),
            'date_from_sql' => $dateFrom->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
            'date_to_sql' => $dateTo->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
            'machine_category_id' => (int) $input->get('machine_category_id', 0),
        ];
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildReport(Connection $connection, array $filters): array
    {
        $base = $this->baseErrorReportSql($filters);
        $params = $base['params'];

        $totalErrors = $this->fetchInt($connection, 'SELECT COUNT(*) ' . $base['fromWhere'], $params);
        $affectedMachines = $this->fetchInt(
            $connection,
            "SELECT COUNT(DISTINCT COALESCE(CAST(m.id AS CHAR), JSON_UNQUOTE(JSON_EXTRACT(w.data, '$.machine_code')))) " . $base['fromWhere'],
            $params
        );

        $avgHours = $connection->executeQuery(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, w.datetime_add, COALESCE(w.datetime_closed, w.datetime_last))) / 60
             " . $base['fromWhere'] . "
               AND wst.code IN ('CLOSED', 'RETURNED', 'REPAIRED_ISSUED')
               AND COALESCE(w.datetime_closed, w.datetime_last) IS NOT NULL",
            $params
        )->fetchOne();
        $avgHours = $avgHours !== null ? round((float) $avgHours, 1) : 0.0;

        $machinesWithRepeatedErrors = $this->fetchInt(
            $connection,
            "SELECT COUNT(*) FROM (
                SELECT COALESCE(CAST(m.id AS CHAR), JSON_UNQUOTE(JSON_EXTRACT(w.data, '$.machine_code'))) AS machine_key
                " . $base['fromWhere'] . "
                GROUP BY machine_key
                HAVING COUNT(*) > 1
            ) repeated_machines",
            $params
        );
        $repeatPercent = $affectedMachines > 0 ? round(($machinesWithRepeatedErrors / $affectedMachines) * 100, 1) : 0.0;

        return [
            'kpi' => [
                'errorReports' => $totalErrors,
                'affectedMachines' => $affectedMachines,
                'avgTurnaroundHours' => $avgHours,
                'repeatPercent' => $repeatPercent,
            ],
            'trend' => $this->buildTrend($connection, $filters),
            'topCategories' => $this->buildTopCategories($connection, $filters),
            'topMachines' => $this->buildTopMachines($connection, $filters),
            'timeline' => $this->buildTimeline($connection, $filters),
        ];
    }

    private function baseErrorReportSql(array $filters): array
    {
        $params = [
            'dateFrom' => $filters['date_from_sql'],
            'dateTo' => $filters['date_to_sql'],
        ];

        $categoryWhere = '';
        if (($filters['machine_category_id'] ?? 0) > 0) {
            $categoryWhere = ' AND m.machine_category_id = :machineCategoryId';
            $params['machineCategoryId'] = $filters['machine_category_id'];
        }

        return [
            'fromWhere' => "
                FROM worksheet w
                INNER JOIN worksheettype wt ON wt.id = w.worksheet_type_id
                INNER JOIN worksheet_status_types wst ON wst.id = w.worksheet_status_type_id
                LEFT JOIN machine m ON (
                    JSON_UNQUOTE(JSON_EXTRACT(w.data, '$.machine_id')) = CAST(m.id AS CHAR)
                    OR JSON_UNQUOTE(JSON_EXTRACT(w.data, '$.machine_code')) = m.code
                )
                WHERE wt.code IN ('ERROR_REPORT', 'ERROR_REPORTING')
                  AND w.datetime_add BETWEEN :dateFrom AND :dateTo
                  {$categoryWhere}
            ",
            'params' => $params,
        ];
    }

    private function buildTrend(Connection $connection, array $filters): array
    {
        $from = new \DateTimeImmutable(substr($filters['date_from_sql'], 0, 10));
        $to = new \DateTimeImmutable(substr($filters['date_to_sql'], 0, 10));
        $cursor = $from->modify('first day of this month');
        $months = [];

        while ($cursor <= $to && count($months) < 24) {
            $months[$cursor->format('Y-m')] = [
                'label' => $cursor->format('Y.m'),
                'count' => 0,
                'height' => 8,
            ];
            $cursor = $cursor->modify('+1 month');
        }

        if (count($months) > 12) {
            $months = array_slice($months, -12, null, true);
        }

        $base = $this->baseErrorReportSql($filters);
        $rows = $connection->fetchAllAssociative(
            "SELECT DATE_FORMAT(w.datetime_add, '%Y-%m') AS period_key, COUNT(*) AS count_value " .
            $base['fromWhere'] .
            " GROUP BY period_key ORDER BY period_key ASC",
            $base['params']
        );

        foreach ($rows as $row) {
            if (isset($months[$row['period_key']])) {
                $months[$row['period_key']]['count'] = (int) $row['count_value'];
            }
        }

        $max = max(1, ...array_column($months, 'count'));
        foreach ($months as &$month) {
            $month['height'] = max(8, (int) round(($month['count'] / $max) * 100));
        }

        return array_values($months);
    }

    private function buildTopCategories(Connection $connection, array $filters): array
    {
        $base = $this->baseErrorReportSql($filters);
        $rows = $connection->fetchAllAssociative(
            "SELECT COALESCE(mc.title, 'Ismeretlen') AS title, COUNT(*) AS count_value " .
            str_replace('WHERE wt.code', 'LEFT JOIN machinecategory mc ON mc.id = m.machine_category_id WHERE wt.code', $base['fromWhere']) .
            " GROUP BY title ORDER BY count_value DESC, title ASC LIMIT 5",
            $base['params']
        );

        return $this->withPercentages($rows);
    }

    private function buildTopMachines(Connection $connection, array $filters): array
    {
        $base = $this->baseErrorReportSql($filters);

        return $connection->fetchAllAssociative(
            "SELECT
                COALESCE(m.title, JSON_UNQUOTE(JSON_EXTRACT(w.data, '$.machine_title')), '-') AS machine_title,
                COALESCE(m.code, JSON_UNQUOTE(JSON_EXTRACT(w.data, '$.machine_code')), '-') AS machine_code,
                COALESCE(mc.title, '-') AS category_title,
                COUNT(*) AS error_count,
                MAX(w.datetime_add) AS last_error
             " .
            str_replace('WHERE wt.code', 'LEFT JOIN machinecategory mc ON mc.id = m.machine_category_id WHERE wt.code', $base['fromWhere']) .
            " GROUP BY machine_title, machine_code, category_title ORDER BY error_count DESC, last_error DESC LIMIT 5",
            $base['params']
        );
    }

    private function buildTimeline(Connection $connection, array $filters): array
    {
        $base = $this->baseErrorReportSql($filters);
        $rows = $connection->fetchAllAssociative(
            "SELECT
                SUM(CASE WHEN TIMESTAMPDIFF(HOUR, w.datetime_add, COALESCE(w.datetime_closed, w.datetime_last)) BETWEEN 0 AND 4 THEN 1 ELSE 0 END) AS h0_4,
                SUM(CASE WHEN TIMESTAMPDIFF(HOUR, w.datetime_add, COALESCE(w.datetime_closed, w.datetime_last)) > 4 AND TIMESTAMPDIFF(HOUR, w.datetime_add, COALESCE(w.datetime_closed, w.datetime_last)) <= 24 THEN 1 ELSE 0 END) AS h4_24,
                SUM(CASE WHEN TIMESTAMPDIFF(HOUR, w.datetime_add, COALESCE(w.datetime_closed, w.datetime_last)) > 24 AND TIMESTAMPDIFF(HOUR, w.datetime_add, COALESCE(w.datetime_closed, w.datetime_last)) <= 72 THEN 1 ELSE 0 END) AS d1_3,
                SUM(CASE WHEN TIMESTAMPDIFF(HOUR, w.datetime_add, COALESCE(w.datetime_closed, w.datetime_last)) > 72 AND TIMESTAMPDIFF(HOUR, w.datetime_add, COALESCE(w.datetime_closed, w.datetime_last)) <= 168 THEN 1 ELSE 0 END) AS d3_7,
                SUM(CASE WHEN TIMESTAMPDIFF(HOUR, w.datetime_add, COALESCE(w.datetime_closed, w.datetime_last)) > 168 THEN 1 ELSE 0 END) AS d7_plus
             " . $base['fromWhere'] . "
               AND wst.code IN ('CLOSED', 'RETURNED', 'REPAIRED_ISSUED')
               AND COALESCE(w.datetime_closed, w.datetime_last) IS NOT NULL",
            $base['params']
        )[0] ?? [];

        $items = [
            ['label' => '0-4 óra', 'count' => (int) ($rows['h0_4'] ?? 0)],
            ['label' => '4-24 óra', 'count' => (int) ($rows['h4_24'] ?? 0)],
            ['label' => '1-3 nap', 'count' => (int) ($rows['d1_3'] ?? 0)],
            ['label' => '3-7 nap', 'count' => (int) ($rows['d3_7'] ?? 0)],
            ['label' => '7+ nap', 'count' => (int) ($rows['d7_plus'] ?? 0)],
        ];
        $max = max(1, ...array_column($items, 'count'));

        foreach ($items as &$item) {
            $item['percent'] = max(4, (int) round(($item['count'] / $max) * 100));
        }

        return $items;
    }

    private function withPercentages(array $rows): array
    {
        $max = max(1, ...array_map(static fn (array $row): int => (int) $row['count_value'], $rows ?: [['count_value' => 0]]));

        return array_map(static function (array $row) use ($max): array {
            return [
                'title' => $row['title'],
                'count' => (int) $row['count_value'],
                'percent' => max(4, (int) round(((int) $row['count_value'] / $max) * 100)),
            ];
        }, $rows);
    }

    private function fetchInt(Connection $connection, string $sql, array $params = []): int
    {
        return (int) $connection->executeQuery($sql, $params)->fetchOne();
    }
}
