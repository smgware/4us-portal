<?php

namespace App\Repository;

use App\Entity\Machine;
use App\Entity\PartnerContact;
use App\Entity\User;
use App\Entity\Worksheet;
use App\Entity\WorksheetAttachment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class WorksheetRepository extends ServiceEntityRepository
{
    private const CLOSED_STATUS_CODES = ['CLOSED', 'RETURNED', 'REPAIRED_ISSUED'];

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

    public function findInfoByMachine(Machine $machine, array $worksheetTypeCodes = [], int $limit = 10): array
    {
        $machineId = (string) $machine->getId();
        $machineCode = (string) $machine->getCode();
        $machineConditions = [
            'machineRental.machine = :machine',
            'worksheet.data LIKE :machineIdQuoted',
            'worksheet.data LIKE :machineIdNumber',
        ];

        if ($machineCode !== '') {
            $machineConditions[] = 'worksheet.data LIKE :machineCode';
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select([
                'worksheet.id AS id',
                'worksheet.title AS title',
                'worksheet.code AS code',
                'worksheet.data AS data',
                'worksheet.datetimeAdd AS datetime_add',
                'worksheet.datetimeOpen AS datetime_open',
                'worksheet.datetimeClosed AS datetime_closed',
                'worksheet.datetimeLast AS datetime_last',
                'worksheetStatusType.id AS worksheet_status_type_id',
                'worksheetStatusType.code AS worksheet_status_type_code',
                'worksheetStatusType.title AS worksheet_status_type_title',
                'worksheetType.id AS worksheet_type_id',
                'worksheetType.title AS worksheet_type_title',
                'worksheetType.code AS worksheet_type_code',
                'partner.id AS partner_id',
                'partner.code AS partner_code',
                'partner.name AS partner_name',
                'machineRental.id AS rental_id',
                'machineRental.code AS rental_code',
            ])
            ->from(Worksheet::class, 'worksheet')
            ->leftJoin('worksheet.worksheetType', 'worksheetType')
            ->leftJoin('worksheet.worksheetStatusType', 'worksheetStatusType')
            ->leftJoin('worksheet.partner', 'partner')
            ->leftJoin('worksheet.machineRental', 'machineRental')
            ->andWhere(implode(' OR ', $machineConditions))
            ->setParameter('machine', $machine)
            ->setParameter('machineIdQuoted', '%"machine_id":"' . $machineId . '"%')
            ->setParameter('machineIdNumber', '%"machine_id":' . $machineId . '%')
            ->orderBy('worksheet.datetimeAdd', 'DESC')
            ->addOrderBy('worksheet.id', 'DESC')
            ->setMaxResults($limit);

        if ($machineCode !== '') {
            $qb->setParameter('machineCode', '%"machine_code":"' . $machineCode . '"%');
        }

        if ($worksheetTypeCodes !== []) {
            $qb
                ->andWhere('worksheetType.code IN (:worksheetTypeCodes)')
                ->setParameter('worksheetTypeCodes', $worksheetTypeCodes);
        }

        return $qb->getQuery()->getArrayResult();
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(Worksheet::class, 'worksheet')
            ->leftJoin('worksheet.worksheetType', 'worksheetType')
            ->leftJoin('worksheet.worksheetStatusType', 'worksheetStatusType')
            ->leftJoin('worksheet.partner', 'partner')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = worksheet.uidAdd')
            ->leftJoin(User::class, 'userEditor', 'WITH', 'userEditor.id = worksheet.uidLast');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('worksheet.title LIKE :search OR worksheet.code LIKE :search OR worksheetType.title LIKE :search OR worksheetType.code LIKE :search OR worksheetStatusType.title LIKE :search OR worksheetStatusType.code LIKE :search OR partner.name LIKE :search OR partner.code LIKE :search')
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

        $dateFrom = $this->parseDateTimeFilter((string) ($filters['date_from'] ?? ''));
        if ($dateFrom) {
            $qb
                ->andWhere('worksheet.datetimeAdd >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        $dateTo = $this->parseDateTimeFilter((string) ($filters['date_to'] ?? ''));
        if ($dateTo) {
            $qb
                ->andWhere('worksheet.datetimeAdd <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        $company = trim((string) ($filters['company'] ?? ''));
        if ($company !== '') {
            $qb
                ->andWhere('partner.name LIKE :company OR worksheet.data LIKE :company')
                ->setParameter('company', '%' . $company . '%');
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'action' || $status === 'open') {
            $qb
                ->andWhere('worksheetStatusType.code NOT IN (:closedStatusCodes)')
                ->setParameter('closedStatusCodes', self::CLOSED_STATUS_CODES);
        } elseif ($status === 'closed') {
            $qb
                ->andWhere('worksheetStatusType.code IN (:closedStatusCodes)')
                ->setParameter('closedStatusCodes', self::CLOSED_STATUS_CODES);
        } elseif ($status === 'new') {
            $qb
                ->andWhere('worksheetStatusType.code IN (:newStatusCodes)')
                ->setParameter('newStatusCodes', ['NEW', 'OPEN']);
        } elseif ($status === 'inprogress') {
            $qb
                ->andWhere('worksheetStatusType.code = :inprogressStatus')
                ->setParameter('inprogressStatus', 'UNDER_REPAIR');
        } elseif ($status === 'returned') {
            $qb
                ->andWhere('worksheetStatusType.code = :returnedStatus')
                ->setParameter('returnedStatus', 'RETURNED');
        } elseif ($status === 'repaired_issued') {
            $qb
                ->andWhere('worksheetStatusType.code = :repairedIssuedStatus')
                ->setParameter('repairedIssuedStatus', 'REPAIRED_ISSUED');
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

    public function findWorksheetAttachments(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(WorksheetAttachment::class, 'worksheetAttachment')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = worksheetAttachment.uidAdd')
            ->leftJoin(User::class, 'userEditor', 'WITH', 'userEditor.id = worksheetAttachment.uidLast');

        $worksheetIdRaw = $filters['worksheet_id'] ?? null;
        $worksheetId = is_numeric($worksheetIdRaw) ? (int) $worksheetIdRaw : 0;
        if ($worksheetId > 0) {
            $qb
                ->andWhere('IDENTITY(worksheetAttachment.worksheet) = :worksheetId')
                ->setParameter('worksheetId', $worksheetId);
        } else {
            $qb->andWhere('1 = 0');
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('(worksheetAttachment.name LIKE :search OR worksheetAttachment.description LIKE :search OR worksheetAttachment.status LIKE :search OR userOwner.name LIKE :search OR userOwner.userName LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $qb
                ->andWhere('worksheetAttachment.status = :attachmentStatus')
                ->setParameter('attachmentStatus', $status);
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(worksheetAttachment.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'worksheetAttachment.id AS id',
                'IDENTITY(worksheetAttachment.worksheet) AS worksheet_id',
                'worksheetAttachment.name AS name',
                'worksheetAttachment.description AS description',
                'worksheetAttachment.data AS data',
                'worksheetAttachment.status AS status',
                'worksheetAttachment.uidAdd AS uid_add',
                'worksheetAttachment.uidLast AS uid_last',
                'worksheetAttachment.datetimeAdd AS datetime_add',
                'worksheetAttachment.datetimeLast AS datetime_last',
                'worksheetAttachment.datetimeOpen AS datetime_open',
                'worksheetAttachment.datetimeClosed AS datetime_closed',
                'userOwner.id AS user_owner_id',
                'userOwner.name AS user_owner_name',
                'userOwner.userName AS user_owner_username',
                'userEditor.id AS user_editor_id',
                'userEditor.name AS user_editor_name',
                'userEditor.userName AS user_editor_username',
            ])
            ->orderBy('worksheetAttachment.datetimeAdd', 'DESC')
            ->addOrderBy('worksheetAttachment.id', 'DESC')
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


    public function findWorksheetPartnerContacts(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(PartnerContact::class, 'partnerContact');

        $partnerId = (int) ($filters['partner_id'] ?? 0);
        if ($partnerId > 0) {
            $qb
                ->andWhere('IDENTITY(partnerContact.partner) = :partnerId')
                ->setParameter('partnerId', $partnerId);
        } else {
            $qb->andWhere('1 = 0');
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('(partnerContact.name LIKE :search OR partnerContact.title LIKE :search OR partnerContact.email LIKE :search OR partnerContact.phone LIKE :search OR partnerContact.description LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(partnerContact.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'partnerContact.id AS id',
                'IDENTITY(partnerContact.partner) AS partner_id',
                'partnerContact.name AS name',
                'partnerContact.title AS title',
                'partnerContact.email AS email',
                'partnerContact.phone AS phone',
                'partnerContact.description AS description',
                'partnerContact.defaultContact AS default_contact',
            ])
            ->orderBy('partnerContact.defaultContact', 'DESC')
            ->addOrderBy('partnerContact.name', 'ASC')
            ->addOrderBy('partnerContact.id', 'ASC')
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

    private function parseDateTimeFilter(string $value): ?\DateTimeImmutable
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
}
