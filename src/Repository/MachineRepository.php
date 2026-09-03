<?php

namespace App\Repository;

use App\Entity\Machine;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class MachineRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Machine::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(Machine $machine, bool $flush = true): void
    {
        $this->entityManager->persist($machine);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(Machine $machine, bool $flush = true): void
    {
        $this->entityManager->remove($machine);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function findAvailableForRental(?int $excludeRentalId = null): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('machine')
            ->from(Machine::class, 'machine')
            ->andWhere('NOT EXISTS (SELECT openRental.id FROM App\Entity\MachineRental openRental WHERE openRental.machine = machine AND openRental.datetimeRentalEnd IS NULL' . ($excludeRentalId !== null && $excludeRentalId > 0 ? ' AND openRental.id != :excludeRentalId' : '') . ')')
            ->orderBy('machine.code', 'ASC');

        if ($excludeRentalId !== null && $excludeRentalId > 0) {
            $qb->setParameter('excludeRentalId', $excludeRentalId);
        }

        return $qb->getQuery()->getResult();
    }

    public function findForWorksheetSelect(string $search, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $qb = $this->createQueryBuilder('machine')
            ->select([
                'machine.id AS id',
                'machine.title AS title',
                'machine.code AS code',
                'machineCategory.title AS category_title',
                'machineCategory.code AS category_code',
            ])
            ->leftJoin('machine.machineCategory', 'machineCategory');

        $search = trim($search);
        if ($search !== '') {
            $qb
                ->andWhere('(machine.title LIKE :search OR machine.code LIKE :search OR machineCategory.title LIKE :search OR machineCategory.code LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb
            ->orderBy('machine.title', 'ASC')
            ->addOrderBy('machine.code', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function findForMachineRentalSelect(
        string $search,
        ?int $excludeRentalId = null,
        int $limit = 20,
    ): array {
        $limit = max(1, min(50, $limit));
        $openRentalCondition = 'openRental.machine = machine AND openRental.datetimeRentalEnd IS NULL';

        if ($excludeRentalId !== null && $excludeRentalId > 0) {
            $openRentalCondition .= ' AND openRental.id != :excludeRentalId';
        }

        $qb = $this->createQueryBuilder('machine')
            ->select([
                'machine.id AS id',
                'machine.title AS title',
                'machine.code AS code',
                'machineCategory.title AS category_title',
                'machineCategory.code AS category_code',
            ])
            ->leftJoin('machine.machineCategory', 'machineCategory')
            ->andWhere('NOT EXISTS (SELECT openRental.id FROM App\Entity\MachineRental openRental WHERE ' . $openRentalCondition . ')');

        if ($excludeRentalId !== null && $excludeRentalId > 0) {
            $qb->setParameter('excludeRentalId', $excludeRentalId);
        }

        $search = trim($search);
        if ($search !== '') {
            $qb
                ->andWhere('(machine.title LIKE :search OR machine.code LIKE :search OR machineCategory.title LIKE :search OR machineCategory.code LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb
            ->orderBy('machine.title', 'ASC')
            ->addOrderBy('machine.code', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(Machine::class, 'machine')
            ->leftJoin('machine.machineCategory', 'machineCategory')
            ->leftJoin('machine.project', 'project')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = machine.uidAdd')
            ->leftJoin(User::class, 'userEditor', 'WITH', 'userEditor.id = machine.uidLast');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('(machine.title LIKE :search OR machine.code LIKE :search OR machineCategory.title LIKE :search OR machineCategory.code LIKE :search OR project.name LIKE :search OR project.code LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $machineCategoryId = $filters['machines_category'] ?? $filters['machine_category_id'] ?? null;
        if (is_numeric($machineCategoryId) && (int) $machineCategoryId > 0) {
            $qb
                ->andWhere('machineCategory.id = :machineCategoryId')
                ->setParameter('machineCategoryId', (int) $machineCategoryId);
        }

        $projectId = $filters['project_id'] ?? null;
        if (is_numeric($projectId) && (int) $projectId > 0) {
            $qb
                ->andWhere('project.id = :projectId')
                ->setParameter('projectId', (int) $projectId);
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type === 'free') {
            $qb->andWhere('NOT EXISTS (SELECT freeOpenRental.id FROM App\Entity\MachineRental freeOpenRental WHERE freeOpenRental.machine = machine AND freeOpenRental.datetimeRentalEnd IS NULL)');
        } elseif ($type === 'issued') {
            $qb
                ->andWhere('EXISTS (SELECT issuedOpenRental.id FROM App\Entity\MachineRental issuedOpenRental WHERE issuedOpenRental.machine = machine AND issuedOpenRental.datetimeRentalEnd IS NULL)')
                ->andWhere("NOT EXISTS (SELECT issuedErrorWorksheet.id FROM App\Entity\Worksheet issuedErrorWorksheet INNER JOIN issuedErrorWorksheet.worksheetType issuedErrorWorksheetType INNER JOIN issuedErrorWorksheet.worksheetStatusType issuedErrorWorksheetStatusType WHERE issuedErrorWorksheetStatusType.code NOT IN ('CLOSED', 'RETURNED', 'REPAIRED_ISSUED') AND issuedErrorWorksheetType.code IN ('ERROR_REPORTING', 'ERROR_REPORT') AND (IDENTITY(issuedErrorWorksheet.machineRental) IN (SELECT issuedErrorRental.id FROM App\Entity\MachineRental issuedErrorRental WHERE issuedErrorRental.machine = machine) OR issuedErrorWorksheet.data LIKE CONCAT('%\"machine_id\":\"', machine.id, '\"%') OR issuedErrorWorksheet.data LIKE CONCAT('%\"machine_id\":', machine.id, '%') OR issuedErrorWorksheet.data LIKE CONCAT('%\"machine_code\":\"', machine.code, '\"%')))");
        } elseif ($type === 'repair') {
            $qb
                ->andWhere('EXISTS (SELECT repairOpenRental.id FROM App\Entity\MachineRental repairOpenRental WHERE repairOpenRental.machine = machine AND repairOpenRental.datetimeRentalEnd IS NULL)')
                ->andWhere("EXISTS (SELECT repairErrorWorksheet.id FROM App\Entity\Worksheet repairErrorWorksheet INNER JOIN repairErrorWorksheet.worksheetType repairErrorWorksheetType INNER JOIN repairErrorWorksheet.worksheetStatusType repairErrorWorksheetStatusType WHERE repairErrorWorksheetStatusType.code NOT IN ('CLOSED', 'RETURNED', 'REPAIRED_ISSUED') AND repairErrorWorksheetType.code IN ('ERROR_REPORTING', 'ERROR_REPORT') AND (IDENTITY(repairErrorWorksheet.machineRental) IN (SELECT repairErrorRental.id FROM App\Entity\MachineRental repairErrorRental WHERE repairErrorRental.machine = machine) OR repairErrorWorksheet.data LIKE CONCAT('%\"machine_id\":\"', machine.id, '\"%') OR repairErrorWorksheet.data LIKE CONCAT('%\"machine_id\":', machine.id, '%') OR repairErrorWorksheet.data LIKE CONCAT('%\"machine_code\":\"', machine.code, '\"%')))");
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(machine.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'machine.id AS id',
                'machine.title AS title',
                'machine.code AS code',
                'machine.data AS data',
                'machine.uidAdd AS uid_add',
                'machine.uidLast AS uid_last',
                'machine.datetimeAdd AS datetime_add',
                'machine.datetimeLast AS datetime_last',
                'machine.status AS status',
                'machineCategory.id AS machine_category_id',
                'machineCategory.title AS machine_category_title',
                'machineCategory.code AS machine_category_code',
                'project.id AS project_id',
                'project.name AS project_name',
                'project.code AS project_code',
                'userOwner.id AS user_owner_id',
                'userOwner.name AS user_owner_name',
                'userOwner.userName AS user_owner_username',
                'userEditor.id AS user_editor_id',
                'userEditor.name AS user_editor_name',
                'userEditor.userName AS user_editor_username',
                "(SELECT COUNT(DISTINCT openRental.id) FROM App\Entity\MachineRental openRental WHERE openRental.machine = machine AND openRental.datetimeRentalEnd IS NULL) AS open_rental_count",
                "(SELECT COUNT(DISTINCT errorWorksheet.id) FROM App\Entity\Worksheet errorWorksheet INNER JOIN errorWorksheet.worksheetType errorWorksheetType INNER JOIN errorWorksheet.worksheetStatusType errorWorksheetStatusType WHERE errorWorksheetStatusType.code NOT IN ('CLOSED', 'RETURNED', 'REPAIRED_ISSUED') AND errorWorksheetType.code IN ('ERROR_REPORTING', 'ERROR_REPORT') AND (IDENTITY(errorWorksheet.machineRental) IN (SELECT errorRental.id FROM App\Entity\MachineRental errorRental WHERE errorRental.machine = machine) OR errorWorksheet.data LIKE CONCAT('%\"machine_id\":\"', machine.id, '\"%') OR errorWorksheet.data LIKE CONCAT('%\"machine_id\":', machine.id, '%') OR errorWorksheet.data LIKE CONCAT('%\"machine_code\":\"', machine.code, '\"%'))) AS open_error_reporting_count",
            ])
            ->orderBy('machine.id', 'DESC')
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
