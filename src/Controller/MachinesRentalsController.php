<?php

namespace App\Controller;

use App\Entity\MachineRental;
use App\Entity\Worksheet;
use App\Repository\MachineRentalRepository;
use App\Repository\MachineRepository;
use App\Repository\PartnerRepository;
use App\Repository\ProjectRepository;
use App\Repository\WorksheetRepository;
use App\Repository\WorksheetStatusTypeRepository;
use App\Repository\WorksheetTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MachinesRentalsController extends BaseController
{
    private const ITEMS_PER_PAGE = 20;
    private const WORKSHEET_TYPE_CODES = ['MACHINE_HANDOVER', 'MACHINE_RETURN'];

    #[Route('/machine-rentals', name: 'index_machine_rentals', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('machine_rentals/index.html.twig');
    }

    #[Route('/machine-rentals/add', name: 'index_machine_rentals_add', methods: ['GET'])]
    public function indexAdd(
        Request $request,
        MachineRentalRepository $machineRentalRepository,
        WorksheetRepository $worksheetRepository,
        WorksheetTypeRepository $worksheetTypeRepository,
    ): Response {
        $id = $this->positiveInt($request->query->get('id'));
        $item = $id ? $machineRentalRepository->find($id) : null;

        if ($id && !$item) {
            return $this->response(false, [
                'data' => ['error' => 'A gépkölcsönzés nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $worksheetIds = [];
        if ($item) {
            foreach (self::WORKSHEET_TYPE_CODES as $worksheetTypeCode) {
                $worksheetType = $worksheetTypeRepository->findOneBy(['code' => $worksheetTypeCode]);
                if ($worksheetType) {
                    $worksheetIds[$worksheetTypeCode] = $worksheetRepository
                        ->findOneByRentalAndType((int) $item->getId(), $worksheetType)?->getId();
                }
            }
        }

        return $this->response(true, [
            'template' => 'machine_rentals/index_add.html.twig',
            'templateData' => [
                'item' => $item,
                'worksheetIds' => $worksheetIds,
            ],
            'data' => [
                'id' => $item?->getId(),
                'code' => $item?->getCode(),
                'worksheet_ids' => $worksheetIds,
            ],
        ]);
    }

    #[Route('/machine-rentals/partners/select/list', name: 'list_machine_rental_partner_select_list', methods: ['POST'])]
    public function listPartnerOptions(Request $request, PartnerRepository $partnerRepository): Response
    {
        $filters = $this->decodeFilters($request);
        if ($filters === null) {
            return $this->invalidFiltersResponse('Érvénytelen partnerkeresési adatok.');
        }

        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);
        $records = $partnerRepository->findForMachineRentalSelect($search, 20);
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
                'status' => (string) ($record['status'] ?? ''),
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

    #[Route('/machine-rentals/machines/select/list', name: 'list_machine_rental_machine_select_list', methods: ['POST'])]
    public function listMachineOptions(
        Request $request,
        MachineRentalRepository $machineRentalRepository,
        MachineRepository $machineRepository,
    ): Response {
        $filters = $this->decodeFilters($request);
        if ($filters === null) {
            return $this->invalidFiltersResponse('Érvénytelen gépkeresési adatok.');
        }

        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);
        $rentalId = $this->positiveInt($filters['rental_id'] ?? null);
        if ($rentalId && !$machineRentalRepository->find($rentalId)) {
            $rentalId = null;
        }

        $records = $machineRepository->findForMachineRentalSelect($search, $rentalId, 20);
        $items = array_map(static function (array $record): array {
            $active = (string) ($record['status'] ?? '') === '1';
            $occupied = (int) ($record['open_rental_count'] ?? 0) > 0;
            $selectable = $active && !$occupied;
            $meta = array_filter([
                trim((string) ($record['code'] ?? '')),
                trim((string) ($record['category_title'] ?? '')),
                ($record['category_code'] ?? null) ? '(' . $record['category_code'] . ')' : null,
            ]);

            if (!$active) {
                $disabledReason = 'A gép nem választható ki, mert inaktív.';
                $badge = 'Inaktív';
            } elseif ($occupied) {
                $disabledReason = 'A gép nem választható ki, mert már van nyitott kölcsönzése.';
                $badge = 'Kölcsönözve';
            } else {
                $disabledReason = null;
                $badge = null;
            }

            return [
                'id' => (int) $record['id'],
                'label' => (string) $record['title'],
                'meta' => implode(' · ', $meta),
                'status' => (string) ($record['status'] ?? ''),
                'selectable' => $selectable,
                'badge' => $badge,
                'disabledReason' => $disabledReason,
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

    #[Route('/machine-rentals/projects/select/list', name: 'list_machine_rental_project_select_list', methods: ['POST'])]
    public function listProjectOptions(Request $request, ProjectRepository $projectRepository): Response
    {
        $filters = $this->decodeFilters($request);
        if ($filters === null) {
            return $this->invalidFiltersResponse('Érvénytelen projektkeresési adatok.');
        }

        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);
        $records = $projectRepository->findForMachineRentalSelect($search, 20);
        $items = array_map(static function (array $record): array {
            $active = (string) ($record['status'] ?? '') === '1';

            return [
                'id' => (int) $record['id'],
                'label' => (string) $record['name'],
                'meta' => trim((string) ($record['code'] ?? '')),
                'selectable' => $active,
                'badge' => $active ? null : 'Inaktív',
                'disabledReason' => $active ? null : 'A projekt nem választható ki, mert inaktív.',
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

    #[Route('/machine-rentals/save', name: 'save_machine_rentals', methods: ['POST'])]
    public function saveMachineRental(
        Request $request,
        MachineRentalRepository $machineRentalRepository,
        PartnerRepository $partnerRepository,
        MachineRepository $machineRepository,
        ProjectRepository $projectRepository,
        WorksheetRepository $worksheetRepository,
        WorksheetTypeRepository $worksheetTypeRepository,
        WorksheetStatusTypeRepository $worksheetStatusTypeRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $postData = [];
        foreach ($request->request->all() as $key => $value) {
            $postData[$key] = is_string($value) ? trim($value) : $value;
        }

        $id = $this->positiveInt($postData['id'] ?? null);
        $machineRental = $id ? $machineRentalRepository->find($id) : null;
        if ($id && !$machineRental) {
            return $this->response(false, [
                'data' => ['error' => 'A módosítandó gépkölcsönzés nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }
        $isNew = !$machineRental;

        $partnerId = $this->positiveInt($postData['partner_id'] ?? null);
        $partner = $partnerId ? $partnerRepository->find($partnerId) : null;
        if (!$partner) {
            return $this->validationError('A partner megadása kötelező.');
        }

        $machineId = $this->positiveInt($postData['machine_id'] ?? null);
        $machine = $machineId ? $machineRepository->find($machineId) : null;
        if (!$machine) {
            return $this->validationError('A gép megadása kötelező.');
        }

        $projectId = $this->positiveInt($postData['project_id'] ?? null);
        $project = $projectId ? $projectRepository->find($projectId) : null;
        if ($projectId && !$project) {
            return $this->validationError('A kiválasztott projekt nem található.');
        }

        $originalPartnerId = $machineRental?->getPartner()?->getId();
        if ($partner->getStatus() !== '1' && ($isNew || $originalPartnerId !== $partner->getId())) {
            return $this->validationError('A kiválasztott partner inaktív, ezért nem választható ki.');
        }

        $originalMachineId = $machineRental?->getMachine()?->getId();
        if ($machine->getStatus() !== '1' && ($isNew || $originalMachineId !== $machine->getId())) {
            return $this->validationError('A kiválasztott gép inaktív, ezért nem választható ki.');
        }

        $originalProjectId = $machineRental?->getProject()?->getId();
        if ($project && $project->getStatus() !== '1' && ($isNew || $originalProjectId !== $project->getId())) {
            return $this->validationError('A kiválasztott projekt inaktív, ezért nem választható ki.');
        }

        $startValue = (string) ($postData['datetime_rental_start'] ?? '');
        $endValue = (string) ($postData['datetime_rental_end'] ?? '');
        $rentalStart = $this->parseDateTime($startValue);
        $rentalEnd = $this->parseDateTime($endValue);

        if (!$rentalStart) {
            return $this->validationError('A bérlés kezdetének megadása kötelező.');
        }
        if ($endValue !== '' && !$rentalEnd) {
            return $this->validationError('A tervezett befejezés dátuma érvénytelen.');
        }
        if ($rentalEnd && $rentalEnd < $rentalStart) {
            return $this->validationError('A bérlés tervezett vége nem lehet korábbi, mint a kezdete.');
        }

        $status = $isNew
            ? '1'
            : (in_array((string) ($postData['status'] ?? $machineRental->getStatus()), ['0', '1'], true)
                ? (string) ($postData['status'] ?? $machineRental->getStatus())
                : $machineRental->getStatus());

        if ($status === '1') {
            $openRental = $machineRentalRepository->findOpenByMachine($machine, $id);
            if ($openRental) {
                return $this->validationError(sprintf(
                    'Erre a gépre már van nyitott kölcsönzés: %s.',
                    $openRental->getCode() ?: '#' . $openRental->getId(),
                ));
            }
        }

        $worksheetTypes = [];
        $openStatuses = [];
        foreach (self::WORKSHEET_TYPE_CODES as $worksheetTypeCode) {
            $worksheetType = $worksheetTypeRepository->findOneBy(['code' => $worksheetTypeCode]);
            if (!$worksheetType) {
                return $this->validationError(sprintf('Hiányzik a %s munkalaptípus.', $worksheetTypeCode));
            }

            $openStatus = $worksheetStatusTypeRepository->findOneBy([
                'worksheetType' => $worksheetType,
                'code' => 'OPEN',
            ]);
            if (!$openStatus) {
                return $this->validationError(sprintf('Hiányzik az OPEN státusz a %s munkalaptípushoz.', $worksheetTypeCode));
            }

            $worksheetTypes[$worksheetTypeCode] = $worksheetType;
            $openStatuses[$worksheetTypeCode] = $openStatus;
        }

        $now = new \DateTimeImmutable();
        $userId = $this->getUser()?->getId();
        if ($isNew) {
            $machineRental = (new MachineRental())
                ->setCode($this->generateRentalCode($machineRentalRepository, $now))
                ->setDatetimeAdd($now)
                ->setUidAdd($userId);
        }

        $machineRental
            ->setPartner($partner)
            ->setMachine($machine)
            ->setProject($project)
            ->setDatetimeRentalStart($rentalStart)
            ->setDatetimeRentalEnd($rentalEnd)
            ->setStatus($status)
            ->setDatetimeLast($now)
            ->setUidLast($userId);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        $worksheetIds = [];

        try {
            $entityManager->persist($machineRental);
            $entityManager->flush();
            $rentalId = (int) $machineRental->getId();

            foreach (self::WORKSHEET_TYPE_CODES as $worksheetTypeCode) {
                $worksheetType = $worksheetTypes[$worksheetTypeCode];
                $worksheet = $worksheetRepository->findOneByRentalAndType($rentalId, $worksheetType);

                if (!$worksheet) {
                    $worksheet = (new Worksheet())
                        ->setCode($this->generateWorksheetCode($worksheetRepository, $now))
                        ->setWorksheetType($worksheetType)
                        ->setWorksheetStatusType($openStatuses[$worksheetTypeCode])
                        ->setStatus('1')
                        ->setDatetimeAdd($now)
                        ->setDatetimeOpen($now)
                        ->setUidAdd($userId);
                }

                $worksheetData = array_merge($worksheet->getData() ?? [], [
                    'machine_rental_id' => $rentalId,
                    'machine_rental_code' => $machineRental->getCode(),
                    'partner_id' => $partner->getId(),
                    'machine_id' => $machine->getId(),
                    'machine_code' => $machine->getCode(),
                    'datetime_rental_start' => $rentalStart->format(\DateTimeInterface::ATOM),
                    'datetime_rental_end' => $rentalEnd?->format(\DateTimeInterface::ATOM),
                    'datetime_rental_end_planned' => $rentalEnd?->format(\DateTimeInterface::ATOM),
                ]);

                $worksheet
                    ->setTitle(sprintf('%s – %s – %s', $worksheetType->getTitle(), $machine->getTitle(), $partner->getName()))
                    ->setPartner($partner)
                    ->setMachineId($machine->getId())
                    ->setMachineRentalId($rentalId)
                    ->setData($worksheetData)
                    ->setDatetimeLast($now)
                    ->setUidLast($userId);

                $entityManager->persist($worksheet);
                $entityManager->flush();
                $worksheetIds[$worksheetTypeCode] = $worksheet->getId();
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            return $this->response(false, [
                'data' => ['error' => 'A gépkölcsönzés mentése nem sikerült.'],
            ], 'json', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->response(true, [
            'data' => [
                'id' => $machineRental->getId(),
                'code' => $machineRental->getCode(),
                'mode' => $isNew ? 'insert' : 'update',
                'worksheet_id' => $worksheetIds['MACHINE_HANDOVER'] ?? null,
                'worksheet_ids' => $worksheetIds,
            ],
        ]);
    }

    #[Route('/machine-rentals/list', name: 'list_machine_rentals', methods: ['GET', 'POST'])]
    public function listMachineRentals(Request $request, MachineRentalRepository $machineRentalRepository): Response
    {
        $filters = $this->decodeFilters($request);
        if ($filters === null) {
            return $this->invalidFiltersResponse('Érvénytelen lista-szűrők.');
        }

        $page = max(1, (int) $request->request->get('page', $request->query->get('page', 1)));
        $list = $machineRentalRepository->findList($filters, $page, self::ITEMS_PER_PAGE);
        $content = $this->renderView('machine_rentals/list_machine_rentals.html.twig', [
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

    private function decodeFilters(Request $request): ?array
    {
        $filtersJson = (string) $request->request->get('filters', $request->query->get('filters', '{}'));
        $filters = json_decode($filtersJson, true);

        return is_array($filters) ? $filters : null;
    }

    private function invalidFiltersResponse(string $message): Response
    {
        return $this->response(false, [
            'data' => ['error' => $message],
        ], 'json', Response::HTTP_BAD_REQUEST);
    }

    private function validationError(string $message): Response
    {
        return $this->response(false, [
            'data' => ['error' => $message],
        ], 'json', Response::HTTP_BAD_REQUEST);
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function parseDateTime(string $value): ?\DateTimeImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function generateRentalCode(
        MachineRentalRepository $machineRentalRepository,
        \DateTimeImmutable $now,
    ): string {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $code = sprintf('B%s-%08d', $now->format('Ym'), random_int(0, 99999999));
            if (!$machineRentalRepository->findOneBy(['code' => $code])) {
                return $code;
            }
        }

        throw new \RuntimeException('Nem sikerült egyedi gépkölcsönzés-kódot generálni.');
    }

    private function generateWorksheetCode(
        WorksheetRepository $worksheetRepository,
        \DateTimeImmutable $now,
    ): string {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $code = sprintf('M%s-%08d', $now->format('Ym'), random_int(0, 99999999));
            if (!$worksheetRepository->findOneBy(['code' => $code])) {
                return $code;
            }
        }

        throw new \RuntimeException('Nem sikerült egyedi munkalapkódot generálni.');
    }
}
