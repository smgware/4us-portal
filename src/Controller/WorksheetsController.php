<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Worksheet;
use App\Entity\WorksheetAttachment;
use App\Repository\CompanySiteRepository;
use App\Repository\MachineRepository;
use App\Repository\MachineRentalRepository;
use App\Repository\PartnerContactRepository;
use App\Repository\PartnerRepository;
use App\Repository\UserRepository;
use App\Repository\WorksheetAttachmentRepository;
use App\Repository\WorksheetDescriptionTemplateRepository;
use App\Repository\WorksheetNotificatedContactRepository;
use App\Repository\WorksheetRepository;
use App\Repository\WorksheetStatusTypeRepository;
use App\Repository\WorksheetTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class WorksheetsController extends BaseController
{
    private const ITEMS_PER_PAGE = 20;
    private const ATTACHMENTS_PER_PAGE = 5;
    private const FINAL_STATUS_CODES = ['CLOSED', 'REPAIRED_RETURNED', 'TAKEN_BACK'];
    private const MACHINE_MOVE_WORKSHEET_TYPE_CODE = 'MACHINE_MOVE_BETWEEN_LOCATIONS';
    private const SERVICE_WORKSHEET_TYPE_CODE = 'ERROR_REPORT';
    private const MATERIAL_UNITS = [
        ['name' => 'Darab', 'short' => 'db'],
        ['name' => 'Méter', 'short' => 'm'],
        ['name' => 'Centiméter', 'short' => 'cm'],
        ['name' => 'Milliméter', 'short' => 'mm'],
        ['name' => 'Négyzetméter', 'short' => 'm²'],
        ['name' => 'Köbméter', 'short' => 'm³'],
        ['name' => 'Liter', 'short' => 'l'],
        ['name' => 'Milliliter', 'short' => 'ml'],
        ['name' => 'Kilogramm', 'short' => 'kg'],
        ['name' => 'Gramm', 'short' => 'g'],
        ['name' => 'Csomag', 'short' => 'csom.'],
        ['name' => 'Doboz', 'short' => 'dob.'],
        ['name' => 'Tekercs', 'short' => 'tek.'],
        ['name' => 'Flakon', 'short' => 'flak.'],
        ['name' => 'Tubus', 'short' => 'tub.'],
        ['name' => 'Készlet / szett', 'short' => 'szett'],
        ['name' => 'Pár', 'short' => 'pár'],
        ['name' => 'Lap / ív', 'short' => 'lap'],
    ];

    #[Route('/worksheets', name: 'index_worksheets')]
    public function index(
        WorksheetTypeRepository $worksheetTypeRepository,
        WorksheetStatusTypeRepository $worksheetStatusTypeRepository,
        WorksheetRepository $worksheetRepository,
    ): Response {
        $isServiceUser = $this->isServiceUser();
        $worksheetStatusFilters = [];
        foreach ($worksheetStatusTypeRepository->findBy([], ['title' => 'ASC']) as $worksheetStatusType) {
            $code = trim((string) $worksheetStatusType->getCode());
            if ($code === '') {
                continue;
            }

            if (!isset($worksheetStatusFilters[$code])) {
                $worksheetStatusFilters[$code] = [
                    'code' => $code,
                    'title' => (string) $worksheetStatusType->getTitle(),
                    'worksheet_type_ids' => [],
                ];
            }

            $worksheetTypeId = $worksheetStatusType->getWorksheetType()?->getId();
            if ($worksheetTypeId && !in_array($worksheetTypeId, $worksheetStatusFilters[$code]['worksheet_type_ids'], true)) {
                $worksheetStatusFilters[$code]['worksheet_type_ids'][] = $worksheetTypeId;
            }
        }

        $worksheetTypes = $worksheetTypeRepository->findBy(['status' => '1'], ['title' => 'ASC']);
        if ($isServiceUser) {
            $worksheetTypes = array_values(array_filter(
                $worksheetTypes,
                static fn ($worksheetType): bool => $worksheetType->getCode() === self::SERVICE_WORKSHEET_TYPE_CODE,
            ));
            $serviceWorksheetTypeIds = array_filter(array_map(
                static fn ($worksheetType): ?int => $worksheetType->getId(),
                $worksheetTypes,
            ));
            $worksheetStatusFilters = array_filter(
                $worksheetStatusFilters,
                static fn (array $filter): bool => array_intersect(
                    $filter['worksheet_type_ids'],
                    $serviceWorksheetTypeIds,
                ) !== [],
            );
        }
        $worksheetTypesByCode = [];
        foreach ($worksheetTypes as $worksheetType) {
            $worksheetTypesByCode[(string) $worksheetType->getCode()] = $worksheetType;
        }

        $shortcutDefinitions = [
            ['code' => 'MACHINE_HANDOVER', 'title' => 'Gép átadási jegyzőkönyv', 'icon' => 'bi-arrow-left-right'],
            ['code' => 'ERROR_REPORT', 'title' => 'Munkalap', 'icon' => 'bi-tools'],
            ['code' => 'MACHINE_RETURN', 'title' => 'Gép átvételi jegyzőkönyv', 'icon' => 'bi-arrow-return-left'],
            ['code' => 'MACHINE_MOVE_BETWEEN_LOCATIONS', 'title' => 'Gépmozgatás telephelyek között', 'icon' => 'bi-truck'],
            ['code' => 'MACHINE_DISPOSAL', 'title' => 'Gép selejtezés / Gép végleges visszaadása', 'icon' => 'bi-trash3'],

        ];
        $shortcutCounts = $worksheetRepository->countNewOrOpenByWorksheetTypeCodes(
            array_column($shortcutDefinitions, 'code'),
        );
        $worksheetTypeShortcuts = [];
        foreach ($shortcutDefinitions as $shortcutDefinition) {
            if ($isServiceUser && $shortcutDefinition['code'] !== self::SERVICE_WORKSHEET_TYPE_CODE) {
                continue;
            }
            $worksheetType = $worksheetTypesByCode[$shortcutDefinition['code']] ?? null;
            if (!$worksheetType) {
                continue;
            }

            $worksheetTypeShortcuts[] = [
                ...$shortcutDefinition,
                'id' => $worksheetType->getId(),
                'count' => $shortcutCounts[$shortcutDefinition['code']] ?? 0,
            ];
        }

        return $this->render('worksheets/index.html.twig', [
            'worksheetTypes' => $worksheetTypes,
            'worksheetStatusFilters' => array_values($worksheetStatusFilters),
            'worksheetTypeShortcuts' => $worksheetTypeShortcuts,
        ]);
    }

    #[Route('/worksheets/add', name: 'index_worksheets_add', methods: ['POST'])]
    public function indexWorksheetsAdd(
        Request $request,
        WorksheetRepository $worksheetRepository,
        WorksheetTypeRepository $worksheetTypeRepository,
    ): Response {
        $worksheetId = $this->positiveInt(
            $request->request->get('worksheet_id', $request->request->get('id')),
        );
        $machineRentalId = $this->positiveInt($request->request->get('rental_id'));
        $worksheetTypeCode = trim((string) $request->request->get('code_worksheet_type', ''));

        $worksheet = $worksheetId ? $worksheetRepository->find($worksheetId) : null;
        if ($worksheetId && !$worksheet) {
            return $this->response(false, [
                'data' => ['error' => 'notfound'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $worksheetType = $worksheetTypeCode !== ''
            ? $worksheetTypeRepository->findOneBy(['code' => $worksheetTypeCode])
            : null;

        if ($this->isServiceUser() && (
            ($worksheet && $worksheet->getWorksheetType()?->getCode() !== self::SERVICE_WORKSHEET_TYPE_CODE)
            || ($worksheetTypeCode !== '' && $worksheetTypeCode !== self::SERVICE_WORKSHEET_TYPE_CODE)
        )) {
            return $this->serviceWorksheetAccessDenied();
        }

        if (!$worksheet && $machineRentalId && $worksheetType) {
            $worksheet = $worksheetRepository->findOneByRentalAndType($machineRentalId, $worksheetType);
            $worksheetId = $worksheet?->getId();
        }

        return $this->response(true, [
            'template' => 'worksheets/index_worksheets_add.html.twig',
            'templateData' => [
                'id' => $worksheetId,
                'rental_id' => $machineRentalId,
                'code_worksheet_type' => $worksheetTypeCode,
            ],
        ]);
    }

    #[Route('/worksheets/add_main', name: 'index_worksheet_add_main', methods: ['POST'])]
    public function indexWorksheetAddMain(
        Request $request,
        WorksheetRepository $worksheetRepository,
        WorksheetAttachmentRepository $worksheetAttachmentRepository,
        WorksheetDescriptionTemplateRepository $worksheetDescriptionTemplateRepository,
        WorksheetTypeRepository $worksheetTypeRepository,
        WorksheetStatusTypeRepository $worksheetStatusTypeRepository,
        MachineRepository $machineRepository,
        MachineRentalRepository $machineRentalRepository,
        CompanySiteRepository $companySiteRepository,
        PartnerContactRepository $partnerContactRepository,
        WorksheetNotificatedContactRepository $worksheetNotificatedContactRepository,
        UserRepository $userRepository,
    ): Response {
        $worksheetId = $this->positiveInt($request->request->get('id'));
        $worksheet = $worksheetId ? $worksheetRepository->find($worksheetId) : null;
        if ($worksheetId && !$worksheet) {
            return $this->response(false, [
                'data' => ['error' => 'notfound'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $worksheetTypeCode = trim((string) $request->request->get('code_worksheet_type', ''));
        $selectedWorksheetType = $worksheet?->getWorksheetType();
        if (!$selectedWorksheetType && $worksheetTypeCode !== '') {
            $selectedWorksheetType = $worksheetTypeRepository->findOneBy(['code' => $worksheetTypeCode]);
        }

        if ($this->isServiceUser() && (
            ($worksheet && $worksheet->getWorksheetType()?->getCode() !== self::SERVICE_WORKSHEET_TYPE_CODE)
            || ($worksheetTypeCode !== '' && $worksheetTypeCode !== self::SERVICE_WORKSHEET_TYPE_CODE)
        )) {
            return $this->serviceWorksheetAccessDenied();
        }

        $conditionImages = [];
        if ($worksheet) {
            $conditionImages = $this->serializeAttachments(
                array_values($worksheetAttachmentRepository->findConditionImages($worksheet)),
                $userRepository,
            );
        }

        $conditionImagesByType = [];
        foreach ($conditionImages as $conditionImage) {
            $conditionType = trim((string) ($conditionImage['conditionType'] ?? ''));
            if ($conditionType !== '') {
                $conditionImagesByType[$conditionType] = $conditionImage;
            }
        }

        $worksheetTypes = $worksheetTypeRepository->findBy(['status' => '1'], ['title' => 'ASC']);
        if ($this->isServiceUser()) {
            $worksheetTypes = array_values(array_filter(
                $worksheetTypes,
                static fn ($worksheetType): bool => $worksheetType->getCode() === self::SERVICE_WORKSHEET_TYPE_CODE,
            ));
        }
        if ($worksheet?->getWorksheetType() && !in_array($worksheet->getWorksheetType(), $worksheetTypes, true)) {
            $worksheetTypes[] = $worksheet->getWorksheetType();
        }
        $worksheetStatusTypes = $worksheetStatusTypeRepository->findBy([], ['title' => 'ASC']);
        if ($this->isServiceUser()) {
            $worksheetStatusTypes = array_values(array_filter(
                $worksheetStatusTypes,
                static fn ($worksheetStatusType): bool => $worksheetStatusType->getWorksheetType()?->getCode() === self::SERVICE_WORKSHEET_TYPE_CODE,
            ));
        }

        $notificationContacts = [];
        $selectedNotificationContactIds = [];
        if ($worksheet?->getPartner()) {
            $notificationContacts = $partnerContactRepository->findBy(
                ['partner' => $worksheet->getPartner()],
                ['defaultContact' => 'DESC', 'name' => 'ASC', 'id' => 'ASC'],
            );
            $selectedNotificationContactIds = $worksheetNotificatedContactRepository->findContactIds($worksheet);
        }
        $lastEditorName = null;
        if ($worksheet?->getUidLast()) {
            $lastEditor = $userRepository->find($worksheet->getUidLast());
            $lastEditorName = $lastEditor?->getName() ?: $lastEditor?->getUserName();
        }
        $worksheetData = $worksheet?->getData() ?? [];
        $selectedMachine = $worksheet?->getMachineId()
            ? $machineRepository->find($worksheet->getMachineId())
            : null;
        $partnerProject = $worksheet?->getPartner()
            ? $machineRentalRepository->findLatestByPartner($worksheet->getPartner())?->getProject()
            : null;
        $materialsUsed = $this->normalizeMaterialsUsed($worksheetData['materials_used'] ?? []);
        $saveEnabled = !$this->isFinalizedWorksheet($worksheet);

        return $this->response(true, [
            'template' => 'worksheets/index_worksheet_add_main.html.twig',
            'templateData' => [
                'item' => $worksheet,
                'worksheetTypes' => $worksheetTypes,
                'worksheetStatusTypes' => $worksheetStatusTypes,
                'worksheetDescriptionTemplates' => $worksheetDescriptionTemplateRepository->findBy(
                    ['status' => '1'],
                    ['title' => 'ASC'],
                ),
                'selectedMachine' => $selectedMachine,
                'partnerProject' => $partnerProject,
                'companySites' => $companySiteRepository->findBy([], ['status' => 'DESC', 'title' => 'ASC']),
                'notificationPartner' => $worksheet?->getPartner(),
                'notificationContacts' => $notificationContacts,
                'selectedNotificationContactIds' => $selectedNotificationContactIds,
                'selectedWorksheetType' => $selectedWorksheetType,
                'rentalId' => $this->positiveInt($request->request->get('rental_id')),
                'conditionImages' => $conditionImagesByType,
                'lastEditorName' => $lastEditorName,
            ],
            'data' => [
                'worksheet' => $worksheet ? $this->serializeWorksheet($worksheet) : null,
                'condition_images' => $conditionImages,
                'notification' => [
                    'partner_id' => $worksheet?->getPartner()?->getId(),
                    'selected_contact_ids' => $selectedNotificationContactIds,
                    'has_saved_contacts' => $selectedNotificationContactIds !== [],
                ],
                'partner_project' => $this->serializePartnerProject($partnerProject),
                'materials_used' => $materialsUsed,
                'material_units' => self::MATERIAL_UNITS,
                'options' => ['save_enabled' => $saveEnabled],
            ],
        ]);
    }

    #[Route('/worksheets/partners/select/list', name: 'list_worksheet_partner_select_list', methods: ['POST'])]
    public function listPartnerOptions(Request $request, PartnerRepository $partnerRepository): Response
    {
        $filters = $this->decodeComboboxFilters($request);
        if ($filters === null) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen partnerkeresési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);
        $records = $partnerRepository->findForWorksheetSelect($search, 20);
        $items = array_map(static function (array $record): array {
            $active = (string) ($record['status'] ?? '') === '1';
            $meta = array_filter([
                trim((string) ($record['code'] ?? '')),
                ($record['tax_number'] ?? null) ? 'Adószám: ' . $record['tax_number'] : null,
                trim(implode(' ', array_filter([
                    (string) ($record['city'] ?? ''),
                    (string) ($record['address'] ?? ''),
                ]))),
            ]);

            return [
                'id' => (int) $record['id'],
                'label' => (string) $record['name'],
                'meta' => implode(' · ', $meta),
                'selectable' => $active,
                'badge' => $active ? null : 'Inaktív',
                'disabledReason' => $active ? null : 'A partner nem választható ki, mert inaktív.',
            ];
        }, $records);

        return $this->response(true, [
            'data' => [
                'items' => $items,
                'search' => $search,
                'count' => count($items),
            ],
        ]);
    }

    #[Route('/worksheets/machines/select/list', name: 'list_worksheet_machine_select_list', methods: ['POST'])]
    public function listMachineOptions(Request $request, MachineRepository $machineRepository): Response
    {
        $filters = $this->decodeComboboxFilters($request);
        if ($filters === null) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen gépkeresési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);
        $records = $machineRepository->findForWorksheetSelect($search, 20);
        $items = array_map(static function (array $record): array {
            $active = (string) ($record['status'] ?? '') === '1';
            $category = trim((string) ($record['category_title'] ?? ''));
            if ($category !== '' && !empty($record['category_code'])) {
                $category .= ' (' . $record['category_code'] . ')';
            }
            $companySite = trim((string) ($record['company_site_title'] ?? ''));
            if ($companySite !== '' && !empty($record['company_site_code'])) {
                $companySite .= ' (' . $record['company_site_code'] . ')';
            }
            $meta = array_filter([
                trim((string) ($record['code'] ?? '')),
                $category,
                $companySite,
            ]);

            return [
                'id' => (int) $record['id'],
                'label' => (string) $record['title'],
                'meta' => implode(' · ', $meta),
                'selectable' => $active,
                'badge' => $active ? null : 'Inaktív',
                'disabledReason' => $active ? null : 'A gép nem választható ki, mert inaktív.',
            ];
        }, $records);

        return $this->response(true, [
            'data' => [
                'items' => $items,
                'search' => $search,
                'count' => count($items),
            ],
        ]);
    }

    #[Route('/worksheets/notification-contacts', name: 'list_worksheet_notification_contacts', methods: ['POST'])]
    public function listWorksheetNotificationContacts(
        Request $request,
        WorksheetRepository $worksheetRepository,
        PartnerRepository $partnerRepository,
        MachineRentalRepository $machineRentalRepository,
        PartnerContactRepository $partnerContactRepository,
        WorksheetNotificatedContactRepository $worksheetNotificatedContactRepository,
    ): Response {
        $partnerId = $this->positiveInt($request->request->get('partner_id'));
        $worksheetId = $this->positiveInt($request->request->get('worksheet_id'));
        $worksheet = $worksheetId ? $worksheetRepository->find($worksheetId) : null;

        if ($worksheetId && !$worksheet) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        if (!$partnerId) {
            return $this->response(true, [
                'template' => 'worksheets/_notification_contacts.html.twig',
                'templateData' => [
                    'partner' => null,
                    'contacts' => [],
                    'selectedContactIds' => [],
                ],
                'data' => [
                    'partner_id' => null,
                    'selected_contact_ids' => [],
                    'has_saved_contacts' => false,
                    'partner_project' => null,
                ],
            ]);
        }

        $partner = $partnerRepository->find($partnerId);
        if (!$partner) {
            return $this->response(false, [
                'data' => ['error' => 'A kiválasztott partner nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $selectedContactIds = [];
        if ($worksheet?->getPartner()?->getId() === $partner->getId()) {
            $selectedContactIds = $worksheetNotificatedContactRepository->findContactIds($worksheet);
        }

        $contacts = $partnerContactRepository->findBy(
            ['partner' => $partner],
            ['defaultContact' => 'DESC', 'name' => 'ASC', 'id' => 'ASC'],
        );
        $partnerProject = $machineRentalRepository->findLatestByPartner($partner)?->getProject();

        return $this->response(true, [
            'template' => 'worksheets/_notification_contacts.html.twig',
            'templateData' => [
                'partner' => $partner,
                'contacts' => $contacts,
                'selectedContactIds' => $selectedContactIds,
            ],
            'data' => [
                'partner_id' => $partner->getId(),
                'selected_contact_ids' => $selectedContactIds,
                'has_saved_contacts' => $selectedContactIds !== [],
                'partner_project' => $this->serializePartnerProject($partnerProject),
            ],
        ]);
    }

    #[Route('/worksheets/save', name: 'save_worksheet', methods: ['POST'])]
    public function saveWorksheet(
        Request $request,
        WorksheetRepository $worksheetRepository,
        WorksheetTypeRepository $worksheetTypeRepository,
        WorksheetStatusTypeRepository $worksheetStatusTypeRepository,
        PartnerRepository $partnerRepository,
        PartnerContactRepository $partnerContactRepository,
        WorksheetNotificatedContactRepository $worksheetNotificatedContactRepository,
        MachineRepository $machineRepository,
        CompanySiteRepository $companySiteRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $id = $this->positiveInt($request->request->get('id'));
        $worksheet = $id ? $worksheetRepository->find($id) : null;
        if ($id && !$worksheet) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }
        if ($this->isFinalizedWorksheet($worksheet)) {
            return $this->response(false, [
                'data' => ['error' => 'A lezárt munkalap már nem szerkeszthető.'],
            ], 'json', Response::HTTP_CONFLICT);
        }

        $title = trim((string) $request->request->get('title', ''));
        $worksheetTypeId = $this->positiveInt($request->request->get('worksheet_type_id'));
        $worksheetStatusTypeId = $this->positiveInt($request->request->get('worksheet_status_type_id'));
        $worksheetType = $worksheetTypeId ? $worksheetTypeRepository->find($worksheetTypeId) : null;
        $worksheetStatusType = $worksheetStatusTypeId
            ? $worksheetStatusTypeRepository->find($worksheetStatusTypeId)
            : null;

        $errors = [];
        if ($title === '') {
            $errors[] = 'A munkalap címe kötelező.';
        }
        if (!$worksheetType) {
            $errors[] = 'Válassz munkalaptípust.';
        }
        if (!$worksheetStatusType) {
            $errors[] = 'Válassz státuszt.';
        } elseif ($worksheetStatusType->getWorksheetType()?->getId() !== $worksheetType?->getId()) {
            $errors[] = 'A kiválasztott státusz nem tartozik a munkalaptípushoz.';
        }

        if ($this->isServiceUser() && (
            ($worksheet && $worksheet->getWorksheetType()?->getCode() !== self::SERVICE_WORKSHEET_TYPE_CODE)
            || ($worksheetType && $worksheetType->getCode() !== self::SERVICE_WORKSHEET_TYPE_CODE)
        )) {
            return $this->serviceWorksheetAccessDenied();
        }

        if ($errors !== []) {
            return $this->response(false, [
                'data' => ['error' => implode('<br>', $errors), 'errors' => $errors],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $partnerId = $this->positiveInt($request->request->get('partner_id'));
        $partner = $partnerId ? $partnerRepository->find($partnerId) : null;
        if ($partnerId && !$partner) {
            return $this->response(false, [
                'data' => ['error' => 'A kiválasztott partner nem található.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $requiresOperationalDetails = true;
        $operationalFields = [
            'operating_hours' => 'Az üzemóra megadása kötelező.',
            'kilometer' => 'A KM óra állás megadása kötelező.',
            'work_and_travel_time' => 'A munka és útidő megadása kötelező.',
            'distance_to_site' => 'A kiszállás megadása kötelező.',
        ];
        $operationalErrors = [];
        if ($requiresOperationalDetails) {
            foreach ($operationalFields as $field => $message) {
                $value = trim((string) $request->request->get($field, ''));
                if (!is_numeric($value) || (float) $value < 1) {
                    $operationalErrors[] = $message;
                }
            }
        }
        if ($operationalErrors !== []) {
            return $this->response(false, [
                'data' => ['error' => implode('<br>', $operationalErrors), 'errors' => $operationalErrors],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $machineId = $this->positiveInt($request->request->get('machine_id'));
        $machine = $machineId ? $machineRepository->find($machineId) : null;
        if ($machineId && !$machine) {
            return $this->response(false, [
                'data' => ['error' => 'A kiválasztott gép nem található.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $isMachineMove = (string) $worksheetType->getCode() === self::MACHINE_MOVE_WORKSHEET_TYPE_CODE;
        $companySiteId = $isMachineMove
            ? $this->positiveInt($request->request->get('company_site_id'))
            : null;
        $companySite = $companySiteId ? $companySiteRepository->find($companySiteId) : null;
        $moveErrors = [];
        if ($isMachineMove && !$machine) {
            $moveErrors[] = 'A gép megadása kötelező a telephelyek közötti mozgatáshoz.';
        }
        if ($isMachineMove && !$companySite) {
            $moveErrors[] = 'A céltelephely megadása kötelező.';
        }
        if ($moveErrors !== []) {
            return $this->response(false, [
                'data' => ['error' => implode('<br>', $moveErrors), 'errors' => $moveErrors],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $submittedNotificationContactIds = $request->request->all()['notification_contact_ids'] ?? [];
        if (!is_array($submittedNotificationContactIds)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen kapcsolattartói értesítési beállítás.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $requestedNotificationContactIds = [];
        foreach ($submittedNotificationContactIds as $submittedNotificationContactId) {
            $contactId = $this->positiveInt($submittedNotificationContactId);
            if ($contactId) {
                $requestedNotificationContactIds[$contactId] = true;
            }
        }

        $selectedNotificationContacts = [];
        if ($partner) {
            $partnerContacts = $partnerContactRepository->findBy(
                ['partner' => $partner],
                ['defaultContact' => 'DESC', 'name' => 'ASC', 'id' => 'ASC'],
            );
            $partnerContactsById = [];
            foreach ($partnerContacts as $partnerContact) {
                if ($partnerContact->getId()) {
                    $partnerContactsById[$partnerContact->getId()] = $partnerContact;
                }
            }

            $invalidContactIds = array_diff(
                array_keys($requestedNotificationContactIds),
                array_keys($partnerContactsById),
            );
            if ($invalidContactIds !== []) {
                return $this->response(false, [
                    'data' => ['error' => 'A kiválasztott kapcsolattartó nem tartozik a partnerhez.'],
                ], 'json', Response::HTTP_BAD_REQUEST);
            }

            foreach ($partnerContactsById as $contactId => $partnerContact) {
                if ($partnerContact->isDefaultContact() || isset($requestedNotificationContactIds[$contactId])) {
                    $selectedNotificationContacts[] = $partnerContact;
                }
            }
        } elseif ($requestedNotificationContactIds !== []) {
            return $this->response(false, [
                'data' => ['error' => 'Kapcsolattartó kiválasztásához partnert is meg kell adni.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }
        try {
            $materialsUsed = $this->normalizeMaterialsUsed(
                $request->request->get('materials_used', '[]'),
                true,
            );
        } catch (\InvalidArgumentException) {
            return $this->response(false, [
                'data' => ['error' => 'A felhasznált anyagok adatai érvénytelenek.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $now = new \DateTimeImmutable();
        $isNew = !$worksheet;
        if ($isNew) {
            $worksheet = (new Worksheet())
                ->setCode($this->generateWorksheetCode($worksheetRepository))
                ->setDatetimeAdd($now)
                ->setDatetimeOpen($now)
                ->setUidAdd($this->getUser()?->getId())
                ->setStatus('1');
        }

        $data = $worksheet->getData() ?? [];
        $data['machine'] = $machine
            ? (string) $machine->getTitle()
            : trim((string) $request->request->get('machine', ''));
        if ($machine) {
            $data['machine_id'] = $machine->getId();
            $data['machine_code'] = $machine->getCode();
        } else {
            unset($data['machine_id'], $data['machine_code']);
        }
        if ($partner) {
            $data['partner_id'] = $partner->getId();
        } else {
            unset($data['partner_id']);
        }
        unset($data['project_id'], $data['project_name'], $data['project_code']);
        if ($companySite) {
            $data['company_site_id'] = $companySite->getId();
            $data['company_site_title'] = $companySite->getTitle();
            $data['company_site_code'] = $companySite->getCode();
        } else {
            unset($data['company_site_id'], $data['company_site_title'], $data['company_site_code']);
        }
        $data['note'] = trim((string) $request->request->get('note', ''));
        $data['operating_hours'] = trim((string) $request->request->get('operating_hours', ''));
        $data['kilometer'] = trim((string) $request->request->get('kilometer', ''));
        $data['work_and_travel_time'] = trim((string) $request->request->get('work_and_travel_time', ''));
        $data['distance_to_site'] = trim((string) $request->request->get('distance_to_site', ''));
        $data['materials_used'] = $materialsUsed;
        $data['worksheet_type_code'] = (string) $worksheetType->getCode();

        $machineRentalId = $this->positiveInt($request->request->get('machine_rental_id'));
        if ($machineRentalId) {
            $data['machine_rental_id'] = $machineRentalId;
        }
        $isClosed = in_array((string) $worksheetStatusType->getCode(), self::FINAL_STATUS_CODES, true);
        $userId = $this->getUser()?->getId();

        $worksheet
            ->setTitle($title)
            ->setWorksheetType($worksheetType)
            ->setWorksheetStatusType($worksheetStatusType)
            ->setPartner($partner)
            ->setMachineId($machineId)
            ->setMachineRentalId($machineRentalId)
            ->setCompanySite($companySite)
            ->setData($data)
            ->setUidLast($userId)
            ->setDatetimeLast($now)
            ->setDatetimeClosed($isClosed ? ($worksheet->getDatetimeClosed() ?? $now) : null);

        $entityManager->wrapInTransaction(function () use (
            $worksheet,
            $partner,
            $selectedNotificationContacts,
            $worksheetRepository,
            $worksheetNotificatedContactRepository,
            $isMachineMove,
            $machine,
            $companySite,
            $machineRepository,
            $userId,
            $now,
        ): void {
            if ($isMachineMove && $machine && $companySite) {
                $machine
                    ->setCompanySite($companySite)
                    ->setUidLast($userId)
                    ->setDatetimeLast($now);
                $machineRepository->save($machine, false);
            }

            $worksheetRepository->save($worksheet, false);
            $worksheetNotificatedContactRepository->replaceForWorksheet(
                $worksheet,
                $partner,
                $selectedNotificationContacts,
            );
        });

        return $this->response(true, [
            'data' => [
                'id' => $worksheet->getId(),
                'worksheetId' => $worksheet->getId(),
                'code' => $worksheet->getCode(),
                'worksheetCode' => $worksheet->getCode(),
                'mode' => $isNew ? 'insert' : 'update',
                'save_enabled' => !$isClosed,
                'notification_contact_count' => count($selectedNotificationContacts),
                'company_site_id' => $companySite?->getId(),
            ],
        ]);
    }

    #[Route('/worksheets/list', name: 'list_worksheets_list', methods: ['GET', 'POST'])]
    public function listWorksheets(Request $request, WorksheetRepository $worksheetRepository): Response
    {
        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen szűrőfeltételek.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        if ($this->isServiceUser()) {
            $filters['allowed_worksheet_type_codes'] = [self::SERVICE_WORKSHEET_TYPE_CODE];
        }

        $page = $this->positiveInt($request->request->get('page', $request->query->get('page', 1))) ?? 1;
        $list = $worksheetRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        return $this->response(true, [
            'template' => 'worksheets/list_worksheets_list.html.twig',
            'templateData' => [
                'records' => $list['records'],
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
            ],
            'data' => $list,
        ]);
    }

    #[Route(
        '/worksheet/pdf/{worksheetCode}',
        name: 'worksheet_pdf',
        methods: ['GET'],
        requirements: ['worksheetCode' => '[A-Za-z0-9._-]+'],
    )]
    public function printWorksheet(
        string $worksheetCode,
        WorksheetRepository $worksheetRepository,
        MachineRepository $machineRepository,
        MachineRentalRepository $machineRentalRepository,
        PartnerContactRepository $partnerContactRepository,
        WorksheetNotificatedContactRepository $worksheetNotificatedContactRepository,
        UserRepository $userRepository,
    ): Response {
        $worksheet = $worksheetRepository->findOneBy([
            'code' => $worksheetCode,
            'status' => '1',
        ]);
        if (!$worksheet || $worksheet->getStatus() !== '1') {
            throw $this->createNotFoundException('A munkalap nem található.');
        }

        if ($this->isServiceUser()
            && $worksheet->getWorksheetType()?->getCode() !== self::SERVICE_WORKSHEET_TYPE_CODE
        ) {
            throw $this->createAccessDeniedException('No access to this worksheet.');
        }

        $worksheetData = $worksheet->getData() ?? [];
        $selectedMachineId = $worksheet->getMachineId()
            ?? $this->positiveInt($worksheetData['machine_id'] ?? null);
        $machine = $selectedMachineId
            ? $machineRepository->find($selectedMachineId)
            : null;
        $partner = $worksheet->getPartner();
        $partnerProject = $partner
            ? $machineRentalRepository->findLatestByPartner($partner)?->getProject()
            : null;
        $partnerContact = null;

        if ($partner) {
            $selectedContactIds = $worksheetNotificatedContactRepository->findContactIds($worksheet);
            if ($selectedContactIds !== []) {
                $partnerContact = $partnerContactRepository->find($selectedContactIds[0]);
            }

            if (!$partnerContact || $partnerContact->getPartner()?->getId() !== $partner->getId()) {
                $partnerContact = $partnerContactRepository->findOneBy([
                    'partner' => $partner,
                    'defaultContact' => true,
                ]) ?? $partnerContactRepository->findOneBy(
                    ['partner' => $partner],
                    ['name' => 'ASC', 'id' => 'ASC'],
                );
            }
        }

        $editor = $worksheet->getUidLast()
            ? $userRepository->find($worksheet->getUidLast())
            : null;
        $typeCode = strtoupper(trim((string) $worksheet->getWorksheetType()?->getCode()));
        $isHandoverOrReturn = in_array($typeCode, ['MACHINE_HANDOVER', 'MACHINE_RETURN'], true);
        $documentDate = $worksheet->getDatetimeClosed()
            ?? $worksheet->getDatetimeLast()
            ?? $worksheet->getDatetimeAdd()
            ?? new \DateTimeImmutable();

        $rentalDateKey = $typeCode === 'MACHINE_HANDOVER'
            ? 'datetime_rental_start'
            : ($typeCode === 'MACHINE_RETURN' ? 'datetime_rental_end' : null);
        if ($rentalDateKey && !empty($worksheetData[$rentalDateKey])) {
            try {
                $documentDate = new \DateTimeImmutable((string) $worksheetData[$rentalDateKey]);
            } catch (\Throwable) {
                // Keep the worksheet date when the imported rental date is invalid.
            }
        }

        $projectDirectory = (string) $this->getParameter('kernel.project_dir');
        $templateData = [
            'worksheet' => $worksheet,
            'worksheetData' => $worksheetData,
            'materialsUsed' => $this->normalizeMaterialsUsed($worksheetData['materials_used'] ?? []),
            'machine' => $machine,
            'machineData' => $machine?->getData() ?? [],
            'partner' => $partner,
            'partnerProject' => $partnerProject,
            'partnerContact' => $partnerContact,
            'editor' => $editor,
            'documentDate' => $documentDate,
            'logoDataUri' => $this->imageDataUri($projectDirectory . '/public/assets/img/logo_4us.png'),
            'machineDrawingDataUri' => $isHandoverOrReturn
                ? $this->imageDataUri($projectDirectory . '/public/assets/img/machines_draw.png')
                : null,
        ];

        $template = $isHandoverOrReturn
            ? 'worksheets/print/index_worksheet_print_handover_and_return.html.twig'
            : 'worksheets/print/index_worksheet_print_universal.html.twig';

        try {
            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);
            $options->set('isPhpEnabled', false);

            $dompdf = new Dompdf($options);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->loadHtml($this->renderView($template, $templateData), 'UTF-8');
            $dompdf->render();
            $pdf = $dompdf->output();
        } catch (\Throwable) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap PDF előállítása nem sikerült.'],
            ], 'json', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $safeCode = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $worksheet->getCode()) ?: 'munkalap';
        $response = new Response($pdf);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Length', (string) strlen($pdf));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_INLINE,
                $safeCode . '.pdf',
                'munkalap.pdf',
            ),
        );

        return $response;
    }

    #[Route('/worksheets/attachments/list', name: 'list_worksheet_attachments', methods: ['POST'])]
    public function listWorksheetAttachments(
        Request $request,
        WorksheetRepository $worksheetRepository,
        WorksheetAttachmentRepository $worksheetAttachmentRepository,
        UserRepository $userRepository,
    ): Response {
        $worksheetId = $this->positiveInt($request->request->get('worksheet_id'));
        $worksheet = $worksheetId ? $worksheetRepository->find($worksheetId) : null;
        if (!$worksheet) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $page = $this->positiveInt($request->request->get('page')) ?? 1;
        $search = trim((string) $request->request->get('search', ''));
        $list = $worksheetAttachmentRepository->findRegularPage(
            $worksheet,
            $search,
            $page,
            self::ATTACHMENTS_PER_PAGE,
        );
        $items = $this->serializeAttachments($list['records'], $userRepository);

        return $this->response(true, [
            'template' => 'worksheets/list_worksheet_attachments.html.twig',
            'templateData' => [
                'attachments' => $items,
                'page' => $list['page'],
                'hasMore' => $list['hasMore'],
                'totalRecords' => $list['totalRecords'],
            ],
            'data' => [
                'items' => $items,
                'page' => $list['page'],
                'hasMore' => $list['hasMore'],
                'totalRecords' => $list['totalRecords'],
            ],
        ]);
    }

    #[Route('/worksheets/attachments/upload', name: 'upload_worksheet_attachment', methods: ['POST'])]
    public function uploadWorksheetAttachment(
        Request $request,
        WorksheetRepository $worksheetRepository,
        WorksheetAttachmentRepository $worksheetAttachmentRepository,
        UserRepository $userRepository,
    ): Response {
        $worksheetId = $this->positiveInt($request->request->get('worksheet_id'));
        $worksheet = $worksheetId ? $worksheetRepository->find($worksheetId) : null;
        if (!$worksheet) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('attachment');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $message = $file instanceof UploadedFile
                ? $file->getErrorMessage()
                : 'Nincs feltöltendő fájl.';

            return $this->response(false, [
                'data' => ['error' => $message],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $isConditionImage = filter_var(
            $request->request->get('is_condition_image', false),
            FILTER_VALIDATE_BOOL,
        );
        $conditionType = trim((string) $request->request->get('condition_type', ''));
        $detectedMimeType = $this->detectUploadedFileMimeType($file);
        $isImage = $this->isSafeInlineImage($detectedMimeType);

        if ($isConditionImage && ($conditionType === '' || !$isImage)) {
            return $this->response(false, [
                'data' => ['error' => 'Az állapotképhez érvényes képfájlt kell választani.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $originalName = trim($file->getClientOriginalName());
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $safeExtension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: '';
        $baseName = (string) pathinfo($originalName, PATHINFO_FILENAME);
        $safeBaseName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $baseName) ?: 'file';
        $safeBaseName = trim(substr($safeBaseName, 0, 100), '.-_');
        if ($safeBaseName === '') {
            $safeBaseName = 'file';
        }

        $storedName = bin2hex(random_bytes(12)) . '-' . $safeBaseName;
        if ($safeExtension !== '') {
            $storedName .= '.' . $safeExtension;
        }

        $safeWorksheetCode = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $worksheet->getCode()) ?: (string) $worksheet->getId();
        $relativeDirectory = 'uploads/worksheets/' . $safeWorksheetCode;
        $absoluteDirectory = $this->getParameter('kernel.project_dir') . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            return $this->response(false, [
                'data' => ['error' => 'Nem sikerült létrehozni a feltöltési mappát.'],
            ], 'json', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $fileSize = (int) ($file->getSize() ?? 0);
        try {
            $file->move($absoluteDirectory, $storedName);
        } catch (\Throwable $exception) {
            return $this->response(false, [
                'data' => ['error' => 'A fájl mentése nem sikerült: ' . $exception->getMessage()],
            ], 'json', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $relativePath = $relativeDirectory . '/' . $storedName;
        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $storedName;
        $now = new \DateTimeImmutable();
        $description = trim((string) $request->request->get('description', ''));
        if ($isConditionImage && $description === '') {
            $description = $conditionType;
        }

        $attachment = (new WorksheetAttachment())
            ->setWorksheet($worksheet)
            ->setName($originalName !== '' ? mb_substr($originalName, 0, 255) : $storedName)
            ->setDescription($description !== '' ? $description : null)
            ->setData([
                'originalName' => $originalName !== '' ? $originalName : $storedName,
                'storedName' => $storedName,
                'clientMimeType' => (string) $file->getClientMimeType(),
                'mimeType' => $detectedMimeType,
                'extension' => $safeExtension,
                'size' => $fileSize,
                'path' => $relativePath,
                'sha256' => is_file($absolutePath) ? hash_file('sha256', $absolutePath) : null,
                'clientLastModified' => $this->positiveInt($request->request->get('last_modified')),
                'isImage' => $isImage,
                'isConditionImage' => $isConditionImage,
                'conditionType' => $isConditionImage ? $conditionType : null,
                'uploadedAt' => $now->format(DATE_ATOM),
            ])
            ->setUidAdd($this->getUser()?->getId())
            ->setUidLast($this->getUser()?->getId())
            ->setDatetimeAdd($now)
            ->setDatetimeLast($now)
            ->setDatetimeOpen($now)
            ->setDatetimeClosed(null)
            ->setStatus('1');

        try {
            $worksheetAttachmentRepository->save($attachment);

            if ($isConditionImage) {
                foreach ($worksheetAttachmentRepository->findConditionImagesByType($worksheet, $conditionType) as $oldAttachment) {
                    if ($oldAttachment->getId() !== $attachment->getId()) {
                        $worksheetAttachmentRepository->softDelete($oldAttachment, $this->getUser()?->getId());
                    }
                }
            }
        } catch (\Throwable $exception) {
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }

            return $this->response(false, [
                'data' => ['error' => 'A csatolmány adatai nem menthetők: ' . $exception->getMessage()],
            ], 'json', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->response(true, [
            'data' => [
                'attachment' => $this->serializeAttachments([$attachment], $userRepository)[0],
            ],
        ]);
    }

    #[Route('/worksheets/attachments/description', name: 'update_worksheet_attachment_description', methods: ['POST'])]
    public function updateWorksheetAttachmentDescription(
        Request $request,
        WorksheetAttachmentRepository $worksheetAttachmentRepository,
        UserRepository $userRepository,
    ): Response {
        $attachmentId = $this->positiveInt($request->request->get('attachment_id'));
        $attachment = $attachmentId ? $worksheetAttachmentRepository->find($attachmentId) : null;
        if (!$attachment || $attachment->getStatus() !== '1') {
            return $this->response(false, [
                'data' => ['error' => 'A csatolmány nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $description = trim((string) $request->request->get('description', ''));
        $attachment
            ->setDescription($description !== '' ? $description : null)
            ->setUidLast($this->getUser()?->getId())
            ->setDatetimeLast(new \DateTimeImmutable());
        $worksheetAttachmentRepository->save($attachment);

        return $this->response(true, [
            'data' => [
                'attachment' => $this->serializeAttachments([$attachment], $userRepository)[0],
            ],
        ]);
    }

    #[Route('/worksheets/attachments/delete', name: 'delete_worksheet_attachment', methods: ['POST', 'DELETE'])]
    public function deleteWorksheetAttachment(
        Request $request,
        WorksheetAttachmentRepository $worksheetAttachmentRepository,
    ): Response {
        $attachmentId = $this->positiveInt($request->request->get('attachment_id'));
        $attachment = $attachmentId ? $worksheetAttachmentRepository->find($attachmentId) : null;
        if (!$attachment || $attachment->getStatus() !== '1') {
            return $this->response(false, [
                'data' => ['error' => 'A csatolmány nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $worksheetAttachmentRepository->softDelete($attachment, $this->getUser()?->getId());

        return $this->response(true, [
            'data' => ['id' => $attachment->getId(), 'deleted' => true],
        ]);
    }

    #[Route('/worksheets/attachments/file/{id}', name: 'worksheet_attachment_file', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function attachmentFile(int $id, Request $request, WorksheetAttachmentRepository $repository): Response
    {
        $attachment = $repository->find($id);
        if (!$attachment || $attachment->getStatus() !== '1') {
            throw $this->createNotFoundException('A csatolmány nem található.');
        }

        $data = $attachment->getData() ?? [];
        $absolutePath = $this->resolveAttachmentPath((string) ($data['path'] ?? ''));
        if (!$absolutePath) {
            throw $this->createNotFoundException('A csatolmány fájlja nem található.');
        }

        $mimeType = strtolower((string) ($data['mimeType'] ?? ''));
        if ($mimeType === '' || $mimeType === 'application/octet-stream') {
            $mimeType = strtolower((string) (mime_content_type($absolutePath) ?: 'application/octet-stream'));
        }
        $inline = $request->query->getBoolean('inline') && $this->isSafeInlineImage($mimeType);
        $originalName = (string) ($data['originalName'] ?? $attachment->getName() ?? 'download');

        $response = new BinaryFileResponse($absolutePath);
        $response->headers->set('Content-Type', $mimeType !== '' ? $mimeType : 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(
            $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $originalName,
            'download',
        );

        return $response;
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function imageDataUri(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('A PDF-hez szükséges kép nem található.');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('A PDF-hez szükséges kép nem olvasható.');
        }

        $mimeType = mime_content_type($path) ?: 'image/png';

        return sprintf('data:%s;base64,%s', $mimeType, base64_encode($contents));
    }

    private function decodeComboboxFilters(Request $request): ?array
    {
        $filters = json_decode((string) $request->request->get('filters', '{}'), true);

        return is_array($filters) ? $filters : null;
    }

    private function generateWorksheetCode(WorksheetRepository $worksheetRepository): string
    {
        do {
            $code = 'M' . date('Ym') . '-' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        } while ($worksheetRepository->findOneBy(['code' => $code]) !== null);

        return $code;
    }

    private function isFinalizedWorksheet(?Worksheet $worksheet): bool
    {
        return $worksheet !== null && in_array(
            (string) $worksheet->getWorksheetStatusType()?->getCode(),
            self::FINAL_STATUS_CODES,
            true,
        );
    }

    /**
     * @return array<int, array{
     *     article_number: string,
     *     name: string,
     *     quantity: int|float,
     *     unit: array{name: string, short: string}
     * }>
     */
    private function normalizeMaterialsUsed(mixed $materialsUsed, bool $strict = false): array
    {
        if (is_string($materialsUsed)) {
            if (trim($materialsUsed) === '') {
                $materialsUsed = [];
            } else {
                try {
                    $materialsUsed = json_decode($materialsUsed, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    if ($strict) {
                        throw new \InvalidArgumentException('Invalid materials JSON.');
                    }

                    return [];
                }
            }
        }

        if (!is_array($materialsUsed) || !array_is_list($materialsUsed)) {
            if ($strict) {
                throw new \InvalidArgumentException('Materials must be a list.');
            }

            return [];
        }

        $unitsByShort = array_column(self::MATERIAL_UNITS, null, 'short');
        $normalized = [];
        foreach ($materialsUsed as $material) {
            if (!is_array($material)) {
                if ($strict) {
                    throw new \InvalidArgumentException('Every material must be an object.');
                }

                continue;
            }

            $quantityRaw = str_replace(',', '.', trim((string) ($material['quantity'] ?? '')));
            $quantity = is_numeric($quantityRaw) ? (float) $quantityRaw : 0.0;
            $unitValue = $material['unit'] ?? '';
            $unitShort = trim((string) (is_array($unitValue) ? ($unitValue['short'] ?? '') : $unitValue));
            if ($quantity < 1 || !is_finite($quantity) || !isset($unitsByShort[$unitShort])) {
                if ($strict) {
                    throw new \InvalidArgumentException('Invalid material quantity or unit.');
                }

                continue;
            }

            $normalized[] = [
                'article_number' => mb_substr(trim((string) ($material['article_number'] ?? '')), 0, 255),
                'name' => mb_substr(trim((string) ($material['name'] ?? '')), 0, 255),
                'quantity' => floor($quantity) === $quantity ? (int) $quantity : $quantity,
                'unit' => $unitsByShort[$unitShort],
            ];
        }

        return $normalized;
    }

    private function serializeWorksheet(Worksheet $worksheet): array
    {
        return [
            'id' => $worksheet->getId(),
            'code' => $worksheet->getCode(),
            'title' => $worksheet->getTitle(),
            'worksheetTypeId' => $worksheet->getWorksheetType()?->getId(),
            'worksheetStatusTypeId' => $worksheet->getWorksheetStatusType()?->getId(),
            'status_title' => $worksheet->getWorksheetStatusType()?->getCode(),
            'status_name' => $worksheet->getWorksheetStatusType()?->getTitle(),
            'partnerId' => $worksheet->getPartner()?->getId(),
            'machineId' => $worksheet->getMachineId(),
            'machineRentalId' => $worksheet->getMachineRentalId(),
            'companySiteId' => $worksheet->getCompanySite()?->getId(),
            'data' => $worksheet->getData() ?? [],
        ];
    }

    private function serializePartnerProject(?\App\Entity\Project $project): ?array
    {
        if (!$project) {
            return null;
        }

        return [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'code' => $project->getCode(),
        ];
    }

    /**
     * @param WorksheetAttachment[] $attachments
     * @return array<int, array<string, mixed>>
     */
    private function serializeAttachments(array $attachments, UserRepository $userRepository): array
    {
        $userIds = [];
        foreach ($attachments as $attachment) {
            $userId = $attachment->getUidAdd() ?? $attachment->getUidLast();
            if ($userId) {
                $userIds[] = $userId;
            }
        }

        $users = [];
        if ($userIds !== []) {
            foreach ($userRepository->findBy(['id' => array_values(array_unique($userIds))]) as $user) {
                if ($user instanceof User && $user->getId()) {
                    $users[$user->getId()] = $user;
                }
            }
        }

        $result = [];
        foreach ($attachments as $attachment) {
            $data = $attachment->getData() ?? [];
            $uploaderId = $attachment->getUidAdd() ?? $attachment->getUidLast();
            $uploader = $uploaderId ? ($users[$uploaderId] ?? null) : null;
            $mimeType = strtolower((string) ($data['mimeType'] ?? ''));
            if ($mimeType === '' || $mimeType === 'application/octet-stream') {
                $storedPath = $this->resolveAttachmentPath((string) ($data['path'] ?? ''));
                $mimeType = $storedPath
                    ? strtolower((string) (mime_content_type($storedPath) ?: 'application/octet-stream'))
                    : 'application/octet-stream';
            }
            $isConditionImage = (bool) ($data['isConditionImage'] ?? false);
            $size = (int) ($data['size'] ?? 0);
            $originalName = (string) ($data['originalName'] ?? $attachment->getName());
            $extension = (string) ($data['extension'] ?? pathinfo($originalName, PATHINFO_EXTENSION));

            $result[] = [
                'id' => $attachment->getId(),
                'name' => $attachment->getName(),
                'originalName' => $originalName,
                'description' => $attachment->getDescription(),
                'mimeType' => $mimeType,
                'clientMimeType' => (string) ($data['clientMimeType'] ?? ''),
                'extension' => $extension,
                'size' => $size,
                'sizeFormatted' => $this->formatFileSize($size),
                'sha256' => $data['sha256'] ?? null,
                'isImage' => (bool) ($data['isImage'] ?? $this->isSafeInlineImage($mimeType)),
                'isConditionImage' => $isConditionImage,
                'conditionType' => $isConditionImage ? (string) ($data['conditionType'] ?? '') : null,
                'uploadedAt' => $attachment->getDatetimeAdd()?->format(DATE_ATOM),
                'uploadedAtFormatted' => $attachment->getDatetimeAdd()?->format('Y-m-d H:i'),
                'uploadedBy' => [
                    'id' => $uploader?->getId(),
                    'name' => $uploader?->getName() ?: $uploader?->getUserIdentifier(),
                ],
                'contentUrl' => $this->generateUrl('worksheet_attachment_file', [
                    'id' => $attachment->getId(),
                    'inline' => 1,
                ]),
                'downloadUrl' => $this->generateUrl('worksheet_attachment_file', [
                    'id' => $attachment->getId(),
                ]),
                'data' => $data,
            ];
        }

        return $result;
    }

    private function isServiceUser(): bool
    {
        return $this->isGranted('ROLE_SERVICE');
    }

    private function serviceWorksheetAccessDenied(): Response
    {
        return $this->response(false, [
            'data' => ['error' => 'SERVICE jogosultsaggal csak ERROR_REPORT munkalap kezelheto.'],
        ], 'json', Response::HTTP_FORBIDDEN);
    }

    private function isSafeInlineImage(string $mimeType): bool
    {
        return in_array(strtolower($mimeType), [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/avif',
        ], true);
    }

    private function detectUploadedFileMimeType(UploadedFile $file): string
    {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo === false) {
            return 'application/octet-stream';
        }

        try {
            $mimeType = finfo_file($fileInfo, $file->getPathname());
        } finally {
            finfo_close($fileInfo);
        }

        return strtolower(is_string($mimeType) && $mimeType !== '' ? $mimeType : 'application/octet-stream');
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', ' ') . ' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1, ',', ' ') . ' MB';
        }

        return number_format($bytes / (1024 * 1024 * 1024), 1, ',', ' ') . ' GB';
    }

    private function resolveAttachmentPath(string $relativePath): ?string
    {
        if ($relativePath === '') {
            return null;
        }

        $projectDirectory = (string) $this->getParameter('kernel.project_dir');
        $uploadRoot = realpath($projectDirectory . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'worksheets');
        if (!$uploadRoot) {
            return null;
        }

        $normalizedRelativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
        $candidate = realpath($projectDirectory . DIRECTORY_SEPARATOR . $normalizedRelativePath);
        if (!$candidate || !is_file($candidate)) {
            return null;
        }

        $rootPrefix = rtrim($uploadRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with(strtolower($candidate), strtolower($rootPrefix))) {
            return null;
        }

        return $candidate;
    }


}
