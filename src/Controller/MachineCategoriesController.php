<?php

namespace App\Controller;

use App\Entity\MachineCategory;
use App\Repository\MachineCategoryRepository;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MachineCategoriesController extends BaseController
{
    private const ITEMS_PER_PAGE = 100;

    #[Route('/machine-categories', name: 'index_machine_categories')]
    public function index(): Response
    {
        return $this->render('machine_categories/index.html.twig');
    }

    /*
    #[Route('/machine-categories/excel-data-import', name: 'excel_data_import_machine_categories', methods: ['GET', 'POST'])]
    public function ExcelDataImport(MachineCategoryRepository $machineCategoryRepository): Response
    {
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
            $rows = $this->readExcelRows($filePath);
        } catch (\RuntimeException $exception) {
            return $this->response(false, [
                'data' => [
                    'error' => $exception->getMessage(),
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        if ($rows === []) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Az import fajl nem tartalmaz adatot.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $header = array_map(static fn ($value): string => trim((string) $value), $rows[0]);
        $categoryColumn = array_search('Kategória', $header, true);
        if ($categoryColumn === false) {
            $categoryColumn = array_search('Kategoria', $header, true);
        }

        if ($categoryColumn === false) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Nem talalhato a Kategoria oszlop.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $values = [];
        foreach (array_slice($rows, 1) as $row) {
            $value = trim((string) ($row[$categoryColumn] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }

        $values = array_values(array_unique($values));
        $imported = [];

        foreach ($values as $value) {
            $importdata = [
                'id' => 0,
                'name' => $value,
                'title' => $value,
                'code' => '',
                'status' => '1',
            ];
            echo "<pre>";
            var_export($importdata);
            echo "</pre>";


            $request = new Request([], $importdata);
            $response = $this->save_machine_categories($request, $machineCategoryRepository);
            $responseData = json_decode($response->getContent(), true);

            $imported[] = [
                'value' => $value,
                'success' => is_array($responseData) ? (bool) ($responseData['success'] ?? false) : false,
                'response' => $responseData,
            ];

        }

        return $this->response(true, [
            'data' => [
                'file' => str_replace($projectDir . '/', '', $filePath),
                'values' => $values,
                'imported' => $imported,
                'count' => count($imported),
            ],
        ]);
    }
*/

    #[Route('/machine-categories/add', name: 'index_machine_categories_add')]
    public function index_add(Request $request, MachineCategoryRepository $machineCategoryRepository): Response
    {
        $idRaw = $request->query->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;

        $item = $id > 0 ? $machineCategoryRepository->find($id) : null;

        return $this->response(true, [
            'template' => 'machine_categories/index_add.html.twig',
            'templateData' => [
                'item' => $item,
            ],
            'data' => [],
        ]);
    }

    #[Route('/machine-categories/save-machine-categories', name: 'save_machine_categories', methods: ['POST'])]
    public function save_machine_categories(Request $request, MachineCategoryRepository $machineCategoryRepository): Response
    {
        $postData = [];
        foreach ($request->request->all() as $key => $value) {
            $postData[$key] = is_string($value) ? trim($value) : $value;
        }

        $id = (int) ($postData['id'] ?? 0);
        $machineCategory = $id > 0 ? $machineCategoryRepository->find($id) : null;
        $isNew = $machineCategory === null;

        if ($isNew) {
            $machineCategory = new MachineCategory();
            $machineCategory->setDatetimeAdd(new \DateTimeImmutable());
            $machineCategory->setUidAdd($this->getUser()?->getId());
        }

        $title = (string) ($postData['title'] ?? $postData['name'] ?? '');
        $code = (string) ($postData['code'] ?? '');
        /*
        if ($code === '') {
            $code = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
        }
        */

        $machineCategory
            ->setTitle($title)
            ->setCode($code)
            ->setStatus((string) ($postData['status'] ?? ''));

        $machineCategory->setDatetimeLast(new \DateTimeImmutable());
        $machineCategory->setUidLast($this->getUser()?->getId());

        $machineCategoryRepository->save($machineCategory);

        return $this->response(true, [
            'template' => null,
            'templateData' => [],
            'data' => [
                'id' => $machineCategory->getId(),
                'mode' => $isNew ? 'insert' : 'update',
                'post' => $postData,
            ],
        ]);
    }

    #[Route('/machine-categories/delete-machine-category', name: 'delete_machines_category', methods: ['DELETE'])]
    public function delete_machines_category(Request $request, MachineCategoryRepository $machineCategoryRepository): Response
    {
        $id = $request->request->getInt('id');
        if ($id <= 0) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Missing id.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $machineCategory = $machineCategoryRepository->find($id);
        if (!$machineCategory) {
            return $this->response(false, [
                'data' => [
                    'id' => $id,
                    'error' => 'Machine category not found.',
                ],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $machineCategoryRepository->delete($machineCategory);

        return $this->response(true, [
            'data' => [
                'id' => $id,
                'deleted' => true,
            ],
        ]);
    }

    #[Route('/machine-categories/list', name: 'list_machines_categories', methods: ['GET', 'POST'])]
    public function listMachineCategories(Request $request, MachineCategoryRepository $machineCategoryRepository): Response
    {
        $filtersJson = $request->request->get('filters', '{}');
        $filters = json_decode($filtersJson, true);

        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Invalid filters JSON.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $pageRaw = $request->request->get('page', $request->query->get('page', 1));
        $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;
        $list = $machineCategoryRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        $content = $this->renderView('machine_categories/list_machine_categories.html.twig', [
            'records' => $list['records'],
            'filters' => $filters,
            'page' => $list['page'],
            'totalPages' => $list['totalPages'],
            'totalRecords' => $list['totalRecords'],
            'itemsPerPage' => $list['itemsPerPage'],
        ]);

        return $this->response(true, [
            'content' => $content,
            'data' => [
                'filters' => $filters,
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
            ],
        ]);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readExcelRows(string $filePath): array
    {
        return $this->readExcelRowsWithPhpSpreadsheet($filePath);
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
