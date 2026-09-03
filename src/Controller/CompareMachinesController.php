<?php

namespace App\Controller;

use App\Service\CompareMachinesCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class CompareMachinesController extends BaseController
{
    private const TECHNICAL_SPECIFICATIONS_DIRECTORY = 'uploads/compare_machines/technical_specifications';

    private const PAIRS = CompareMachinesCatalog::PAIRS;

    #[Route('/compare-machines', name: 'index_compare_machines', methods: ['GET'])]
    #[Route('/osszehasonlito-ajanlatkero', name: 'index_compare_machines_hu', methods: ['GET'])]
    public function index(): Response
    {
        $machines = $this->loadMachines();

        return $this->render('compare_machines/index.html.twig', [
            'machines' => array_values($machines),
            'machineOptions' => $this->buildMachineOptions($machines),
            'machinePairs' => $this->buildMachinePairs($machines),
            'procons' => $this->loadProcons(),
        ]);
    }

    #[Route('/compare-machines/calculate', name: 'calculate_compare_machines', methods: ['POST'])]
    public function calculate(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $pairId = (string) ($payload['pairId'] ?? 'pair-1');
        $pairIndex = max(0, ((int) str_replace('pair-', '', $pairId)) - 1);
        $pairConfig = self::PAIRS[$pairIndex] ?? self::PAIRS[0];

        $machines = $this->loadMachines();
        $tower = $machines[$pairConfig['tower']] ?? null;
        $mobile = $machines[$pairConfig['mobile']] ?? null;

        if (!is_array($tower) || !is_array($mobile)) {
            return $this->json(['success' => false, 'message' => 'A kiválasztott darupár nem található.'], 404);
        }

        $closestMonth = $this->findClosestCostMonth($tower, $mobile);
        $months = array_key_exists('months', $payload)
            ? max(1, min(12, (int) $payload['months']))
            : $closestMonth;
        $towerCosts = $this->calculateMachineCost($tower, $months);
        $mobileCosts = $this->calculateMachineCost($mobile, $months);
        $recommendedType = $towerCosts['total'] <= $mobileCosts['total'] ? 'tower' : 'mobile';
        $recommendedCosts = $recommendedType === 'tower' ? $towerCosts : $mobileCosts;
        $alternativeCosts = $recommendedType === 'tower' ? $mobileCosts : $towerCosts;
        $recommendedName = $recommendedType === 'tower' ? 'Toronydaru' : 'Mobil toronydaru';
        $recommendedMachineName = $this->displayMachineName($this->machineName($recommendedType === 'tower' ? $tower : $mobile));
        $alternativeMachineName = $this->displayMachineName($this->machineName($recommendedType === 'tower' ? $mobile : $tower));
        $saving = abs($towerCosts['total'] - $mobileCosts['total']);

        return $this->json([
            'success' => true,
            'months' => $months,
            'closestMonth' => $closestMonth,
            'pairId' => $pairId,
            'recommendedType' => $recommendedType,
            'recommendedName' => $recommendedName,
            'recommendedMachineName' => $recommendedMachineName,
            'alternativeMachineName' => $alternativeMachineName,
            'recommendationTitle' => $recommendedName . ' javasolt',
            'recommendationSummary' => $this->formatMoney($saving) . ' becsült költségelőny ' . $months . ' hónapos futamidőnél.',
            'primaryTitle' => 'Szerintünk neked a ' . mb_strtolower($recommendedName) . ' jobb',
            'primarySummary' => $recommendedMachineName . ' modellre számolva ' . $months . ' hónappal ' . $this->formatMoney($recommendedCosts['total']) . ' becsült teljes költség jön ki. Ez jelenleg ' . $this->formatMoney($saving) . ' eltérést jelent a másik opcióhoz képest.',
            'alternativeSummary' => $alternativeMachineName . ' modellre számolva ' . $months . ' hónappal ' . $this->formatMoney($alternativeCosts['total']) . ' becsült teljes költség jelenik meg.',
            'saving' => [
                'raw' => $saving,
                'formatted' => $this->formatMoney($saving),
            ],
            'tower' => [
                'total' => $towerCosts['total'],
                'totalFormatted' => $this->formatMoney($towerCosts['total']),
                'oneTimeFormatted' => $this->formatMoney($towerCosts['one_time']),
                'monthlyFormatted' => $this->formatMoney($towerCosts['monthly']),
            ],
            'mobile' => [
                'total' => $mobileCosts['total'],
                'totalFormatted' => $this->formatMoney($mobileCosts['total']),
                'oneTimeFormatted' => $this->formatMoney($mobileCosts['one_time']),
                'monthlyFormatted' => $this->formatMoney($mobileCosts['monthly']),
            ],
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadMachines(): array
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/data/machines.json';
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
     * @param array<string, array<string, mixed>> $machines
     * @return array<int, array<string, mixed>>
     */
    private function buildMachinePairs(array $machines): array
    {
        $pairs = [];

        foreach (self::PAIRS as $index => $pair) {
            $tower = $machines[$pair['tower']] ?? null;
            $mobile = $machines[$pair['mobile']] ?? null;

            if (!is_array($tower) || !is_array($mobile)) {
                continue;
            }

            $stats = $this->buildPairStats([$tower, $mobile]);
            $towerName = $this->displayMachineName($this->machineName($tower));
            $mobileName = $this->displayMachineName($this->machineName($mobile));

            $pairs[] = [
                'id' => 'pair-' . ($index + 1),
                'active' => $index === 0,
                'tower' => $tower,
                'mobile' => $mobile,
                'towerName' => $towerName,
                'mobileName' => $mobileName,
                'title' => $towerName . ' / ' . $mobileName,
                'towerImage' => 'img/compare_machines/machines/' . $pair['tower_image'],
                'mobileImage' => 'img/compare_machines/machines/' . $pair['mobile_image'],
                'closestMonth' => $this->findClosestCostMonth($tower, $mobile),
                'maxLoad' => $this->formatRange($stats['max_load_min'], $stats['max_load_max']) . ' t',
                'maxLength' => $this->formatRange($stats['jib_length_min'], $stats['jib_length_max']) . ' m / ' . $this->formatRange($stats['tip_load_min'], $stats['tip_load_max']) . ' t',
            ];
        }

        return $pairs;
    }

    /**
     * @param array<int, array<string, mixed>> $machines
     * @return array<string, float>
     */
    private function buildPairStats(array $machines): array
    {
        $maxLoads = [];
        $jibLengths = [];
        $tipLoads = [];

        foreach ($machines as $machine) {
            $loadRows = $machine['teherbírási_adatok'] ?? [];
            if (!is_array($loadRows)) {
                continue;
            }

            foreach ($loadRows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                if (isset($row['maximális_teherbírás_t'])) {
                    $maxLoads[] = (float) $row['maximális_teherbírás_t'];
                }
                if (isset($row['gémhossz_m'])) {
                    $jibLengths[] = (float) $row['gémhossz_m'];
                }
                if (isset($row['gém_végi_teherbírás_t'])) {
                    $tipLoads[] = (float) $row['gém_végi_teherbírás_t'];
                }
            }
        }

        return [
            'max_load_min' => min($maxLoads ?: [0]),
            'max_load_max' => max($maxLoads ?: [0]),
            'jib_length_min' => min($jibLengths ?: [0]),
            'jib_length_max' => max($jibLengths ?: [0]),
            'tip_load_min' => min($tipLoads ?: [0]),
            'tip_load_max' => max($tipLoads ?: [0]),
        ];
    }

    /**
     * @param array<string, mixed> $machine
     * @return array{one_time: float, monthly: float, total: float}
     */
    private function calculateMachineCost(array $machine, int $months): array
    {
        $oneTime = 0.0;
        foreach (array_keys(CompareMachinesCatalog::ONE_TIME_COST_FIELDS) as $key) {
            $oneTime += (float) ($machine[$key] ?? 0);
        }

        $monthly = 0.0;
        foreach (array_keys(CompareMachinesCatalog::MONTHLY_COST_FIELDS) as $key) {
            $monthly += (float) ($machine[$key] ?? 0);
        }

        return [
            'one_time' => $oneTime,
            'monthly' => $monthly,
            'total' => $oneTime + ($monthly * $months),
        ];
    }

    /**
     * @param array<string, mixed> $tower
     * @param array<string, mixed> $mobile
     */
    private function findClosestCostMonth(array $tower, array $mobile): int
    {
        $closestMonth = 1;
        $smallestDifference = INF;

        for ($month = 1; $month <= 12; $month++) {
            $towerCosts = $this->calculateMachineCost($tower, $month);
            $mobileCosts = $this->calculateMachineCost($mobile, $month);
            $difference = abs($towerCosts['total'] - $mobileCosts['total']);

            if ($difference < $smallestDifference) {
                $smallestDifference = $difference;
                $closestMonth = $month;
            }
        }

        return $closestMonth;
    }

    /**
     * @param array<string, mixed> $machine
     */
    private function machineName(array $machine): string
    {
        return (string) ($machine['név'] ?? '');
    }

    private function displayMachineName(string $name): string
    {
        return match ($name) {
            'CTT91-5' => 'CTT 91-5',
            'CTT121/A-5' => 'CTT 121/A-5',
            'CTT132-6' => 'CTT 132-6',
            default => $name,
        };
    }

    /**
     * @param array<string, array<string, mixed>> $machines
     * @return array<int, string>
     */
    private function buildMachineOptions(array $machines): array
    {
        $options = [];

        foreach ($machines as $machine) {
            $name = $this->displayMachineName($this->machineName($machine));
            if ($name !== '') {
                $options[] = $name;
            }
        }

        sort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    private function formatNumber(float $value): string
    {
        $text = number_format($value, 2, ',', '');
        return rtrim(rtrim($text, '0'), ',');
    }

    private function formatRange(float $min, float $max): string
    {
        if (abs($min - $max) < 0.001) {
            return $this->formatNumber($max);
        }

        return $this->formatNumber($min) . '-' . $this->formatNumber($max);
    }

    private function formatMoney(float $value): string
    {
        if ($value >= 1000000) {
            return $this->formatNumber($value / 1000000) . ' M Ft';
        }

        return number_format($value, 0, ',', ' ') . ' Ft';
    }

    /**
     * @return array<string, array<string, array<int, string>>>
     */
    private function loadProcons(): array
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/data/procons.json';
        $content = is_file($filePath) ? file_get_contents($filePath) : false;

        if ($content === false) {
            return ['td' => ['pro' => [], 'cons' => []], 'mtd' => ['pro' => [], 'cons' => []]];
        }

        $items = json_decode($content, true);

        if (!is_array($items)) {
            return ['td' => ['pro' => [], 'cons' => []], 'mtd' => ['pro' => [], 'cons' => []]];
        }

        return [
            'td' => [
                'pro' => array_values($items['td']['pro'] ?? []),
                'cons' => array_values($items['td']['cons'] ?? []),
            ],
            'mtd' => [
                'pro' => array_values($items['mtd']['pro'] ?? []),
                'cons' => array_values($items['mtd']['cons'] ?? []),
            ],
        ];
    }

    #[Route('/download/technicalspecification', name: 'download_technical_specification', methods: ['POST', 'GET'])]
    public function downloadTechnicalSpecification(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $requestedName = trim($request->request->getString('name'));
            $specification = $this->findTechnicalSpecification($requestedName);

            if ($specification === null) {
                return $this->response(false, [
                    'content' => null,
                    'data' => [],
                ]);
            }

            $session = $request->getSession();
            do {
                $code = bin2hex(random_bytes(32));
            } while ($session->has($code));

            $session->set($code, $specification);

            return $this->response(true, [
                'content' => null,
                'data' => [
                    'code' => $code,
                ],
            ]);
        }

        $code = trim($request->query->getString('code'));
        if (preg_match('/^[a-f0-9]{64}$/D', $code) !== 1) {
            return new Response('A letöltési kód hiányzik vagy érvénytelen.', Response::HTTP_BAD_REQUEST);
        }

        $download = $request->getSession()->remove($code);
        if (!is_array($download)) {
            return new Response('A letöltési kód érvénytelen vagy már fel lett használva.', Response::HTTP_NOT_FOUND);
        }

        $filePath = $this->resolveTechnicalSpecificationPath((string) ($download['path'] ?? ''));
        if ($filePath === null) {
            return new Response('A műszaki adatlap nem található.', Response::HTTP_NOT_FOUND);
        }

        $fileName = basename((string) ($download['filename'] ?? basename($filePath)));
        $fallbackFileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $fileName) ?: 'technical-specification';
        $mimeType = function_exists('mime_content_type')
            ? mime_content_type($filePath)
            : false;

        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', $mimeType ?: 'application/octet-stream');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $fileName,
            $fallbackFileName,
        );


        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $fileName,
            $fallbackFileName,
        );


        return $response;
    }

    /**
     * @return array{filename: string, path: string}|null
     */
    private function findTechnicalSpecification(string $requestedName): ?array
    {
        if ($requestedName === '' || str_contains($requestedName, "\0") || str_contains($requestedName, '..')) {
            return null;
        }

        $directory = realpath(
            (string) $this->getParameter('kernel.project_dir') . '/' . self::TECHNICAL_SPECIFICATIONS_DIRECTORY,
        );
        if ($directory === false || !is_dir($directory)) {
            return null;
        }

        $normalizedRequestedName = $this->normalizeTechnicalSpecificationName($requestedName);
        if ($normalizedRequestedName === '') {
            return null;
        }

        $matches = [];
        foreach (new \DirectoryIterator($directory) as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $fileName = $file->getFilename();
            $nameWithoutExtension = pathinfo($fileName, PATHINFO_FILENAME);
            if (
                strcasecmp($fileName, $requestedName) === 0
                || strcasecmp($nameWithoutExtension, $requestedName) === 0
                || $this->normalizeTechnicalSpecificationName($nameWithoutExtension) === $normalizedRequestedName
            ) {
                $matches[$fileName] = $file->getRealPath();
            }
        }

        if ($matches === []) {
            return null;
        }

        ksort($matches, SORT_NATURAL | SORT_FLAG_CASE);
        $fileName = array_key_first($matches);
        $filePath = $matches[$fileName];

        if (!is_string($filePath) || $this->resolveTechnicalSpecificationPath($filePath) === null) {
            return null;
        }

        return [
            'filename' => $fileName,
            'path' => $filePath,
        ];
    }

    private function normalizeTechnicalSpecificationName(string $name): string
    {
        $name = preg_replace('~\.[^./\\\\]+$~u', '', trim($name)) ?? '';
        $name = preg_replace('/[^\p{L}\p{N}]+/u', '', $name) ?? '';

        return mb_strtolower($name, 'UTF-8');
    }

    private function resolveTechnicalSpecificationPath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        $resolvedPath = realpath($path);
        $allowedRoot = realpath(
            (string) $this->getParameter('kernel.project_dir') . '/' . self::TECHNICAL_SPECIFICATIONS_DIRECTORY,
        );

        if ($resolvedPath === false || $allowedRoot === false || !is_file($resolvedPath)) {
            return null;
        }

        $normalizedPath = strtolower(str_replace('\\', '/', $resolvedPath));
        $normalizedRoot = strtolower(rtrim(str_replace('\\', '/', $allowedRoot), '/'));

        return str_starts_with($normalizedPath, $normalizedRoot . '/') ? $resolvedPath : null;
    }
}
