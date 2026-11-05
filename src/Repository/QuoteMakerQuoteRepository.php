<?php

namespace App\Repository;

use App\Entity\QuoteMakerQuote;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class QuoteMakerQuoteRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuoteMakerQuote::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(QuoteMakerQuote $quote, bool $flush = true): void
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
        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 255);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(QuoteMakerQuote::class, 'quote')
            ->leftJoin(User::class, 'creator', 'WITH', 'creator.id = quote.uidAdd')
            ->leftJoin(User::class, 'editor', 'WITH', 'editor.id = quote.uidLast');

        if ($search !== '') {
            $qb
                ->andWhere('(quote.code LIKE :search OR quote.title LIKE :search OR quote.data LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(quote.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);

        $records = $qb
            ->select([
                'quote.id AS id',
                'quote.title AS title',
                'quote.code AS code',
                'quote.data AS data',
                'quote.datetimeAdd AS datetime_add',
                'quote.datetimeLast AS datetime_last',
                'quote.uidAdd AS uid_add',
                'quote.uidLast AS uid_last',
                'creator.name AS creator_name',
                'creator.userName AS creator_username',
                'editor.name AS editor_name',
                'editor.userName AS editor_username',
            ])
            ->orderBy('quote.datetimeAdd', 'DESC')
            ->addOrderBy('quote.id', 'DESC')
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
