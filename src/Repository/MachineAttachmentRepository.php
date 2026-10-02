<?php

namespace App\Repository;

use App\Entity\Machine;
use App\Entity\MachineAttachment;
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

    public function save(MachineAttachment $attachment, bool $flush = true): void
    {
        $this->entityManager->persist($attachment);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(MachineAttachment $attachment, bool $flush = true): void
    {
        $this->entityManager->remove($attachment);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * @return array{records: MachineAttachment[], page: int, totalPages: int, totalRecords: int, itemsPerPage: int, hasMore: bool}
     */
    public function findPage(Machine $machine, array $filters, int $page, int $itemsPerPage = 5): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);
        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);

        $qb = $this->createQueryBuilder('attachment')
            ->andWhere('attachment.machine = :machine')
            ->andWhere('attachment.status = :active')
            ->setParameter('machine', $machine)
            ->setParameter('active', '1');

        if ($search !== '') {
            $qb
                ->andWhere('(attachment.name LIKE :search OR attachment.description LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $totalRecords = (int) (clone $qb)
            ->select('COUNT(attachment.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->orderBy('attachment.datetimeAdd', 'DESC')
            ->addOrderBy('attachment.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return [
            'records' => $records,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'itemsPerPage' => $itemsPerPage,
            'hasMore' => $offset + count($records) < $totalRecords,
        ];
    }
}
