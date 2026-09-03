<?php

namespace App\Controller;

use App\Entity\MachineRental;
use App\Entity\Worksheet;
use App\Repository\MachineRentalRepository;
use App\Repository\MachineRepository;
use App\Repository\PartnerRepository;
use App\Repository\WorksheetStatusTypeRepository;
use App\Repository\WorksheetTypeRepository;
use App\Service\WorksheetNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MachineRentalsController extends BaseController
{
    private const ITEMS_PER_PAGE = 20;

    #[Route('/machine-rentals', name: 'index_machine_rentals')]
    public function index(): Response
    {
        return $this->render('machine_rentals/index.html.twig');
    }

    #[Route('/machine-rentals/partners', name: 'list_machine_rentals_partners')]
    public function listPartners(
        Request $request,
        PartnerRepository $partnerRepository,
    ): Response {
        $partners = array_map(static fn ($partner): array => [
            'id' => $partner->getId(),
            'name' => $partner->getName(),
        ], $partnerRepository->findBy([], ['name' => 'ASC']));

        return $this->response(true, [
            'templateData' => [],
            'data' => [
                'partners' => $partners,
            ],
        ]);
    }

    #[Route('/machine-rentals/add', name: 'index_machine_rentals_add')]
    public function indexAdd(
        Request $request,
        MachineRentalRepository $machineRentalRepository,
    ): Response {
        $idRaw = $request->query->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $item = $id > 0 ? $machineRentalRepository->find($id) : null;

        return $this->response(true, [
            'template' => 'machine_rentals/index_add.html.twig',
            'templateData' => [
                'item' => $item,
            ],
            'data' => [
                'code' => $item ? $item->getCode() : null,
            ],
        ]);
    }

    #[Route('/machine-rentals/partners/select/list', name: 'list_machine_rental_partner_select_list', methods: ['POST'])]
    public function listMachineRentalPartnerSelectList(
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
        $records = $partnerRepository->findForMachineRentalSelect($search, 20);

        return $this->response(true, [
            'template' => 'machine_rentals/select/list_machine_rental_partner_select_list.html.twig',
            'templateData' => ['records' => $records],
            'data' => [
                'search' => $search,
                'count' => count($records),
            ],
        ]);
    }

    #[Route('/machine-rentals/machines/select/list', name: 'list_machine_rental_machine_select_list', methods: ['POST'])]
    public function listMachineRentalMachineSelectList(
        Request $request,
        MachineRentalRepository $machineRentalRepository,
        MachineRepository $machineRepository,
    ): Response {
        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen gépkeresési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);
        $rentalIdRaw = $filters['rental_id'] ?? null;
        $rental = is_numeric($rentalIdRaw) ? $machineRentalRepository->find((int) $rentalIdRaw) : null;
        $records = $machineRepository->findForMachineRentalSelect($search, $rental?->getId(), 20);

        return $this->response(true, [
            'template' => 'machine_rentals/select/list_machine_rental_machine_select_list.html.twig',
            'templateData' => ['records' => $records],
            'data' => [
                'search' => $search,
                'count' => count($records),
            ],
        ]);
    }

    #[Route('/machine-rentals/save', name: 'save_machine_rentals', methods: ['POST'])]
    public function saveMachineRentals(
        Request $request,
        MachineRentalRepository $machineRentalRepository,
        PartnerRepository $partnerRepository,
        MachineRepository $machineRepository,
        WorksheetTypeRepository $worksheetTypeRepository,
        WorksheetStatusTypeRepository $worksheetStatusTypeRepository,
        EntityManagerInterface $entityManager,
        WorksheetNotificationService $worksheetNotificationService,
    ): Response {
        $postData = [];
        foreach ($request->request->all() as $key => $value) {
            $postData[$key] = is_string($value) ? trim($value) : $value;
        }

        $id = (int) ($postData['id'] ?? 0);
        $machineRental = $id > 0 ? $machineRentalRepository->find($id) : null;
        $isNew = $machineRental === null;

        $partnerId = (int) ($postData['partner_id'] ?? 0);
        $partner = $partnerId > 0 ? $partnerRepository->find($partnerId) : null;
        if (!$partner) {
            return $this->response(false, [
                'data' => [
                    'error' => 'A partner megadása kötelező.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $machineId = (int) ($postData['machine_id'] ?? 0);
        $machine = $machineId > 0 ? $machineRepository->find($machineId) : null;
        if (!$machine) {
            return $this->response(false, [
                'data' => [
                    'error' => 'A gép megadása kötelező.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $rentalStart = $this->parseDateTime((string) ($postData['datetime_rental_start'] ?? ''));
        $rentalEnd = $this->parseDateTime((string) ($postData['datetime_rental_end'] ?? ''));

        if ($rentalStart !== null && $rentalEnd !== null && $rentalEnd < $rentalStart) {
            return $this->response(false, [
                'data' => [
                    'error' => 'A bérlés vége nem lehet korábbi, mint a kezdete.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $status = $rentalEnd === null ? '1' : '0';
        $now = new \DateTimeImmutable();
        $userId = $this->getUser()?->getId();
        $createdWorksheetIds = [];
        $createdWorksheets = [];
        $worksheetNotificationResults = [];

        if ($rentalEnd === null) {
            $openRentalForMachine = $machineRentalRepository->findOpenByMachine($machine, $id);

            if ($openRentalForMachine && $openRentalForMachine->getId() !== $id) {
                return $this->response(false, [
                    'data' => [
                        'error' => sprintf(
                            'Erre a gĂ©pre mĂˇr van nyitott kĂ¶lcsĂ¶nzĂ©s: %s.',
                            $openRentalForMachine->getCode() ?? ('#' . $openRentalForMachine->getId())
                        ),
                    ],
                ], 'json', Response::HTTP_BAD_REQUEST);
            }
        }

        if ($isNew) {
            $machineRental = new MachineRental();
            $machineRental->setDatetimeAdd($now);
            $machineRental->setUidAdd($userId);
        }

        if ($machineRental->getCode() === null || $machineRental->getCode() === '') {
            $machineRental->setCode($this->generateRentalCode($machineRentalRepository, $now));
        }

        $machineRental
            ->setPartner($partner)
            ->setMachine($machine)
            ->setDatetimeRentalStart($rentalStart)
            ->setDatetimeRentalEnd($rentalEnd)
            ->setStatus($status)
            ->setDatetimeLast($now)
            ->setUidLast($userId);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $entityManager->persist($machineRental);
            $entityManager->flush();

            if ($isNew){
                $rentalId = $machineRental->getId() ?? 0;

                foreach (['MACHINE_HANDOVER', 'MACHINE_RETURN'] as $worksheetTypeCode) {
                    $worksheetType = $worksheetTypeRepository->findOneBy(['code' => $worksheetTypeCode]);
                    if (!$worksheetType) {
                        throw new \RuntimeException(sprintf('Hiányzó munkalap típus: %s', $worksheetTypeCode));
                    }

                    $worksheetStatusType = $worksheetStatusTypeRepository->findOneBy([
                        'worksheetType' => $worksheetType,
                        'code' => 'OPEN',
                    ]);
                    if (!$worksheetStatusType) {
                        throw new \RuntimeException(sprintf('Hiányzó OPEN státusz a munkalap típushoz: %s', $worksheetTypeCode));
                    }

                    $worksheet = new Worksheet();
                    $worksheet
                        ->setTitle(sprintf('%s - %s - %s', $worksheetType->getTitle(), $machine?->getTitle() ?? '', $partner?->getName() ?? ''))
                        ->setCode(sprintf('M%s-%08d', $now->format('Ym'), random_int(0, 99999999)))
                        ->setWorksheetType($worksheetType)
                        ->setWorksheetStatusType($worksheetStatusType)
                        ->setPartner($partner)
                        ->setMachineRental($machineRental)
                        ->setData([
                            'machine_rental_id' => $rentalId,
                            'machine_rental_code' => $machineRental->getCode(),
                            'partner_id' => $partner?->getId(),
                            'machine_id' => $machine?->getId(),
                            'datetime_rental_start' => $machineRental->getDatetimeRentalStart()?->format(\DateTimeInterface::ATOM),
                            'datetime_rental_end' => $machineRental->getDatetimeRentalEnd()?->format(\DateTimeInterface::ATOM),
                        ])
                        ->setDatetimeAdd($now)
                        ->setDatetimeOpen($now)
                        ->setUidAdd($userId);

                    $entityManager->persist($worksheet);
                    $createdWorksheets[$worksheetTypeCode] = $worksheet;
                }

                $entityManager->flush();

                foreach ($createdWorksheets as $worksheetTypeCode => $worksheet) {
                    $createdWorksheetIds[$worksheetTypeCode] = $worksheet->getId();
                }
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        foreach ($createdWorksheets as $worksheetTypeCode => $createdWorksheet) {
            $worksheetNotificationResults[$worksheetTypeCode] = $worksheetNotificationService
                ->sendForWorksheet($createdWorksheet, true);
        }

        return $this->response(true, [
            'data' => [
                'id' => $machineRental->getId(),
                'code' => $machineRental->getCode(),
                'mode' => $isNew ? 'insert' : 'update',
                'post' => $postData,
                'worksheet_ids' => $createdWorksheetIds,
                'worksheet_notifications' => $worksheetNotificationResults,
            ],
        ]);
    }

    #[Route('/machine-rentals/list', name: 'list_machine_rentals', methods: ['GET', 'POST'])]
    public function listMachineRentals(Request $request, MachineRentalRepository $machineRentalRepository): Response
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

    private function parseDateTime(string $value): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function generateRentalCode(MachineRentalRepository $machineRentalRepository, \DateTimeImmutable $now): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = sprintf('B%s-%08d', $now->format('Ym'), random_int(0, 99999999));

            if (!$machineRentalRepository->findOneBy(['code' => $code])) {
                return $code;
            }
        }

        throw new \RuntimeException('Nem sikerült egyedi gépkölcsönzés kódot generálni.');
    }

}
