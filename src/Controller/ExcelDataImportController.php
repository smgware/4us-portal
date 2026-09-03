<?php

namespace App\Controller;

use App\Repository\MachineCategoryRepository;
use App\Repository\MachineRepository;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

class ExcelDataImportController extends BaseController
{
    #[Route('/import-machines-and-categories', name: 'importmachinesandcategories')]
    public function importMachinesAndCategories(
        MachineCategoriesController $machineCategoriesController,
        MachineCategoryRepository $machineCategoryRepository,
        MachinesController $machinesController,
        MachineRepository $machineRepository
    ): Response {
        $projectDir = $this->getParameter('kernel.project_dir');
        $filePath = $projectDir . '/data/dataimport.xlsx';

        if (!is_file($filePath)) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Import fajl nem talalhato: data/dataimport.xlsx',
                ],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        try {
            $data = $this->readExcelRowsWithPhpSpreadsheet($filePath);
        } catch (\RuntimeException $exception) {
            return $this->response(false, [
                'data' => [
                    'error' => $exception->getMessage(),
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $rows = $this->buildRowsFromExcelData($data);
        $slugger = new AsciiSlugger();
        $categories = [];
        $machines = [];
        $errors = [];

        foreach ($rows as $row) {
            $categoryTitle = $this->getRowValue($row, ['Kategoria', 'Kategória', 'KategĂłria']);
            if ($categoryTitle === '') {
                continue;
            }

            $categoryKey = mb_strtolower($categoryTitle);
            if (isset($categories[$categoryKey])) {
                continue;
            }

            $existingCategory = $machineCategoryRepository->findOneBy(['title' => $categoryTitle]);
            if ($existingCategory) {
                $categories[$categoryKey] = [
                    'value' => $categoryTitle,
                    'id' => (int) $existingCategory->getId(),
                    'success' => true,
                    'mode' => 'exists',
                ];
                continue;
            }

            $importData = [
                'title' => $categoryTitle,
                'code' => strtolower($slugger->slug($categoryTitle)->toString()),
                'status' => '1',
            ];

            $request = new Request([], $importData);
            $response = $machineCategoriesController->save_machine_categories($request, $machineCategoryRepository);
            $responseData = json_decode($response->getContent(), true);

            $categories[$categoryKey] = [
                'value' => $categoryTitle,
                'id' => (int) ($responseData['data']['id'] ?? 0),
                'success' => (bool) ($responseData['success'] ?? false),
                'mode' => 'insert',
            ];
        }

        foreach ($rows as $rowNumber => $row) {
            $categoryTitle  = $this->getRowValue($row, ['Kategória']);
            $machineTitle   = $this->getRowValue($row, ['Gép megnevezése']);
            $machineCode    = $this->getRowValue($row, ['Gépszám']);
            $sku            = $this->getRowValue($row, ['Cikkszám']);
            $year           = $this->getRowValue($row, ['Évjárat']);

            if ($machineTitle === '') {
                $errors[] = [
                    'row' => $rowNumber,
                    'error' => 'Missing machine title.',
                ];
                continue;
            }

            $categoryKey = mb_strtolower($categoryTitle);
            $categoryId = (int) ($categories[$categoryKey]['id'] ?? 0);
            if ($categoryId <= 0) {
                $errors[] = [
                    'row' => $rowNumber,
                    'machine' => $machineTitle,
                    'category' => $categoryTitle,
                    'error' => 'Missing imported category id.',
                ];
                continue;
            }

            $existingMachine = $machineRepository->findOneBy(['title' => $machineTitle]);
            if ($existingMachine) {
                $machines[] = [
                    'id' => $existingMachine->getId(),
                    'title' => $existingMachine->getTitle(),
                    'code' => $existingMachine->getCode(),
                    'machine_category_id' => $existingMachine->getMachineCategory()?->getId(),
                    'data' => $existingMachine->getData(),
                    'status' => $existingMachine->getStatus(),
                    'mode' => 'exists',
                ];
                continue;
            }

            $importData = [
                'title' => $machineTitle,
                'code' => $machineCode,
                'machine_category_id' => $categoryId,
                'data' => [
                    'sku' => $sku,
                    'year' => $year
                ],
                'status' => '1',
            ];

            $request = new Request([], $importData);
            $response = $machinesController->save_machines($request, $machineRepository, $machineCategoryRepository);
            $responseData = json_decode($response->getContent(), true);

            if (!($responseData['success'] ?? false)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'machine' => $machineTitle,
                    'response' => $responseData,
                ];
                continue;
            }

            $importData['id'] = $responseData['data']['id'] ?? null;
            $importData['mode'] = 'insert';
            $machines[] = $importData;
        }

        return $this->response(true, [
            'data' => [
                'categories' => array_values($categories),
                'machines' => $machines,
                'errors' => $errors,
                'categoryCount' => count($categories),
                'machineCount' => count($machines),
                'errorCount' => count($errors),
            ],
        ]);
    }

    /**
     * @param array<int, array<int, string>> $data
     * @return array<int, array<string, string>>
     */
    private function buildRowsFromExcelData(array $data): array
    {
        $headers = [];
        $rows = [];

        foreach ($data as $rowId => $item) {
            if ($rowId === 0) {
                $headers = array_map(static fn (string $header): string => trim($header), $item);
                continue;
            }

            $row = [];
            foreach ($headers as $columnId => $header) {
                if ($header !== '') {
                    $row[$header] = trim((string) ($item[$columnId] ?? ''));
                }
            }

            if ($row !== []) {
                $rows[$rowId] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, string> $row
     * @param array<int, string> $keys
     */
    private function getRowValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readExcelRowsWithPhpSpreadsheet(string $filePath): array
    {
        if (!class_exists(IOFactory::class)) {
            throw new \RuntimeException('PhpSpreadsheet nincs telepitve vagy nem talalhato.');
        }

        $excel = IOFactory::load($filePath);
        $sheet = $excel->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        $rows = [];
        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $cellCoordinate = Coordinate::stringFromColumnIndex($column) . $row;
                $rowData[$column - 1] = trim((string) $sheet->getCell($cellCoordinate)->getFormattedValue());
            }

            if (array_filter($rowData, static fn (string $value): bool => $value !== '') !== []) {
                $rows[] = $rowData;
            }
        }

        return $rows;
    }
}
