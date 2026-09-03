<?php

namespace App\Controller;

use App\Entity\Worksheet;
use App\Entity\WorksheetAttachment;
use App\Entity\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Repository\MachineRepository;
use App\Repository\PartnerRepository;
use App\Repository\WorksheetAttachmentRepository;
use App\Repository\WorksheetRepository;
use App\Repository\WorksheetStatusTypeRepository;
use App\Repository\WorksheetTypeRepository;
use App\Service\WorksheetNotificationService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WorksheetController extends BaseController
{
    private const ITEMS_PER_PAGE = 20;
    private const ERROR_REPORT_TYPE_CODES = ['ERROR_REPORT', 'ERROR_REPORTING'];
    private const CLOSED_STATUS_CODES = ['CLOSED', 'RETURNED', 'REPAIRED_ISSUED'];
    private const WORKSHEET_ATTACHMENTS_DIRECTORY = 'uploads/worksheets';
    private const MAX_ATTACHMENT_FILE_SIZE = 268435456; // 256 MB

    #[Route('/worksheets', name: 'index_worksheets')]
    public function index(WorksheetTypeRepository $worksheetTypeRepository): Response
    {
        return $this->render('worksheets/index.html.twig', [
            'worksheetTypes' => $worksheetTypeRepository->findBy([], ['title' => 'ASC']),
        ]);
    }

    #[Route('/worksheets/count-to-index', name: 'getdata_worksheets_count_to_index', methods: ['POST'])]
    public function getDataWorksheetsCountToIndex(WorksheetRepository $worksheetRepository): Response
    {
        $queryBuilder = $worksheetRepository->createQueryBuilder('worksheet')
            ->select([
                'worksheetType.code AS code',
                'COUNT(worksheet.id) AS nr_of_worksheet',
            ])
            ->innerJoin('worksheet.worksheetType', 'worksheetType')
            ->groupBy('worksheetType.code');

        $canViewAllWorksheetTypes = $this->isGranted('ROLE_MASTER')
            || $this->isGranted('ROLE_PROJECTMANAGER');

        if (!$canViewAllWorksheetTypes) {
            if ($this->isGranted('ROLE_SERVICE')) {
                $queryBuilder
                    ->andWhere('worksheetType.code IN (:worksheetTypeCodes)')
                    ->setParameter('worksheetTypeCodes', self::ERROR_REPORT_TYPE_CODES);
            } else {
                $queryBuilder->andWhere('1 = 0');
            }
        }

        $worksheetCounts = [];
        foreach ($queryBuilder->getQuery()->getArrayResult() as $record) {
            $code = trim((string) ($record['code'] ?? ''));
            if ($code !== '') {
                $worksheetCounts[$code] = (int) ($record['nr_of_worksheet'] ?? 0);
            }
        }

        return $this->response(true, [
            'data' => [
                'worksheet_counts' => $worksheetCounts,
            ],
        ]);
    }


    #[Route('/worksheets/add', name: 'index_worksheets_add', methods: ['POST'])]
    public function index_worksheets_add(
        Request $request,
        WorksheetRepository $worksheetRepository,
        WorksheetTypeRepository $worksheetTypeRepository,
    ): Response {
        $idRaw = $request->request->get('id', $request->request->get('worksheet_id', ''));

        if (!is_numeric($idRaw)) {
            $rentalIdRaw = $request->request->get('rental_id', '');
            $worksheetTypeCode = trim((string) $request->request->get('code_worksheet_type', ''));

            if (is_numeric($rentalIdRaw) && $worksheetTypeCode !== '') {
                $worksheetType = $worksheetTypeRepository->findOneBy(['code' => $worksheetTypeCode]);
                $worksheet = $worksheetType ? $worksheetRepository->findOneBy([
                    'machineRental' => (int) $rentalIdRaw,
                    'worksheetType' => $worksheetType,
                ]) : null;

                if ($worksheet) {
                    $idRaw = $worksheet->getId();
                }
            }
        }
        return $this->response(true, [
            'template' => 'worksheets/index_add.html.twig',
            'templateData' => [
                'id' => $idRaw
            ],
            'data' => [],
        ]);

    }


    #[Route('/worksheets/datacontent', name: 'index_worksheet_add_main', methods: ['POST'])]
    public function index_worksheet_add_main(
        Request $request,
        WorksheetRepository $worksheetRepository,
        WorksheetTypeRepository $worksheetTypeRepository,
        WorksheetStatusTypeRepository $worksheetStatusTypeRepository,
        WorksheetAttachmentRepository $worksheetAttachmentRepository,
        MachineRepository $machineRepository,
    ): Response
    {
        $idRaw = $request->request->get('id', $request->request->get('worksheet_id', ''));
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;

        $item = $id > 0 ? $worksheetRepository->find($id) : null;
        $isNew = $id <= 0;
        $isServiceOnly = $this->isGranted('ROLE_SERVICE')
            && !$this->isGranted('ROLE_MASTER')
            && !$this->isGranted('ROLE_PROJECTMANAGER');
        $worksheetTypes = $isServiceOnly
            ? $worksheetTypeRepository->findBy(['code' => self::ERROR_REPORT_TYPE_CODES], ['title' => 'ASC'])
            : $worksheetTypeRepository->findBy([], ['title' => 'ASC']);

        if ($isNew) {
            $defaultWorksheetType = $worksheetTypeRepository->findOneBy(['code' => 'ERROR_REPORT'])
                ?? $worksheetTypeRepository->findOneBy(['code' => 'ERROR_REPORTING'])
                ?? ($worksheetTypes[0] ?? null);

            if ($defaultWorksheetType === null) {
                return $this->response(false, [
                    'data' => ['error' => 'missing_worksheet_type'],
                ], 'json', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $defaultWorksheetStatusType = $worksheetStatusTypeRepository->findOneBy([
                'worksheetType' => $defaultWorksheetType,
                'code' => 'NEW',
            ]) ?? $worksheetStatusTypeRepository->findOneBy([
                'worksheetType' => $defaultWorksheetType,
                'code' => 'OPEN',
            ]) ?? $worksheetStatusTypeRepository->findOneBy([
                'worksheetType' => $defaultWorksheetType,
            ], ['id' => 'ASC']);

            if ($defaultWorksheetStatusType === null) {
                return $this->response(false, [
                    'data' => ['error' => 'missing_worksheet_status_type'],
                ], 'json', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $item = (new Worksheet())
                ->setTitle('')
                ->setCode('')
                ->setWorksheetType($defaultWorksheetType)
                ->setData([])
                ->setWorksheetStatusType($defaultWorksheetStatusType);
        } elseif (!$item || ($isServiceOnly && !in_array($item->getWorksheetType()?->getCode(), self::ERROR_REPORT_TYPE_CODES, true))) {
            return $this->response(false, [
                'data' => ['error' => 'notfound'],
            ]);
        }

        $itemData = $item->getData() ?? [];
        $selectedMachineIdRaw = $itemData['machine_id'] ?? $item->getMachineRental()?->getMachine()?->getId();
        $selectedMachine = is_numeric($selectedMachineIdRaw)
            ? $machineRepository->find((int) $selectedMachineIdRaw)
            : null;

        return $this->response(true, [
            'template' => 'worksheets/add/index_main.html.twig',
            'templateData' => [
                'item' => $item,
                'isNew' => $isNew,
                'worksheetTypes' => $worksheetTypes,
                'selectedMachine' => $selectedMachine,
                'conditionImageTypes' => [
                    ['code'=>'front','title'=>'Elölről'],
                    ['code'=>'back','title'=>'Hátulról'],
                    ['code'=>'right','title'=>'Jobbról'],
                    ['code'=>'left','title'=>'Balról'],
                    ['code'=>'service','title'=>'Üzem / KM óra'],
                ]
            ],
            'data' => [
                'options' => [
                    'save_enabled' => $isNew || !in_array($item->getWorksheetStatusType()?->getCode(), self::CLOSED_STATUS_CODES, true),
                    'max_attachment_file_size' => self::MAX_ATTACHMENT_FILE_SIZE,
                ],
                'worksheet' => $this->serializeWorksheet($item),
                'attachments' => []
                /*
                'attachments' => $isNew ? [] : array_map(function (WorksheetAttachment $attachment): array {
                    return $this->serializeAttachment($attachment);
                }, $worksheetAttachmentRepository->findBy(['worksheet' => $item], ['id' => 'DESC'])),
                */
            ],
        ]);
    }

    #[Route('/worksheets/status-types/list', name: 'list_worksheet_status_types_for_worksheet', methods: ['POST'])]
    #[Route('/worksheets/status-types/getdata', name: 'getdata_worksheets_types', methods: ['POST'])]
    public function listWorksheetStatusTypes(
        Request $request,
        WorksheetTypeRepository $worksheetTypeRepository,
        WorksheetStatusTypeRepository $worksheetStatusTypeRepository,
    ): Response {
        $filtersJson = (string) $request->request->get('filters', '{}');
        $filters = json_decode($filtersJson, true);

        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen szűrési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $worksheetTypeIdRaw = $filters['worksheet_type_id'] ?? $request->request->get('worksheet_type_id');
        $worksheetTypeId = is_numeric($worksheetTypeIdRaw) ? (int) $worksheetTypeIdRaw : 0;

        if ($worksheetTypeId <= 0 && ($worksheetTypeIdRaw === null || $worksheetTypeIdRaw === '' || $worksheetTypeIdRaw === 'null')) {
            return $this->response(true, [
                'data' => [
                    'worksheet_type_id' => '',
                    'status_types' => [],
                ],
            ]);
        }

        $worksheetType = $worksheetTypeId > 0 ? $worksheetTypeRepository->find($worksheetTypeId) : null;

        if (!$worksheetType) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap típusa nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $statusTypes = $worksheetStatusTypeRepository->findBy(
            ['worksheetType' => $worksheetType],
            ['id' => 'ASC'],
        );

        return $this->response(true, [
            'data' => [
                'worksheet_type_id' => $worksheetType->getId(),
                'status_types' => array_map(static fn ($statusType): array => [
                    'id' => $statusType->getId(),
                    'code' => $statusType->getCode(),
                    'title' => $statusType->getTitle(),
                ], $statusTypes),
            ],
        ]);
    }

    #[Route('/worksheets/partners/select/list', name: 'list_worksheet_partner_select_list', methods: ['POST'])]
    public function listWorksheetPartnerSelectList(
        Request $request,
        PartnerRepository $partnerRepository,
    ): Response {
        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen partnerkeresési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);
        $records = $partnerRepository->findForWorksheetSelect($search, 20);

        return $this->response(true, [
            'template' => 'worksheets/add/select/list_worksheet_partner_select_list.html.twig',
            'templateData' => ['records' => $records],
            'data' => [
                'search' => $search,
                'count' => count($records),
            ],
        ]);
    }

    #[Route('/worksheets/machines/select/list', name: 'list_worksheet_machine_select_list', methods: ['POST'])]
    public function listWorksheetMachineSelectList(
        Request $request,
        MachineRepository $machineRepository,
    ): Response {
        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen gépkeresési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);
        $records = $machineRepository->findForWorksheetSelect($search, 20);

        return $this->response(true, [
            'template' => 'worksheets/add/select/list_worksheet_machine_select_list.html.twig',
            'templateData' => ['records' => $records],
            'data' => [
                'search' => $search,
                'count' => count($records),
            ],
        ]);
    }


    #[Route('/worksheets/attachments/list', name: 'list_worksheet_attachments_list', methods: ['GET', 'POST'])]
    public function list_worksheet_attachments_list(
        Request $request,
        WorksheetRepository $worksheetRepository,
    ): Response
    {
        $filtersJson = (string) $request->request->get('filters', $request->query->get('filters', '{}'));
        $filters = json_decode($filtersJson, true);
        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen szűrési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $worksheetIdRaw             = $request->request->get('worksheet_id', $request->query->get('worksheet_id', ''));
        $filters['worksheet_id']    = is_numeric($worksheetIdRaw) ? (int) $worksheetIdRaw : 0;

        $pageRaw    = $request->request->get('page', $request->query->get('page', 1));
        $page       = is_numeric($pageRaw) ? (int) $pageRaw : 1;
        $list       = $worksheetRepository->findWorksheetAttachments($filters, $page, 10);

        //dd($list);
        //die();
        return $this->response(true, [
            'template' => 'worksheets/add/attachments/list_worksheet_attachments_list.html.twig',
            'templateData' => [
                'attachments' => $list['records'],
                /*
                'attachments' => array_map(
                    fn (array $record): array => $this->buildAttachmentCardFromRecord($record),
                    $list['records'],
                ),
                */
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
            ],
            'data' => [
                'worksheet_id' => $filters['worksheet_id'],
                'filters' => $filters,
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
            ],
        ], 'json');
    }

    #[Route('/get/attachment', name: 'getdata_attachment', methods: ['GET'])]
    public function getdata_attachment(
        Request $request,
        WorksheetAttachmentRepository $worksheetAttachmentRepository,
    ): Response {
        $id = $request->query->getInt('id');
        $attachment = $id > 0 ? $worksheetAttachmentRepository->find($id) : null;

        if (!$attachment) {
            return new Response('Attachment not found.', Response::HTTP_NOT_FOUND);
        }

        $filePath = $this->resolveAttachmentPath($attachment->getData() ?? []);
        if ($filePath === null) {
            return new Response('Attachment file not found.', Response::HTTP_NOT_FOUND);
        }

        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $fileName = $attachment->getName() ?: basename($filePath);
        $fallbackFileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $fileName) ?: 'attachment';
        $disposition = $request->query->getBoolean('download')
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;

        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', $mimeType);
        $response->setContentDisposition($disposition, $fileName, $fallbackFileName);

        return $response;
    }

    #[Route('/worksheets/signatures/add', name: 'index_worksheet_signature_add')]
    public function index_worksheet_signature_add(): Response
    {
        return $this->response(true, [
            'template' => 'worksheets/add/signatures/index_worksheet_signature_add.html.twig'
        ], 'json');
    }

    #[Route('/worksheets/partners/contacts/list', name: 'list_worksheet_notifications_contacts_list', methods: ['POST'])]
    public function list_worksheet_notifications_contacts_list(
        Request $request,
        WorksheetRepository $worksheetRepository,
    ): Response
    {
        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen szűrési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $pageRaw = $request->request->get('page', $request->query->get('page', 1));
        $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;
        $list = $worksheetRepository->findWorksheetPartnerContacts($filters, $page, 20);

        return $this->response(true, [
            'template' => 'worksheets/add/notifications/list_worksheet_notifications_contacts_list.html.twig',
            'templateData' => [
                'records' => $list['records'],
            ],
            'data' => [
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
            ],
        ], 'json');
    }

    #[Route('/worksheets/save', name: 'save_worksheets', methods: ['POST'])]
    public function saveWorksheets(
        Request $request,
        WorksheetRepository $worksheetRepository,
        WorksheetTypeRepository $worksheetTypeRepository,
        WorksheetStatusTypeRepository $worksheetStatusTypeRepository,
        PartnerRepository $partnerRepository,
        MachineRepository $machineRepository,
        WorksheetNotificationService $worksheetNotificationService,
    ): Response
    {
        $payload = json_decode((string) $request->request->get('data', ''), true);
        if (!is_array($payload) || !is_array($payload['worksheet'] ?? null)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen munkalap adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $worksheetInput = $payload['worksheet'];
        $rawId = $worksheetInput['id'] ?? null;
        $isNew = $rawId === null || $rawId === '';

        if (!$isNew && !is_numeric($rawId)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen munkalap azonosító.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $worksheet = $isNew ? new Worksheet() : $worksheetRepository->find((int) $rawId);
        if (!$worksheet) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $worksheetTypeId = $worksheetInput['worksheet_type_id'] ?? $worksheetInput['worksheet_type'] ?? null;
        $worksheetType = is_numeric($worksheetTypeId)
            ? $worksheetTypeRepository->find((int) $worksheetTypeId)
            : null;

        if (!$worksheetType) {
            return $this->response(false, [
                'data' => ['error' => 'Válassz munkalap típust.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $title = trim((string) ($worksheetInput['title'] ?? $worksheetInput['worksheet_title'] ?? ''));
        if ($title === '') {
            return $this->response(false, [
                'data' => ['error' => 'Add meg a munkalap címét.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $partnerId = $worksheetInput['partner_id'] ?? null;
        $partner = null;
        if ($partnerId !== null && $partnerId !== '') {
            $partner = is_numeric($partnerId) ? $partnerRepository->find((int) $partnerId) : null;
            if (!$partner) {
                return $this->response(false, [
                    'data' => ['error' => 'A kiválasztott partner nem található.'],
                ], 'json', Response::HTTP_BAD_REQUEST);
            }
        }

        $worksheetData = $worksheetInput['data'] ?? [];
        if (!is_array($worksheetData)) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap JSON data mezője érvénytelen.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('machine_id', $worksheetInput)) {
            $worksheetData['machine_id'] = $worksheetInput['machine_id'];
        }

        $machineIdRaw = $worksheetData['machine_id'] ?? null;
        if ($machineIdRaw !== null && $machineIdRaw !== '') {
            $machine = is_numeric($machineIdRaw) ? $machineRepository->find((int) $machineIdRaw) : null;
            if (!$machine) {
                return $this->response(false, [
                    'data' => ['error' => 'A kiválasztott gép nem található.'],
                ], 'json', Response::HTTP_BAD_REQUEST);
            }

            $worksheetData['machine_id'] = $machine->getId();
        } else {
            $worksheetData['machine_id'] = null;
        }

        if (is_array($payload['contacts'] ?? null)) {
            $worksheetData['contacts'] = $payload['contacts'];
        }
        $worksheetData['worksheet_type_code'] = $worksheetType->getCode();

        $worksheetStatusTypeIdRaw = $worksheetInput['worksheet_status_type_id'] ?? null;
        $worksheetStatusTypeId = is_numeric($worksheetStatusTypeIdRaw) ? (int) $worksheetStatusTypeIdRaw : 0;
        $worksheetStatusType = $worksheetStatusTypeId > 0
            ? $worksheetStatusTypeRepository->find($worksheetStatusTypeId)
            : null;

        if (!$worksheetStatusType || $worksheetStatusType->getWorksheetType()?->getId() !== $worksheetType->getId()) {
            return $this->response(false, [
                'data' => ['error' => 'A kiválasztott státusz nem tartozik ehhez a munkalaptípushoz.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $statusCode = (string) $worksheetStatusType->getCode();

        $now = new \DateTimeImmutable();
        if ($isNew) {
            $worksheet
                ->setCode($this->generateWorksheetCode($worksheetRepository))
                ->setDatetimeAdd($now)
                ->setDatetimeOpen($now)
                ->setUidAdd($this->getUser()?->getId());
        }

        $worksheet
            ->setTitle($title)
            ->setWorksheetType($worksheetType)
            ->setWorksheetStatusType($worksheetStatusType)
            ->setPartner($partner)
            ->setData($worksheetData)
            ->setDatetimeLast($now)
            ->setDatetimeClosed(in_array($statusCode, self::CLOSED_STATUS_CODES, true) ? ($worksheet->getDatetimeClosed() ?? $now) : null)
            ->setUidLast($this->getUser()?->getId());

        $worksheetRepository->save($worksheet);
        $notificationResult = $worksheetNotificationService->sendForWorksheet($worksheet, $isNew);

        return $this->response(true, [
            'data' => [
                'id' => $worksheet->getId(),
                'worksheet_id' => $worksheet->getId(),
                'worksheet' => $this->serializeWorksheet($worksheet),
                'notifications' => $notificationResult,
            ]
        ], 'json');
    }

    #[Route('/worksheets/attachments/save', name: 'save_worksheets_attachment', methods: ['POST'])]
    public function saveWorksheetsAttachment(
        Request $request,
        WorksheetRepository $worksheetRepository,
        WorksheetAttachmentRepository $worksheetAttachmentRepository
    ): Response
    {
        $contentLength = (int) $request->server->get('CONTENT_LENGTH', 0);
        if ($contentLength > 0 && $request->request->count() === 0 && $request->files->count() === 0) {
            return $this->response(false, [
                'data' => [
                    'error' => 'A feltöltési kérés túl nagy. A csatolmány legfeljebb 256 MB lehet.',
                    'max_file_size' => self::MAX_ATTACHMENT_FILE_SIZE,
                ],
            ], 'json', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $worksheetId = $request->request->get('worksheet_id');
        if ($worksheetId === null || $worksheetId === '') {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap azonosítója hiányzik.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $worksheet = is_numeric($worksheetId) ? $worksheetRepository->find((int) $worksheetId) : null;
        if (!$worksheet) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $attachmentInput = json_decode((string) $request->request->get('data', '{}'), true);
        if (!is_array($attachmentInput)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen csatolmány adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $attachmentData = is_array($attachmentInput['data'] ?? null) ? $attachmentInput['data'] : [];
        $operation = strtolower(trim((string) $request->request->get(
            'function',
            $attachmentData['function'] ?? $attachmentInput['function'] ?? '',
        )));
        if (!in_array($operation, ['add', 'update', 'delete'], true)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen csatolmány művelet.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $rawAttachmentId = $request->request->get('id');
        $attachment = null;
        if ($operation !== 'add') {
            $attachment = is_numeric($rawAttachmentId)
                ? $worksheetAttachmentRepository->find((int) $rawAttachmentId)
                : null;

            if (!$attachment || $attachment->getWorksheet()?->getId() !== $worksheet->getId()) {
                return $this->response(false, [
                    'data' => ['error' => 'A csatolmány nem található.'],
                ], 'json', Response::HTTP_NOT_FOUND);
            }
        }

        if ($operation === 'delete') {
            $filePath = $this->resolveAttachmentPath($attachment->getData() ?? []);
            if ($filePath !== null && is_file($filePath) && !unlink($filePath)) {
                return $this->response(false, [
                    'data' => ['error' => 'A csatolmány fájlja nem törölhető.'],
                ], 'json', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $worksheetAttachmentRepository->delete($attachment);

            return $this->response(true, [
                'data' => ['id' => (int) $rawAttachmentId, 'deleted' => true],
            ], 'json');
        }

        $file = $request->files->get('file');
        if ($file instanceof UploadedFile && ($file->getSize() ?? 0) > self::MAX_ATTACHMENT_FILE_SIZE) {
            return $this->response(false, [
                'data' => [
                    'error' => 'A csatolmány legfeljebb 256 MB lehet.',
                    'max_file_size' => self::MAX_ATTACHMENT_FILE_SIZE,
                ],
            ], 'json', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }
        if ($operation === 'add' && (!$file instanceof UploadedFile || !$file->isValid())) {
            return $this->response(false, [
                'data' => ['error' => 'A feltöltendő fájl hiányzik vagy hibás.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }
        if ($file instanceof UploadedFile && !$file->isValid()) {
            return $this->response(false, [
                'data' => ['error' => 'A feltöltött fájl hibás.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $clientData = $attachmentData;
        unset(
            $clientData['attachmentType'],
            $clientData['function'],
            $clientData['percentage'],
            $clientData['path'],
            $clientData['filename'],
            $clientData['size'],
            $clientData['type'],
        );

        $oldFilePath = $attachment ? $this->resolveAttachmentPath($attachment->getData() ?? []) : null;
        $storedFile = null;
        if ($file instanceof UploadedFile && $file->isValid()) {
            try {
                $storedFile = $this->storeWorksheetAttachmentFile($file, (string) $worksheet->getCode());
            } catch (\RuntimeException $exception) {
                return $this->response(false, [
                    'data' => ['error' => $exception->getMessage()],
                ], 'json', Response::HTTP_BAD_REQUEST);
            }
        }

        $now = new \DateTimeImmutable();
        if ($operation === 'add') {
            $attachment = (new WorksheetAttachment())
                ->setWorksheet($worksheet)
                ->setDatetimeAdd($now)
                ->setDatetimeOpen($now)
                ->setUidAdd($this->getUser()?->getId())
                ->setStatus('1');
        }

        $recordData = array_replace($attachment->getData() ?? [], $clientData);
        unset($recordData['attachmentType'], $recordData['function'], $recordData['percentage']);
        if ($storedFile !== null) {
            $recordData = array_replace($recordData, [
                'filename' => $storedFile['fileName'],
                'size' => $storedFile['size'],
                'path' => $storedFile['path'],
                'type' => (string) ($file->getClientMimeType() ?? ''),
            ]);
        }

        $name = trim((string) ($attachmentInput['name'] ?? ''));
        if ($name === '') {
            $name = $storedFile['originalName'] ?? $attachment->getName() ?? 'csatolmány';
        }

        $description = trim((string) ($attachmentInput['description'] ?? ''));
        $attachment
            ->setName($name)
            ->setDescription($description !== '' ? $description : null)
            ->setData($recordData)
            ->setDatetimeLast($now)
            ->setUidLast($this->getUser()?->getId());

        $worksheetAttachmentRepository->save($attachment);

        if ($storedFile !== null && $oldFilePath !== null && is_file($oldFilePath)) {
            @unlink($oldFilePath);
        }

        return $this->response(true, [
            'data' => [
                'worksheet_id' => $worksheet->getId(),
                'attachment' => $this->serializeAttachment($attachment),
            ]
        ], 'json');
    }

    #[Route('/worksheets/list', name: 'list_worksheets', methods: ['GET', 'POST'])]
    public function listWorksheets(Request $request, WorksheetRepository $worksheetRepository): Response
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

        $isServiceOnly = $this->isGranted('ROLE_SERVICE')
            && !$this->isGranted('ROLE_MASTER')
            && !$this->isGranted('ROLE_PROJECTMANAGER');

        if ($isServiceOnly) {
            unset($filters['worksheet_type']);
            $filters['allowed_worksheet_type_codes'] = self::ERROR_REPORT_TYPE_CODES;
        }

        $statusTypeIdRaw = $filters['status'] ?? null;
        if (is_numeric($statusTypeIdRaw) && (int) $statusTypeIdRaw > 0) {
            $filters['worksheet_status_type_id'] = (int) $statusTypeIdRaw;
            unset($filters['status']);
        }

        $pageRaw = $request->request->get('page', $request->query->get('page', 1));
        $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;
        $list = isset($filters['worksheet_status_type_id'])
            ? $this->findWorksheetListByStatusType($worksheetRepository, $filters, $page)
            : $worksheetRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        foreach ($list['records'] as $index => $record) {
            $statusCode = (string) ($record['worksheet_status_type_code'] ?? '');

            $list['records'][$index]['status_code'] = match (true) {
                in_array($statusCode, self::CLOSED_STATUS_CODES, true) => 'closed',
                $statusCode === 'UNDER_REPAIR' => 'inprogress',
                default => 'open',
            };
        }

        $content = $this->renderView('worksheets/list_worksheets.html.twig', [
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

    private function findWorksheetListByStatusType(
        WorksheetRepository $worksheetRepository,
        array $filters,
        int $page,
    ): array {
        $page = max(1, $page);
        $statusTypeId = (int) ($filters['worksheet_status_type_id'] ?? 0);

        $queryBuilder = $worksheetRepository->createQueryBuilder('worksheet')
            ->leftJoin('worksheet.worksheetType', 'worksheetType')
            ->leftJoin('worksheet.worksheetStatusType', 'worksheetStatusType')
            ->leftJoin('worksheet.partner', 'partner')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = worksheet.uidAdd')
            ->leftJoin(User::class, 'userEditor', 'WITH', 'userEditor.id = worksheet.uidLast')
            ->andWhere('worksheetStatusType.id = :worksheetStatusTypeId')
            ->setParameter('worksheetStatusTypeId', $statusTypeId);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $queryBuilder
                ->andWhere('worksheet.title LIKE :search OR worksheet.code LIKE :search OR worksheetType.title LIKE :search OR worksheetType.code LIKE :search OR worksheetStatusType.title LIKE :search OR worksheetStatusType.code LIKE :search OR partner.name LIKE :search OR partner.code LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $worksheetTypeId = (int) ($filters['worksheet_type_id'] ?? 0);
        if ($worksheetTypeId > 0) {
            $queryBuilder
                ->andWhere('worksheetType.id = :worksheetTypeId')
                ->setParameter('worksheetTypeId', $worksheetTypeId);
        }

        $allowedWorksheetTypeCodes = $filters['allowed_worksheet_type_codes'] ?? [];
        if (is_array($allowedWorksheetTypeCodes) && $allowedWorksheetTypeCodes !== []) {
            $queryBuilder
                ->andWhere('worksheetType.code IN (:allowedWorksheetTypeCodes)')
                ->setParameter('allowedWorksheetTypeCodes', $allowedWorksheetTypeCodes);
        }

        $dateFrom = $this->parseWorksheetListDateFilter((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== null) {
            $queryBuilder
                ->andWhere('worksheet.datetimeAdd >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        $dateTo = $this->parseWorksheetListDateFilter((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== null) {
            $queryBuilder
                ->andWhere('worksheet.datetimeAdd <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        $company = trim((string) ($filters['company'] ?? ''));
        if ($company !== '') {
            $queryBuilder
                ->andWhere('partner.name LIKE :company OR worksheet.data LIKE :company')
                ->setParameter('company', '%' . $company . '%');
        }

        $countQueryBuilder = clone $queryBuilder;
        $totalRecords = (int) $countQueryBuilder
            ->select('COUNT(worksheet.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / self::ITEMS_PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::ITEMS_PER_PAGE;

        $records = $queryBuilder
            ->select([
                'worksheet.id AS id',
                'worksheet.title AS title',
                'worksheet.code AS code',
                'worksheet.data AS data',
                'worksheet.uidAdd AS uid_add',
                'worksheet.uidLast AS uid_last',
                'worksheet.datetimeAdd AS datetime_add',
                'worksheet.datetimeLast AS datetime_last',
                'worksheet.datetimeOpen AS datetime_open',
                'worksheet.datetimeClosed AS datetime_closed',
                'worksheetStatusType.id AS worksheet_status_type_id',
                'worksheetStatusType.code AS worksheet_status_type_code',
                'worksheetStatusType.title AS worksheet_status_type_title',
                'worksheetType.id AS worksheet_type_id',
                'worksheetType.title AS worksheet_type_title',
                'worksheetType.code AS worksheet_type_code',
                'partner.id AS partner_id',
                'partner.code AS partner_code',
                'partner.name AS partner_name',
                'userOwner.id AS user_owner_id',
                'userOwner.name AS user_owner_name',
                'userOwner.userName AS user_owner_username',
                'userEditor.id AS user_editor_id',
                'userEditor.name AS user_editor_name',
                'userEditor.userName AS user_editor_username',
            ])
            ->orderBy('worksheet.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults(self::ITEMS_PER_PAGE)
            ->getQuery()
            ->getArrayResult();

        return [
            'records' => $records,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'itemsPerPage' => self::ITEMS_PER_PAGE,
        ];
    }

    private function parseWorksheetListDateFilter(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function serializeWorksheet(Worksheet $worksheet): array
    {
        return [
            'id' => $worksheet->getId(),
            'title' => $worksheet->getTitle(),
            'code' => $worksheet->getCode(),
            'worksheet_type_id' => $worksheet->getWorksheetType()?->getId(),
            'partner_id' => $worksheet->getPartner()?->getId(),
            'rental_id' => $worksheet->getMachineRental()?->getId(),
            'data' => $worksheet->getData() ?? [],
            'worksheet_status_type_id' => $worksheet->getWorksheetStatusType()?->getId(),
            'worksheet_status_type_code' => $worksheet->getWorksheetStatusType()?->getCode(),
            'worksheet_status_type_title' => $worksheet->getWorksheetStatusType()?->getTitle(),
            'datetime_add' => $worksheet->getDatetimeAdd()?->format('Y-m-d H:i:s'),
            'datetime_last' => $worksheet->getDatetimeLast()?->format('Y-m-d H:i:s'),
            'datetime_open' => $worksheet->getDatetimeOpen()?->format('Y-m-d H:i:s'),
            'datetime_closed' => $worksheet->getDatetimeClosed()?->format('Y-m-d H:i:s'),
            'temp_data' => [
                'worksheet_type_code' => $worksheet->getWorksheetType()?->getCode(),
            ]
        ];
    }

    private function serializeAttachment(WorksheetAttachment $attachment): array
    {
        return [
            'id' => $attachment->getId(),
            'name' => $attachment->getName(),
            'description' => $attachment->getDescription(),
            'data' => $attachment->getData() ?? [],
            'status' => $attachment->getStatus(),
            'datetime_add' => $attachment->getDatetimeAdd()?->format('Y-m-d H:i:s'),
            'datetime_last' => $attachment->getDatetimeLast()?->format('Y-m-d H:i:s'),
        ];
    }



    private function storeWorksheetAttachmentFile(UploadedFile $file, string $code): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $code)) {
            throw new \RuntimeException('Érvénytelen munkalap kód.');
        }

        $uploadDir = $this->getParameter('kernel.project_dir')
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, self::WORKSHEET_ATTACHMENTS_DIRECTORY)
            . DIRECTORY_SEPARATOR
            . $code;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException('Nem sikerült létrehozni a feltöltési mappát.');
        }

        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize() ?? 0;
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $originalName) ?: 'attachment';
        $fileName = uniqid('', true) . '-' . $safeName;

        $file->move($uploadDir, $fileName);

        return [
            'originalName' => $originalName,
            'fileName' => $fileName,
            'size' => $fileSize,
            'path' => '/' . self::WORKSHEET_ATTACHMENTS_DIRECTORY . '/' . $code . '/' . $fileName,
        ];
    }

    private function resolveAttachmentPath(array $data): ?string
    {
        $relativePath = trim((string) ($data['path'] ?? ''));
        if ($relativePath === '') {
            return null;
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $normalizedRelativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
        $filePath = realpath($projectDir . DIRECTORY_SEPARATOR . $normalizedRelativePath);

        if ($filePath === false) {
            return null;
        }

        $normalizedFilePath = strtolower($filePath);
        $allowedRoots = [
            realpath(
                $projectDir
                . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, self::WORKSHEET_ATTACHMENTS_DIRECTORY)
            ),
        ];

        foreach ($allowedRoots as $allowedRoot) {
            if ($allowedRoot === false) {
                continue;
            }

            $normalizedRoot = strtolower($allowedRoot);
            if ($normalizedFilePath === $normalizedRoot || str_starts_with($normalizedFilePath, $normalizedRoot . DIRECTORY_SEPARATOR)) {
                return $filePath;
            }
        }

        return null;
    }

    private function generateWorksheetCode(WorksheetRepository $worksheetRepository): string
    {
        do {
            $code = 'M' . date('Ym') . '-' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        } while ($worksheetRepository->findOneBy(['code' => $code]) !== null);

        return $code;
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }


    #[Route('/worksheets/pdf/{id}', name: 'index_worksheet_pdf', methods: ['GET'], defaults: ['id' => null])]
    public function index_worksheet_pdf(
        Request $request,
        WorksheetRepository $worksheetRepository,
        WorksheetAttachmentRepository $worksheetAttachmentRepository,
        ?string $id = null,
    ): Response {
        $identifier = trim((string) ($id ?? $request->query->get('id', '')));
        $worksheet = null;

        if ($identifier !== '') {
            $worksheet = is_numeric($identifier)
                ? $worksheetRepository->find((int) $identifier)
                : $worksheetRepository->findOneBy(['code' => $identifier]);
        }

        if (!$worksheet) {
            return new Response('Worksheet not found.', Response::HTTP_NOT_FOUND);
        }

        $html = $this->renderView('worksheets/pdf/index.html.twig', [
            'item' => $worksheet,
            'data' => $worksheet->getData() ?? [],
            'signatures' => $this->normalizePdfSignatures($worksheet->getData()['signatures'] ?? []),
            'image_attachments' => $this->buildPdfImageAttachments(
                $worksheetAttachmentRepository->findBy(['worksheet' => $worksheet], ['id' => 'ASC'])
            ),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $fileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $worksheet->getCode() ?: ('worksheet-' . $worksheet->getId())) . '.pdf';

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $fileName),
        ]);
    }

    private function normalizePdfSignatures(mixed $signatures): array
    {
        if (is_string($signatures)) {
            $decoded = json_decode($signatures, true);
            $signatures = is_array($decoded) ? $decoded : ['handed' => ['image' => $signatures]];
        }

        if (!is_array($signatures)) {
            $signatures = [];
        }

        foreach (['handed', 'received'] as $type) {
            $signature = $signatures[$type] ?? [];

            if (is_string($signature)) {
                $signature = [
                    'name' => '',
                    'image' => $signature,
                ];
            }

            if (!is_array($signature)) {
                $signature = [];
            }

            $image = trim((string) ($signature['image'] ?? $signature['base64'] ?? $signature['data'] ?? ''));
            if ($image !== '' && !str_starts_with($image, 'data:image/')) {
                $image = 'data:image/png;base64,' . $image;
            }

            $signatures[$type] = [
                'name' => trim((string) ($signature['name'] ?? '')),
                'image' => $image,
            ];
        }

        return $signatures;
    }

    /**
     * @param WorksheetAttachment[] $attachments
     */
    private function buildPdfImageAttachments(array $attachments): array
    {
        $items = [];

        foreach ($attachments as $attachment) {
            $path = $this->resolveAttachmentPath($attachment->getData() ?? []);
            if ($path === null || !is_file($path)) {
                continue;
            }

            $mimeType = mime_content_type($path) ?: 'application/octet-stream';
            if (!str_starts_with($mimeType, 'image/')) {
                continue;
            }

            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }

            $items[] = [
                'name' => $attachment->getName() ?: basename($path),
                'src' => sprintf('data:%s;base64,%s', $mimeType, base64_encode($content)),
            ];
        }

        return $items;
    }
}
