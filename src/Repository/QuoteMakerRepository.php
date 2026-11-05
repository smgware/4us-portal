<?php

namespace App\Repository;

use App\Entity\QuoteMakerMachine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class QuoteMakerRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuoteMakerMachine::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(QuoteMakerMachine $quoteMakerMachine, bool $flush = true): void
    {
        $this->entityManager->persist($quoteMakerMachine);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function findAllQuoteMachines(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->createQueryBuilder('quoteMakerMachine');
        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 255);

        if ($search !== '') {
            $qb
                ->andWhere('(quoteMakerMachine.title LIKE :search OR quoteMakerMachine.code LIKE :search OR quoteMakerMachine.data LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(quoteMakerMachine.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);

        $records = $qb
            ->orderBy('quoteMakerMachine.datetimeAdd', 'DESC')
            ->addOrderBy('quoteMakerMachine.id', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return [
            'records' => $records,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'itemsPerPage' => $itemsPerPage,
        ];
    }

    public function findForQuoteSelect(string $search, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $qb = $this->createQueryBuilder('machine')
            ->select([
                'machine.id AS id',
                'machine.title AS title',
                'machine.code AS code',
                'machine.data AS data',
            ])
            ->orderBy('machine.title', 'ASC')
            ->addOrderBy('machine.id', 'DESC')
            ->setMaxResults($limit);

        $search = trim($search);
        if ($search !== '') {
            $qb
                ->andWhere('(machine.title LIKE :search OR machine.code LIKE :search OR machine.data LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getArrayResult();
    }
}
