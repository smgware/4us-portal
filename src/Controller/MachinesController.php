<?php

namespace App\Controller;

use App\Entity\Machine;
use App\Repository\MachineCategoryRepository;
use App\Repository\MachineRentalRepository;
use App\Repository\MachineRepository;
use App\Repository\ProjectRepository;
use App\Repository\WorksheetRepository;
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

    #[Route('/machines', name: 'index_machines')]
    public function index(MachineCategoryRepository $machineCategoryRepository): Response
    {
        return $this->render('machines/index.html.twig', [
            'machineCategories' => $machineCategoryRepository->findBy([], ['title' => 'ASC']),
        ]);
    }

    #[Route('/machines/projects/select/list', name: 'list_machine_project_select_list', methods: ['POST'])]
    public function listMachineProjectSelectList(
        Request $request,
        ProjectRepository $projectRepository,
    ): Response {
        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen projektkeresési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);
        $records = $projectRepository->findForMachineSelect($search, 20);

        return $this->response(true, [
            'template' => 'machines/select/list_machine_project_select_list.html.twig',
            'templateData' => ['records' => $records],
            'data' => [
                'search' => $search,
                'count' => count($records),
            ],
        ]);
    }

    #[Route('/machines/add', name: 'index_machines_add')]
    public function index_add(
        Request $request,
        MachineRepository $machineRepository,
        MachineCategoryRepository $machineCategoryRepository
    ): Response {
        $idRaw = $request->query->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $item = $id > 0 ? $machineRepository->find($id) : null;

        return $this->response(true, [
            'template' => 'machines/index_add.html.twig',
            'templateData' => [
                'item' => $item,
                'categories' => $machineCategoryRepository->findBy([], ['title' => 'ASC']),
            ],
            'data' => [],
        ]);
    }

    #[Route('/machines/save', name: 'save_machines', methods: ['POST'])]
    public function save_machines(
        Request $request,
        MachineRepository $machineRepository,
        MachineCategoryRepository $machineCategoryRepository,
        ProjectRepository $projectRepository
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

        $projectIdRaw = $postData['project_id'] ?? '';
        $project = null;
        if ($projectIdRaw !== '') {
            if (!is_numeric($projectIdRaw) || (int) $projectIdRaw <= 0) {
                return $this->response(false, [
                    'data' => [
                        'error' => 'Érvénytelen projekt azonosító.',
                    ],
                ], 'json', Response::HTTP_BAD_REQUEST);
            }

            $project = $projectRepository->find((int) $projectIdRaw);
            if (!$project) {
                return $this->response(false, [
                    'data' => [
                        'error' => 'A kiválasztott projekt nem található.',
                    ],
                ], 'json', Response::HTTP_BAD_REQUEST);
            }
        }

        $code = (string) ($postData['code'] ?? '');
        /*
        if ($code === '') {
            $code = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
        }
        */

        $data = $postData['data'] ?? [];

        $machine
            ->setTitle($title)
            ->setCode($code)
            ->setData($data)
            ->setProject($project)
            ->setMachineCategory($machineCategory)
            ->setStatus((string) ($postData['status'] ?? '1'));

        $machine->setDatetimeLast(new \DateTimeImmutable());
        $machine->setUidLast($this->getUser()?->getId());

        $machineRepository->save($machine);

        return $this->response(true, [
            'data' => [
                'id' => $machine->getId(),
                'mode' => $isNew ? 'insert' : 'update',
                'project_id' => $machine->getProject()?->getId(),
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
        WorksheetRepository $worksheetRepository
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

        $machineRentals = $machineRentalRepository->findInfoByMachine($machine, 10);
        $openMachineRental = null;
        foreach ($machineRentals as $machineRental) {
            if ($machineRental['datetime_rental_end'] === null) {
                $openMachineRental = $machineRental;
                break;
            }
        }

        return $this->response(true, [
            'template' => 'machines/index_info.html.twig',
            'templateData' => [
                'machine' => $machine,
                'open_machine_rental' => $openMachineRental,
                'worksheets_error_reporting' => $worksheetRepository->findInfoByMachine($machine, ['ERROR_REPORTING', 'ERROR_REPORT'], 10),
                'worksheets_machine_return' => $worksheetRepository->findInfoByMachine($machine, ['MACHINE_RETURN'], 10),
                'worksheets_handover' => $worksheetRepository->findInfoByMachine($machine, ['MACHINE_HANDOVER'], 10),
                'machine_rentals' => $machineRentals,
            ],
            'data' => [],
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

}
