<?php

namespace App\Repository;

use App\Entity\CompareMachinesQuote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class CompareMachinesQuoteRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompareMachinesQuote::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(CompareMachinesQuote $quote, bool $flush = true): void
    {
        $this->entityManager->persist($quote);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('quote')
            ->from(CompareMachinesQuote::class, 'quote');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('quote.title LIKE :search OR quote.code LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(quote.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->orderBy('quote.id', 'DESC')
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
        ];
    }
}
