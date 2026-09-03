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

class MachineRentalRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MachineRental::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(MachineRental $machineRental, bool $flush = true): void
    {
        $this->entityManager->persist($machineRental);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(MachineRental $machineRental, bool $flush = true): void
    {
        $this->entityManager->remove($machineRental);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function findOpenByMachine(Machine $machine, ?int $excludeRentalId = null): ?MachineRental
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('machineRental')
            ->from(MachineRental::class, 'machineRental')
            ->andWhere('machineRental.machine = :machine')
            ->andWhere('machineRental.datetimeRentalEnd IS NULL')
            ->setParameter('machine', $machine)
            ->setMaxResults(1);

        if ($excludeRentalId !== null && $excludeRentalId > 0) {
            $qb
                ->andWhere('machineRental.id != :excludeRentalId')
                ->setParameter('excludeRentalId', $excludeRentalId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findInfoByMachine(Machine $machine, int $limit = 10): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select([
                'machineRental.id AS id',
                'machineRental.code AS code',
                'machineRental.datetimeRentalStart AS datetime_rental_start',
                'machineRental.datetimeRentalEnd AS datetime_rental_end',
                'machineRental.status AS status',
                'partner.id AS partner_id',
                'partner.code AS partner_code',
                'partner.name AS partner_name',
            ])
            ->from(MachineRental::class, 'machineRental')
            ->leftJoin('machineRental.partner', 'partner')
            ->andWhere('machineRental.machine = :machine')
            ->setParameter('machine', $machine)
            ->orderBy('machineRental.datetimeRentalStart', 'DESC')
            ->addOrderBy('machineRental.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(MachineRental::class, 'machineRental')
            ->leftJoin('machineRental.partner', 'partner')
            ->leftJoin('machineRental.machine', 'machine')
            ->leftJoin('machine.machineCategory', 'machineCategory')
            ->leftJoin(WorksheetType::class, 'handoverWorksheetType', 'WITH', 'handoverWorksheetType.code = :handoverWorksheetTypeCode')
            ->leftJoin(Worksheet::class, 'handoverWorksheet', 'WITH', 'handoverWorksheet.machineRental = machineRental AND handoverWorksheet.worksheetType = handoverWorksheetType')
            ->leftJoin(WorksheetType::class, 'returnWorksheetType', 'WITH', 'returnWorksheetType.code = :returnWorksheetTypeCode')
            ->leftJoin(Worksheet::class, 'returnWorksheet', 'WITH', 'returnWorksheet.machineRental = machineRental AND returnWorksheet.worksheetType = returnWorksheetType')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = machineRental.uidAdd')
            ->leftJoin(User::class, 'userEditor', 'WITH', 'userEditor.id = machineRental.uidLast')
            ->setParameter('handoverWorksheetTypeCode', 'MACHINE_HANDOVER')
            ->setParameter('returnWorksheetTypeCode', 'MACHINE_RETURN');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('machineRental.code LIKE :search OR partner.name LIKE :search OR partner.code LIKE :search OR machine.title LIKE :search OR machine.code LIKE :search OR machineCategory.title LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === '1') {
            $qb->andWhere('machineRental.datetimeRentalEnd IS NULL');
        } elseif ($status === '0') {
            $qb->andWhere('machineRental.datetimeRentalEnd IS NOT NULL');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(machineRental.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'machineRental.id AS id',
                'machineRental.code AS code',
                'machineRental.datetimeRentalStart AS datetime_rental_start',
                'machineRental.datetimeRentalEnd AS datetime_rental_end',
                'machineRental.status AS status',
                'machineRental.uidAdd AS uid_add',
                'machineRental.uidLast AS uid_last',
                'machineRental.datetimeAdd AS datetime_add',
                'machineRental.datetimeLast AS datetime_last',
                'partner.id AS partner_id',
                'partner.code AS partner_code',
                'partner.name AS partner_name',
                'machine.id AS machine_id',
                'machine.title AS machine_title',
                'machine.code AS machine_code',
                'machineCategory.title AS machine_category_title',
                'handoverWorksheet.id AS handover_worksheet_id',
                'returnWorksheet.id AS return_worksheet_id',
                'userOwner.id AS user_owner_id',
                'userOwner.name AS user_owner_name',
                'userOwner.userName AS user_owner_username',
                'userEditor.id AS user_editor_id',
                'userEditor.name AS user_editor_name',
                'userEditor.userName AS user_editor_username',
            ])
            ->orderBy('machineRental.id', 'DESC')
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
