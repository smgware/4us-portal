<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WorksheetDescriptionTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class WorksheetDescriptionTemplateRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorksheetDescriptionTemplate::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(WorksheetDescriptionTemplate $template, bool $flush = true): void
    {
        $this->entityManager->persist($template);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(WorksheetDescriptionTemplate $template, bool $flush = true): void
    {
        $this->entityManager->remove($template);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(WorksheetDescriptionTemplate::class, 'descriptionTemplate')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = descriptionTemplate.uidAdd')
            ->leftJoin(User::class, 'userEditor', 'WITH', 'userEditor.id = descriptionTemplate.uidLast');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('descriptionTemplate.title LIKE :search OR descriptionTemplate.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(descriptionTemplate.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'descriptionTemplate.id AS id',
                'descriptionTemplate.title AS title',
                'descriptionTemplate.description AS description',
                'descriptionTemplate.uidAdd AS uid_add',
                'descriptionTemplate.uidLast AS uid_last',
                'descriptionTemplate.datetimeAdd AS datetime_add',
                'descriptionTemplate.datetimeLast AS datetime_last',
                'descriptionTemplate.status AS status',
                'userOwner.name AS user_owner_name',
                'userOwner.userName AS user_owner_username',
                'userEditor.name AS user_editor_name',
                'userEditor.userName AS user_editor_username',
            ])
            ->orderBy('descriptionTemplate.id', 'DESC')
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
