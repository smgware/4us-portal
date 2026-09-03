<?php

namespace App\Repository;

use App\Entity\Machine;
use App\Entity\MachineAttachment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class MachineAttachmentRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MachineAttachment::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(MachineAttachment $machineAttachment, bool $flush = true): void
    {
        $this->entityManager->persist($machineAttachment);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(MachineAttachment $machineAttachment, bool $flush = true): void
    {
        $this->entityManager->remove($machineAttachment);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function findList(Machine $machine, array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->createQueryBuilder('machineAttachment')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = machineAttachment.uidAdd')
            ->andWhere('machineAttachment.machine = :machine')
            ->andWhere('machineAttachment.status = :status')
            ->setParameter('machine', $machine)
            ->setParameter('status', '1');

        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);
        if ($search !== '') {
            $qb
                ->andWhere('(machineAttachment.name LIKE :search OR machineAttachment.description LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(machineAttachment.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);

        $records = $qb
            ->select([
                'machineAttachment.id AS id',
                'machineAttachment.name AS name',
                'machineAttachment.description AS description',
                'machineAttachment.data AS data',
                'machineAttachment.datetimeAdd AS datetime_add',
                'userOwner.name AS user_owner_name',
                'userOwner.userName AS user_owner_username',
            ])
            ->orderBy('machineAttachment.datetimeAdd', 'DESC')
            ->addOrderBy('machineAttachment.id', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
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
