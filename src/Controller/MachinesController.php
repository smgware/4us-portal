<?php

namespace App\Controller;

use App\Entity\Machine;
use App\Repository\CompanySiteRepository;
use App\Repository\MachineCategoryRepository;
use App\Repository\MachineRentalRepository;
use App\Repository\MachineRepository;
use App\Repository\WorksheetRepository;
use App\Repository\WorksheetTypeRepository;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MachinesController extends BaseController
{
    private const ITEMS_PER_PAGE = 20;
    private const INFO_ITEMS_PER_PAGE = 5;
    private const INFO_WORKSHEET_TYPE_CODES = [
        'MACHINE_HANDOVER',
        'MACHINE_RETURN',
        'ERROR_REPORTING',
        'ERROR_REPORT',
        'MACHINE_MOVE_BETWEEN_LOCATIONS',
        'MACHINE_DISPOSAL',
    ];

    #[Route('/machines', name: 'index_machines')]
    public function index(
        MachineCategoryRepository $machineCategoryRepository,
        CompanySiteRepository $companySiteRepository,
    ): Response {
        return $this->render('machines/index.html.twig', [
            'machineCategories' => $machineCategoryRepository->findBy([], ['title' => 'ASC']),
            'companySites' => $companySiteRepository->findBy([], ['status' => 'DESC', 'title' => 'ASC']),
        ]);
    }

    #[Route('/machines/add', name: 'index_machines_add')]
    public function index_add(
        Request $request,
        MachineRepository $machineRepository,
        MachineCategoryRepository $machineCategoryRepository,
        CompanySiteRepository $companySiteRepository,
    ): Response {
        $idRaw = $request->query->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $item = $id > 0 ? $machineRepository->find($id) : null;

        return $this->response(true, [
            'template' => 'machines/index_add.html.twig',
            'templateData' => [
                'item' => $item,
                'categories' => $machineCategoryRepository->findBy([], ['title' => 'ASC']),
                'companySites' => $companySiteRepository->findBy([], ['status' => 'DESC', 'title' => 'ASC']),
            ],
            'data' => [],
        ]);
    }

    #[Route('/machines/save', name: 'save_machines', methods: ['POST'])]
    public function save_machines(
        Request $request,
        MachineRepository $machineRepository,
        MachineCategoryRepository $machineCategoryRepository,
        CompanySiteRepository $companySiteRepository,
    ): Response {
        $postData = [];
        foreach ($request->request->all() as $key => $value) {
            $postData[$key] = is_string($value) ? trim($value) : $value;
        }

        $id = (int) ($postData['id'] ?? 0);
        $machine = $id > 0 ? $machineRepository->find($id) : null;
        $isNew = $machine === null;

        if ($isNew) {
            $machine = new Machine();
            $machine->setDatetimeAdd(new \DateTimeImmutable());
            $machine->setUidAdd($this->getUser()?->getId());
        }

        $title = (string) ($postData['title'] ?? $postData['name'] ?? '');
        if ($title === '') {
            return $this->response(false, [
                'data' => [
                    'error' => 'Missing machine title.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $categoryId = (int) ($postData['machine_category_id'] ?? 0);
        $machineCategory = $categoryId > 0 ? $machineCategoryRepository->find($categoryId) : null;
        if (!$machineCategory) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Missing machine category.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $companySiteId = (int) ($postData['company_site_id'] ?? 0);
        $companySite = $companySiteId > 0 ? $companySiteRepository->find($companySiteId) : null;
        if (!$companySite) {
            return $this->response(false, [
                'data' => [
                    'error' => 'A telephely megadása kötelező.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $code = (string) ($postData['code'] ?? '');
        /*
        if ($code === '') {
            $code = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
        }
        */

        $submittedData = $postData['data'] ?? [];
        if (!is_array($submittedData)) {
            return $this->response(false, [
                'data' => [
                    'error' => 'A gép kiegészítő adatai érvénytelenek.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $data = $machine->getData() ?? [];
        $data['sku'] = trim((string) ($submittedData['sku'] ?? ''));
        $data['year'] = trim((string) ($submittedData['year'] ?? ''));

        $machine
            ->setTitle($title)
            ->setCode($code)
            ->setData($data)
            ->setMachineCategory($machineCategory)
            ->setCompanySite($companySite)
            ->setStatus((string) ($postData['status'] ?? '1'));

        $machine->setDatetimeLast(new \DateTimeImmutable());
        $machine->setUidLast($this->getUser()?->getId());

        $machineRepository->save($machine);

        return $this->response(true, [
            'data' => [
                'id' => $machine->getId(),
                'mode' => $isNew ? 'insert' : 'update',
                'post' => $postData,
            ],
        ]);
    }

    #[Route('/machines/delete', name: 'delete_machines', methods: ['DELETE'])]
    public function delete_machines(Request $request, MachineRepository $machineRepository): Response
    {
        $idRaw = $request->request->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;

        if ($id <= 0) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Missing id.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $machine = $machineRepository->find($id);
        if (!$machine) {
            return $this->response(false, [
                'data' => [
                    'id' => $id,
                    'error' => 'Machine not found.',
                ],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $machineRepository->delete($machine);

        return $this->response(true, [
            'data' => [
                'id' => $id,
                'deleted' => true,
            ],
        ]);
    }

    #[Route('/machines/list', name: 'list_machines', methods: ['GET', 'POST'])]
    public function list_machines(Request $request, MachineRepository $machineRepository): Response
    {
        $filtersJson = $request->request->get('filters', '{}');
        $decodedFilters = json_decode($filtersJson, true);

        if (!is_array($decodedFilters)) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Invalid filters JSON.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $filters = [
            'search' => (string) ($decodedFilters['search'] ?? ''),
            'type' => (string) ($decodedFilters['type'] ?? ''),
            'machines_category' => $decodedFilters['machines_category']
                ?? $decodedFilters['machine_category_id']
                ?? '',
            'company_site_id' => $decodedFilters['company_site_id'] ?? '',
        ];

        $pageRaw = $request->request->get('page', $request->query->get('page', 1));
        $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;
        $list = $machineRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        $content = $this->renderView('machines/list_machines.html.twig', [
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

    #[Route('/machines/info', name: 'index_machine_info', methods: ['POST'])]
    public function index_machine_info(
        Request $request,
        MachineRepository $machineRepository,
        MachineRentalRepository $machineRentalRepository,
        WorksheetTypeRepository $worksheetTypeRepository,
        CompanySiteRepository $companySiteRepository,
    ): Response
    {
        $idRaw = $request->request->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $machine = $id > 0 ? $machineRepository->find($id) : null;

        if (!$machine) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Machine not found.',
                ],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        return $this->response(true, [
            'template' => 'machines/index_info.html.twig',
            'templateData' => [
                'machine' => $machine,
                'open_machine_rental' => $machineRentalRepository->findCurrentInfoByMachine($machine),
                'worksheet_filter_types' => $this->findInfoWorksheetTypes($worksheetTypeRepository),
                'company_sites' => $companySiteRepository->findBy([], ['status' => 'DESC', 'title' => 'ASC']),
                'max_attachment_file_size' => MachinesAttachmentsController::maximumFileSize(),
            ],
            'data' => ['machine_id' => $machine->getId()],
        ]);
    }

    #[Route('/machines/info/company-site', name: 'update_machine_company_site', methods: ['POST'])]
    public function updateCompanySite(
        Request $request,
        MachineRepository $machineRepository,
        CompanySiteRepository $companySiteRepository,
    ): Response
    {
        $machine = $this->findRequestedMachine($request, $machineRepository);
        if (!$machine) {
            return $this->machineNotFoundResponse();
        }

        $companySiteId = $request->request->get('company_site_id');
        $companySite = is_numeric($companySiteId) && (int) $companySiteId > 0
            ? $companySiteRepository->find((int) $companySiteId)
            : null;
        if (!$companySite) {
            return $this->response(false, [
                'data' => ['error' => 'A telephely megadása kötelező.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $machine
            ->setCompanySite($companySite)
            ->setDatetimeLast(new \DateTimeImmutable())
            ->setUidLast($this->getUser()?->getId());
        $machineRepository->save($machine);

        return $this->response(true, [
            'data' => [
                'machine_id' => $machine->getId(),
                'company_site_id' => $companySite->getId(),
                'company_site_title' => $companySite->getTitle(),
            ],
        ]);
    }

    #[Route('/machines/info/rentals/list', name: 'list_machine_info_rentals', methods: ['POST'])]
    public function listInfoRentals(
        Request $request,
        MachineRepository $machineRepository,
        MachineRentalRepository $machineRentalRepository,
    ): Response {
        $machine = $this->findRequestedMachine($request, $machineRepository);
        if (!$machine) {
            return $this->machineNotFoundResponse();
        }

        $filters = $this->decodeFilters($request);
        if ($filters === null) {
            return $this->invalidInfoFiltersResponse();
        }

        $page = max(1, (int) $request->request->get('page', 1));
        $list = $machineRentalRepository->findInfoPageByMachine(
            $machine,
            $filters,
            $page,
            self::INFO_ITEMS_PER_PAGE,
        );

        return $this->response(true, [
            'template' => 'machines/_info_rentals_list.html.twig',
            'templateData' => $list,
            'data' => [
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
            ],
        ]);
    }

    #[Route('/machines/info/worksheets/list', name: 'list_machine_info_worksheets', methods: ['POST'])]
    public function listInfoWorksheets(
        Request $request,
        MachineRepository $machineRepository,
        WorksheetRepository $worksheetRepository,
    ): Response {
        $machine = $this->findRequestedMachine($request, $machineRepository);
        if (!$machine) {
            return $this->machineNotFoundResponse();
        }

        $filters = $this->decodeFilters($request);
        if ($filters === null) {
            return $this->invalidInfoFiltersResponse();
        }

        $page = max(1, (int) $request->request->get('page', 1));
        $list = $worksheetRepository->findInfoPageByMachine(
            $machine,
            self::INFO_WORKSHEET_TYPE_CODES,
            $filters,
            $page,
            self::INFO_ITEMS_PER_PAGE,
        );

        return $this->response(true, [
            'template' => 'machines/_info_worksheets_list.html.twig',
            'templateData' => $list,
            'data' => [
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
            ],
        ]);
    }

    #[Route('/machines/print-error-reporting-qr/{id}', name: 'print_qr_code_error_reporting', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function printErrorReportingQr(
        int $id,
        MachineRepository $machineRepository,
    ): Response {
        $machine = $machineRepository->find($id);
        if (!$machine) {
            throw $this->createNotFoundException('Machine not found.');
        }

        $machineCode = trim((string) $machine->getCode());
        if ($machineCode === '') {
            throw $this->createNotFoundException('A gépnek nincs gépkódja.');
        }

        $errorReportingUrl = $this->generateUrl(
            'error_reporting_index_machine',
            ['machineCode' => $machineCode],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $qrCode = Builder::create()
            ->writer(new SvgWriter())
            ->data($errorReportingUrl)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(1000)
            ->margin(20)
            ->build();

        return $this->render('machines/print_error_reporting_qr.html.twig', [
            'machine' => $machine,
            'error_reporting_url' => $errorReportingUrl,
            'qr_code_data_uri' => $qrCode->getDataUri(),
        ]);
    }

    private function findRequestedMachine(Request $request, MachineRepository $machineRepository): ?Machine
    {
        $id = $request->request->get('machine_id', $request->request->get('id'));

        return is_numeric($id) && (int) $id > 0 ? $machineRepository->find((int) $id) : null;
    }

    private function decodeFilters(Request $request): ?array
    {
        $filters = json_decode((string) $request->request->get('filters', '{}'), true);

        return is_array($filters) ? $filters : null;
    }

    private function machineNotFoundResponse(): Response
    {
        return $this->response(false, [
            'data' => ['error' => 'A gép nem található.'],
        ], 'json', Response::HTTP_NOT_FOUND);
    }

    private function invalidInfoFiltersResponse(): Response
    {
        return $this->response(false, [
            'data' => ['error' => 'Érvénytelen szűrési adatok.'],
        ], 'json', Response::HTTP_BAD_REQUEST);
    }

    private function findInfoWorksheetTypes(WorksheetTypeRepository $worksheetTypeRepository): array
    {
        $typesByCode = [];
        foreach ($worksheetTypeRepository->findBy(['code' => self::INFO_WORKSHEET_TYPE_CODES]) as $worksheetType) {
            $typesByCode[$worksheetType->getCode()] = $worksheetType;
        }

        $types = [];
        foreach (self::INFO_WORKSHEET_TYPE_CODES as $code) {
            if (isset($typesByCode[$code])) {
                $types[] = $typesByCode[$code];
            }
        }

        return $types;
    }

}
