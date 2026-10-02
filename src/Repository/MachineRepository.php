<?php

namespace App\Repository;

use App\Entity\Machine;
use App\Entity\MachineRental;
use App\Entity\User;
use App\Entity\Worksheet;
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

    public function findForMachineRentalSelect(
        string $search,
        ?int $excludeRentalId = null,
        int $limit = 20,
    ): array {
        $limit = max(1, min(50, $limit));
        $openRentalCondition = 'openRental.machine = machine AND openRental.status = :openStatus';
        if ($excludeRentalId !== null && $excludeRentalId > 0) {
            $openRentalCondition .= ' AND openRental.id != :excludeRentalId';
        }

        $qb = $this->createQueryBuilder('machine')
            ->select([
                'machine.id AS id',
                'machine.title AS title',
                'machine.code AS code',
                'machine.status AS status',
                'machineCategory.title AS category_title',
                'machineCategory.code AS category_code',
                'companySite.title AS company_site_title',
                'companySite.code AS company_site_code',
                '(SELECT COUNT(openRental.id) FROM ' . MachineRental::class . ' openRental WHERE ' . $openRentalCondition . ') AS open_rental_count',
            ])
            ->leftJoin('machine.machineCategory', 'machineCategory')
            ->leftJoin('machine.companySite', 'companySite')
            ->setParameter('openStatus', '1');

        if ($excludeRentalId !== null && $excludeRentalId > 0) {
            $qb->setParameter('excludeRentalId', $excludeRentalId);
        }

        $search = trim($search);
        if ($search !== '') {
            $qb
                ->andWhere('(machine.title LIKE :search OR machine.code LIKE :search OR machineCategory.title LIKE :search OR machineCategory.code LIKE :search OR companySite.title LIKE :search OR companySite.code LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb
            ->orderBy('machine.status', 'DESC')
            ->addOrderBy('machine.title', 'ASC')
            ->addOrderBy('machine.code', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function findForWorksheetSelect(string $search, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $qb = $this->createQueryBuilder('machine')
            ->select([
                'machine.id AS id',
                'machine.title AS title',
                'machine.code AS code',
                'machine.status AS status',
                'machineCategory.title AS category_title',
                'machineCategory.code AS category_code',
                'companySite.title AS company_site_title',
                'companySite.code AS company_site_code',
            ])
            ->leftJoin('machine.machineCategory', 'machineCategory')
            ->leftJoin('machine.companySite', 'companySite');

        $search = trim($search);
        if ($search !== '') {
            $qb
                ->andWhere('(machine.title LIKE :search OR machine.code LIKE :search OR machineCategory.title LIKE :search OR machineCategory.code LIKE :search OR companySite.title LIKE :search OR companySite.code LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb
            ->orderBy('machine.status', 'DESC')
            ->addOrderBy('machine.title', 'ASC')
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
            ->leftJoin('machine.companySite', 'companySite')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = machine.uidAdd')
            ->leftJoin(User::class, 'userEditor', 'WITH', 'userEditor.id = machine.uidLast');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('(machine.title LIKE :search OR machine.code LIKE :search OR machineCategory.title LIKE :search OR machineCategory.code LIKE :search OR companySite.title LIKE :search OR companySite.code LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $machineCategoryId = $filters['machines_category'] ?? $filters['machine_category_id'] ?? null;
        if (is_numeric($machineCategoryId) && (int) $machineCategoryId > 0) {
            $qb
                ->andWhere('machineCategory.id = :machineCategoryId')
                ->setParameter('machineCategoryId', (int) $machineCategoryId);
        }

        $companySiteId = $filters['company_site_id'] ?? null;
        if (is_numeric($companySiteId) && (int) $companySiteId > 0) {
            $qb
                ->andWhere('companySite.id = :companySiteId')
                ->setParameter('companySiteId', (int) $companySiteId);
        }

        $finalWorksheetStatusCodes = "'CLOSED', 'TAKEN_BACK', 'REPAIRED_RETURNED', 'RETURNED', 'REPAIRED_ISSUED'";
        $openRentalExists = "EXISTS (SELECT rentalFilter.id FROM " . MachineRental::class . " rentalFilter WHERE rentalFilter.machine = machine AND rentalFilter.status = '1' AND NOT EXISTS (SELECT returnFilter.id FROM " . Worksheet::class . " returnFilter INNER JOIN returnFilter.worksheetType returnFilterType INNER JOIN returnFilter.worksheetStatusType returnFilterStatus WHERE returnFilter.machineRentalId = rentalFilter.id AND returnFilter.status = '1' AND returnFilterType.code = 'MACHINE_RETURN' AND returnFilterStatus.code IN (" . $finalWorksheetStatusCodes . ")))";
        $openErrorExists = "EXISTS (SELECT errorFilter.id FROM " . Worksheet::class . " errorFilter INNER JOIN errorFilter.worksheetType errorFilterType INNER JOIN errorFilter.worksheetStatusType errorFilterStatus WHERE errorFilter.status = '1' AND errorFilterStatus.code NOT IN (" . $finalWorksheetStatusCodes . ") AND errorFilterType.code IN ('ERROR_REPORTING', 'ERROR_REPORT') AND errorFilter.machineId = machine.id)";

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type === 'free') {
            $qb
                ->andWhere('NOT ' . $openRentalExists)
                ->andWhere('NOT ' . $openErrorExists);
        } elseif ($type === 'issued') {
            $qb
                ->andWhere($openRentalExists)
                ->andWhere('NOT ' . $openErrorExists);
        } elseif ($type === 'repair') {
            $qb->andWhere($openErrorExists);
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
                'companySite.id AS company_site_id',
                'companySite.title AS company_site_title',
                'companySite.code AS company_site_code',
                'userOwner.name AS user_owner_name',
                'userOwner.userName AS user_owner_username',
                'userEditor.name AS user_editor_name',
                'userEditor.userName AS user_editor_username',
                "(SELECT COUNT(openRental.id) FROM " . MachineRental::class . " openRental WHERE openRental.machine = machine AND openRental.status = '1' AND NOT EXISTS (SELECT returnedRental.id FROM " . Worksheet::class . " returnedRental INNER JOIN returnedRental.worksheetType returnedRentalType INNER JOIN returnedRental.worksheetStatusType returnedRentalStatus WHERE returnedRental.machineRentalId = openRental.id AND returnedRental.status = '1' AND returnedRentalType.code = 'MACHINE_RETURN' AND returnedRentalStatus.code IN (" . $finalWorksheetStatusCodes . "))) AS open_rental_count",
                "(SELECT COUNT(errorWorksheet.id) FROM " . Worksheet::class . " errorWorksheet INNER JOIN errorWorksheet.worksheetType errorWorksheetType INNER JOIN errorWorksheet.worksheetStatusType errorWorksheetStatusType WHERE errorWorksheet.status = '1' AND errorWorksheetStatusType.code NOT IN (" . $finalWorksheetStatusCodes . ") AND errorWorksheetType.code IN ('ERROR_REPORTING', 'ERROR_REPORT') AND errorWorksheet.machineId = machine.id) AS open_error_reporting_count",
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
