<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class CompareMachinesCatalog
{
    public const PAIRS = [
        ['tower' => 'CTT91-5', 'mobile' => 'AT-3', 'tower_image' => 'CTT91-5.png', 'mobile_image' => 'AT-3.png'],
        ['tower' => '110EC-B6', 'mobile' => 'AT-4', 'tower_image' => '110EC-B6.png', 'mobile_image' => 'AT-4.png'],
        ['tower' => 'CTT132-6', 'mobile' => 'AT-5', 'tower_image' => 'CTT132-6.png', 'mobile_image' => 'AT-5.png'],
        ['tower' => 'CTT162-8', 'mobile' => 'AT-6', 'tower_image' => 'CTT162-8.png', 'mobile_image' => 'AT-6.png'],
    ];

    public const ONE_TIME_COST_FIELDS = [
        'alap_tervezés' => 'Alap tervezés',
        'alap_kivitelezés' => 'Alap kivitelezés',
        'elektromos_csatlakozás_kiépítése' => 'Elektromos csatlakozás kiépítése',
        'telepítés' => 'Telepítés',
        'beüzemelés' => 'Beüzemelés',
        'veszélymentes_üzemmód_szabály' => 'Veszélymentes üzemmód szabály',
        'bontás' => 'Bontás',
        'alap_elbontása' => 'Alap elbontása',
    ];

    public const MONTHLY_COST_FIELDS = [
        'havi_díj' => 'Havi díj',
        'kezelő_díja' => 'Kezelő díja',
    ];

    public function __construct(private readonly ParameterBagInterface $parameterBag)
    {
    }

    /**
     * @return array{
     *     pairId: string,
     *     selectedType: 'tower'|'mobile',
     *     otherType: 'tower'|'mobile',
     *     tower: array<string, mixed>,
     *     mobile: array<string, mixed>,
     *     selectedMachine: array<string, mixed>,
     *     otherMachine: array<string, mixed>
     * }|null
     */
    public function resolveSelection(string $selectedMachineName, string $preferredPairId = ''): ?array
    {
        $normalizedSelection = $this->normalizeMachineName($selectedMachineName);
        if ($normalizedSelection === '') {
            return null;
        }

        $machines = $this->loadMachines();
        $pairIndexes = array_keys(self::PAIRS);

        if (preg_match('/^pair-(\d+)$/D', $preferredPairId, $matches) === 1) {
            $preferredIndex = (int) $matches[1] - 1;
            if (isset(self::PAIRS[$preferredIndex])) {
                $pairIndexes = array_values(array_unique([$preferredIndex, ...$pairIndexes]));
            }
        }

        foreach ($pairIndexes as $index) {
            $pair = self::PAIRS[$index];
            $tower = $machines[$pair['tower']] ?? null;
            $mobile = $machines[$pair['mobile']] ?? null;

            if (!is_array($tower) || !is_array($mobile)) {
                continue;
            }

            foreach (['tower', 'mobile'] as $type) {
                $machine = $type === 'tower' ? $tower : $mobile;
                $machineName = (string) ($machine['név'] ?? '');
                $displayName = $this->displayMachineName($machineName);

                if (
                    $this->normalizeMachineName($machineName) !== $normalizedSelection
                    && $this->normalizeMachineName($displayName) !== $normalizedSelection
                ) {
                    continue;
                }

                $otherType = $type === 'tower' ? 'mobile' : 'tower';

                return [
                    'pairId' => 'pair-' . ($index + 1),
                    'selectedType' => $type,
                    'otherType' => $otherType,
                    'tower' => $tower,
                    'mobile' => $mobile,
                    'selectedMachine' => $machine,
                    'otherMachine' => $otherType === 'tower' ? $tower : $mobile,
                ];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $machine
     * @return array<string, mixed>
     */
    public function buildMachineView(array $machine, int $months, bool $selected): array
    {
        $costs = $this->calculateMachineCost($machine, $months);
        $capacityRows = [];

        foreach (($machine['teherbírási_adatok'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $capacityRows[] = [
                'jibLength' => $this->formatMeasurement($row['gémhossz_m'] ?? null, 'm'),
                'tipLoad' => $this->formatMeasurement($row['gém_végi_teherbírás_t'] ?? null, 't'),
                'maximumLoad' => $this->formatMeasurement($row['maximális_teherbírás_t'] ?? null, 't'),
                'maximumLoadReach' => $this->formatMeasurement($row['maximális_teherbírás_kinyúlása_m'] ?? null, 'm'),
            ];
        }

        return [
            'name' => $this->displayMachineName((string) ($machine['név'] ?? '')),
            'type' => (string) ($machine['eszköz'] ?? ''),
            'selected' => $selected,
            'capacityRows' => $capacityRows,
            'oneTimeItems' => $this->buildCostItems($machine, self::ONE_TIME_COST_FIELDS),
            'monthlyItems' => $this->buildCostItems($machine, self::MONTHLY_COST_FIELDS),
            'oneTimeTotal' => $costs['one_time'],
            'oneTimeTotalFormatted' => $this->formatMoney($costs['one_time']),
            'monthlyTotal' => $costs['monthly'],
            'monthlyTotalFormatted' => $this->formatMoney($costs['monthly']),
            'durationCost' => $costs['monthly'] * $months,
            'durationCostFormatted' => $this->formatMoney($costs['monthly'] * $months),
            'total' => $costs['total'],
            'totalFormatted' => $this->formatMoney($costs['total']),
        ];
    }

    /**
     * @param array<string, mixed> $machine
     * @return array{one_time: float, monthly: float, total: float}
     */
    public function calculateMachineCost(array $machine, int $months): array
    {
        $oneTime = 0.0;
        foreach (array_keys(self::ONE_TIME_COST_FIELDS) as $key) {
            $oneTime += (float) ($machine[$key] ?? 0);
        }

        $monthly = 0.0;
        foreach (array_keys(self::MONTHLY_COST_FIELDS) as $key) {
            $monthly += (float) ($machine[$key] ?? 0);
        }

        return [
            'one_time' => $oneTime,
            'monthly' => $monthly,
            'total' => $oneTime + ($monthly * $months),
        ];
    }

    public function displayMachineName(string $name): string
    {
        return match ($name) {
            'CTT91-5' => 'CTT 91-5',
            'CTT121/A-5' => 'CTT 121/A-5',
            'CTT132-6' => 'CTT 132-6',
            default => $name,
        };
    }

    public function formatMoney(float $value): string
    {
        return number_format($value, 0, ',', ' ') . ' Ft';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadMachines(): array
    {
        $projectDirectory = (string) $this->parameterBag->get('kernel.project_dir');
        $filePath = $projectDirectory . '/data/machines.json';
        $content = is_file($filePath) ? file_get_contents($filePath) : false;
        if ($content === false) {
            return [];
        }

        $items = json_decode($content, true);
        if (!is_array($items)) {
            return [];
        }

        $machines = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['név'])) {
                $machines[(string) $item['név']] = $item;
            }
        }

        return $machines;
    }

    /**
     * @param array<string, mixed> $machine
     * @param array<string, string> $fields
     * @return array<int, array{label: string, value: string}>
     */
    private function buildCostItems(array $machine, array $fields): array
    {
        $items = [];
        foreach ($fields as $key => $label) {
            $items[] = [
                'label' => $label,
                'value' => $this->formatCostValue($machine[$key] ?? null),
            ];
        }

        return $items;
    }

    private function formatCostValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '–';
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return $this->formatMoney((float) $value);
        }

        return trim((string) $value);
    }

    private function formatMeasurement(mixed $value, string $unit): string
    {
        if (!is_numeric($value)) {
            return '–';
        }

        $formatted = number_format((float) $value, 2, ',', ' ');
        $formatted = rtrim(rtrim($formatted, '0'), ',');

        return $formatted . ' ' . $unit;
    }

    private function normalizeMachineName(string $name): string
    {
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', '', trim($name)) ?? '';

        return mb_strtolower($normalized, 'UTF-8');
    }
}
