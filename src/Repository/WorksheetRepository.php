<?php

namespace App\Repository;

use App\Entity\Machine;
use App\Entity\MachineRental;
use App\Entity\User;
use App\Entity\Worksheet;
use App\Entity\WorksheetType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class WorksheetRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Worksheet::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(Worksheet $worksheet, bool $flush = true): void
    {
        $this->entityManager->persist($worksheet);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(Worksheet $worksheet, bool $flush = true): void
    {
        $this->entityManager->remove($worksheet);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function findOneByRentalAndType(int $machineRentalId, WorksheetType $worksheetType): ?Worksheet
    {
        return $this->createQueryBuilder('worksheet')
            ->andWhere('worksheet.machineRentalId = :machineRentalId')
            ->andWhere('worksheet.worksheetType = :worksheetType')
            ->andWhere('worksheet.status = :active')
            ->setParameter('machineRentalId', $machineRentalId)
            ->setParameter('worksheetType', $worksheetType)
            ->setParameter('active', '1')
            ->orderBy('worksheet.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(Worksheet::class, 'worksheet')
            ->innerJoin('worksheet.worksheetType', 'worksheetType')
            ->innerJoin('worksheet.worksheetStatusType', 'worksheetStatusType')
            ->leftJoin('worksheet.partner', 'partner')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = worksheet.uidAdd')
            ->leftJoin(User::class, 'userEditor', 'WITH', 'userEditor.id = worksheet.uidLast')
            ->andWhere('worksheet.status = :active')
            ->setParameter('active', '1');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('(worksheet.title LIKE :search OR worksheet.code LIKE :search OR worksheetType.title LIKE :search OR worksheetType.code LIKE :search OR worksheetStatusType.title LIKE :search OR worksheetStatusType.code LIKE :search OR partner.name LIKE :search OR partner.code LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $worksheetTypeId = (int) ($filters['worksheet_type_id'] ?? 0);
        if ($worksheetTypeId > 0) {
            $qb
                ->andWhere('worksheetType.id = :worksheetTypeId')
                ->setParameter('worksheetTypeId', $worksheetTypeId);
        }

        $allowedWorksheetTypeCodes = $filters['allowed_worksheet_type_codes'] ?? [];
        if (is_array($allowedWorksheetTypeCodes) && $allowedWorksheetTypeCodes !== []) {
            $qb
                ->andWhere('worksheetType.code IN (:allowedWorksheetTypeCodes)')
                ->setParameter('allowedWorksheetTypeCodes', $allowedWorksheetTypeCodes);
        }

        $worksheetStatusTypeCode = trim((string) ($filters['worksheet_status_type_code'] ?? ''));
        if ($worksheetStatusTypeCode !== '') {
            $qb
                ->andWhere('worksheetStatusType.code = :worksheetStatusTypeCode')
                ->setParameter('worksheetStatusTypeCode', $worksheetStatusTypeCode);
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(worksheet.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'worksheet.id AS id',
                'worksheet.title AS title',
                'worksheet.code AS code',
                'worksheet.data AS data',
                'worksheet.machineId AS machine_id',
                'worksheet.machineRentalId AS machine_rental_id',
                'worksheet.datetimeAdd AS datetime_add',
                'worksheet.datetimeLast AS datetime_last',
                'worksheet.datetimeOpen AS datetime_open',
                'worksheet.datetimeClosed AS datetime_closed',
                'worksheetType.id AS worksheet_type_id',
                'worksheetType.title AS worksheet_type_title',
                'worksheetType.code AS worksheet_type_code',
                'worksheetStatusType.id AS worksheet_status_type_id',
                'worksheetStatusType.title AS worksheet_status_type_title',
                'worksheetStatusType.code AS worksheet_status_type_code',
                'partner.id AS partner_id',
                'partner.name AS partner_name',
                'partner.code AS partner_code',
                'userOwner.name AS user_owner_name',
                'userOwner.userName AS user_owner_username',
                'userEditor.name AS user_editor_name',
                'userEditor.userName AS user_editor_username',
            ])
            ->orderBy('worksheet.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getArrayResult();

        $records = $this->appendLatestPartnerProjects($records);

        return [
            'records' => $records,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'itemsPerPage' => $itemsPerPage,
        ];
    }

    private function appendLatestPartnerProjects(array $records): array
    {
        $partnerIds = array_values(array_unique(array_filter(array_map(
            static fn (array $record): int => (int) ($record['partner_id'] ?? 0),
            $records,
        ))));
        if ($partnerIds === []) {
            return $records;
        }

        $rentals = $this->entityManager->createQueryBuilder()
            ->select([
                'partner.id AS partner_id',
                'project.name AS project_name',
                'project.code AS project_code',
            ])
            ->from(MachineRental::class, 'machineRental')
            ->innerJoin('machineRental.partner', 'partner')
            ->leftJoin('machineRental.project', 'project')
            ->andWhere('partner.id IN (:partnerIds)')
            ->setParameter('partnerIds', $partnerIds)
            ->orderBy('partner.id', 'ASC')
            ->addOrderBy('machineRental.datetimeRentalStart', 'DESC')
            ->addOrderBy('machineRental.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $projectsByPartnerId = [];
        foreach ($rentals as $rental) {
            $partnerId = (int) $rental['partner_id'];
            if (!array_key_exists($partnerId, $projectsByPartnerId)) {
                $projectsByPartnerId[$partnerId] = [
                    'name' => $rental['project_name'],
                    'code' => $rental['project_code'],
                ];
            }
        }

        foreach ($records as &$record) {
            $project = $projectsByPartnerId[(int) ($record['partner_id'] ?? 0)] ?? null;
            $record['project_name'] = $project['name'] ?? null;
            $record['project_code'] = $project['code'] ?? null;
        }
        unset($record);

        return $records;
    }

    /**
     * @param string[] $worksheetTypeCodes
     *
     * @return array<string, int>
     */
    public function countNewOrOpenByWorksheetTypeCodes(array $worksheetTypeCodes): array
    {
        if ($worksheetTypeCodes === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('worksheet')
            ->select('worksheetType.code AS worksheet_type_code, COUNT(worksheet.id) AS worksheet_count')
            ->innerJoin('worksheet.worksheetType', 'worksheetType')
            ->innerJoin('worksheet.worksheetStatusType', 'worksheetStatusType')
            ->andWhere('worksheet.status = :active')
            ->andWhere('worksheetType.code IN (:worksheetTypeCodes)')
            ->andWhere('worksheetStatusType.code IN (:openStatusCodes)')
            ->setParameter('active', '1')
            ->setParameter('worksheetTypeCodes', $worksheetTypeCodes)
            ->setParameter('openStatusCodes', ['NEW', 'OPEN'])
            ->groupBy('worksheetType.code')
            ->getQuery()
            ->getArrayResult();

        $counts = array_fill_keys($worksheetTypeCodes, 0);
        foreach ($rows as $row) {
            $counts[(string) $row['worksheet_type_code']] = (int) $row['worksheet_count'];
        }

        return $counts;
    }

    /**
     * @param object $machine Any machine-like object exposing getId().
     * @param string[] $worksheetTypeCodes
     */
    public function findInfoByMachine(object $machine, array $worksheetTypeCodes, int $limit = 10): array
    {
        if (!method_exists($machine, 'getId') || !$machine->getId() || $worksheetTypeCodes === []) {
            return [];
        }

        return $this->createQueryBuilder('worksheet')
            ->select([
                'worksheet.id AS id',
                'worksheet.code AS code',
                'worksheet.title AS title',
                'worksheet.datetimeAdd AS datetime_add',
                'worksheet.datetimeLast AS datetime_last',
                'worksheetStatusType.title AS status_title',
                'worksheetStatusType.code AS status_code',
            ])
            ->innerJoin('worksheet.worksheetType', 'worksheetType')
            ->innerJoin('worksheet.worksheetStatusType', 'worksheetStatusType')
            ->andWhere('worksheet.machineId = :machineId')
            ->andWhere('worksheetType.code IN (:worksheetTypeCodes)')
            ->andWhere('worksheet.status = :active')
            ->setParameter('machineId', (int) $machine->getId())
            ->setParameter('worksheetTypeCodes', $worksheetTypeCodes)
            ->setParameter('active', '1')
            ->orderBy('worksheet.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @param string[] $worksheetTypeCodes
     */
    public function findInfoPageByMachine(
        Machine $machine,
        array $worksheetTypeCodes,
        array $filters,
        int $page,
        int $itemsPerPage = 5,
    ): array {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);
        $machineId = (int) $machine->getId();
        $machineCode = trim((string) $machine->getCode());

        $qb = $this->createQueryBuilder('worksheet')
            ->innerJoin('worksheet.worksheetType', 'worksheetType')
            ->innerJoin('worksheet.worksheetStatusType', 'worksheetStatusType')
            ->leftJoin('worksheet.partner', 'partner')
            ->leftJoin(
                MachineRental::class,
                'machineRental',
                'WITH',
                'machineRental.id = worksheet.machineRentalId',
            )
            ->leftJoin('machineRental.partner', 'rentalPartner')
            ->andWhere('worksheetType.code IN (:worksheetTypeCodes)')
            ->andWhere('worksheet.status = :active')
            ->setParameter('machineId', $machineId)
            ->setParameter('machine', $machine)
            ->setParameter('machineIdQuoted', '%"machine_id":"' . $machineId . '"%')
            ->setParameter('machineIdQuotedSpaced', '%"machine_id": "' . $machineId . '"%')
            ->setParameter('machineIdNumber', '%"machine_id":' . $machineId . ',%')
            ->setParameter('machineIdNumberSpaced', '%"machine_id": ' . $machineId . ',%')
            ->setParameter('machineIdNumberLast', '%"machine_id":' . $machineId . '}%')
            ->setParameter('machineIdNumberLastSpaced', '%"machine_id": ' . $machineId . '}%')
            ->setParameter('worksheetTypeCodes', $worksheetTypeCodes)
            ->setParameter('active', '1');

        $machineConditions = [
            'worksheet.machineId = :machineId',
            'machineRental.machine = :machine',
            'worksheet.data LIKE :machineIdQuoted',
            'worksheet.data LIKE :machineIdQuotedSpaced',
            'worksheet.data LIKE :machineIdNumber',
            'worksheet.data LIKE :machineIdNumberSpaced',
            'worksheet.data LIKE :machineIdNumberLast',
            'worksheet.data LIKE :machineIdNumberLastSpaced',
        ];

        if ($machineCode !== '') {
            $machineConditions[] = 'worksheet.data LIKE :machineCode';
            $machineConditions[] = 'worksheet.data LIKE :machineCodeSpaced';
            $qb
                ->setParameter('machineCode', '%"machine_code":"' . $machineCode . '"%')
                ->setParameter('machineCodeSpaced', '%"machine_code": "' . $machineCode . '"%');
        }

        $qb->andWhere('(' . implode(' OR ', $machineConditions) . ')');

        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);
        if ($search !== '') {
            $qb
                ->andWhere('(worksheet.code LIKE :search OR worksheet.title LIKE :search OR partner.name LIKE :search OR partner.code LIKE :search OR rentalPartner.name LIKE :search OR rentalPartner.code LIKE :search OR machineRental.code LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $partnerId = (int) ($filters['partner_id'] ?? 0);
        if ($partnerId > 0) {
            $qb
                ->andWhere('(partner.id = :partnerId OR rentalPartner.id = :partnerId)')
                ->setParameter('partnerId', $partnerId);
        }

        $typeCode = trim((string) ($filters['worksheet_type_code'] ?? ''));
        if ($typeCode !== '' && in_array($typeCode, $worksheetTypeCodes, true)) {
            $qb
                ->andWhere('worksheetType.code = :worksheetTypeCode')
                ->setParameter('worksheetTypeCode', $typeCode);
        }

        $totalRecords = (int) (clone $qb)
            ->select('COUNT(worksheet.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'worksheet.id AS id',
                'worksheet.code AS code',
                'worksheet.title AS title',
                'worksheet.data AS data',
                'worksheet.datetimeAdd AS datetime_add',
                'worksheet.datetimeLast AS datetime_last',
                'worksheetType.code AS worksheet_type_code',
                'worksheetType.title AS worksheet_type_title',
                'worksheetStatusType.code AS worksheet_status_type_code',
                'worksheetStatusType.title AS worksheet_status_type_title',
                'COALESCE(partner.id, rentalPartner.id) AS partner_id',
                'COALESCE(partner.code, rentalPartner.code) AS partner_code',
                'COALESCE(partner.name, rentalPartner.name) AS partner_name',
                'machineRental.code AS rental_code',
            ])
            ->orderBy('worksheet.datetimeAdd', 'DESC')
            ->addOrderBy('worksheet.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getArrayResult();

        return [
            'records' => $records,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'itemsPerPage' => $itemsPerPage,
        ];
    }
}
