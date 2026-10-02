<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class ProjectRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(Project $project, bool $flush = true): void
    {
        $this->entityManager->persist($project);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * @return array<int, array{id: int, code: string, name: string, status: string}>
     */
    public function findForWorksheetSelect(string $search, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('project')
            ->select('project.id, project.code, project.name, project.status')
            ->orderBy('project.name', 'ASC')
            ->addOrderBy('project.code', 'ASC')
            ->setMaxResults(max(1, $limit));

        if ($search !== '') {
            $qb
                ->andWhere('project.code LIKE :search OR project.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @return array<int, array{id: int, code: string, name: string, status: string}>
     */
    public function findForMachineRentalSelect(string $search, int $limit = 20): array
    {
        return $this->findForWorksheetSelect($search, $limit);
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(Project::class, 'project');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('project.code LIKE :search OR project.name LIKE :search OR project.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(project.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'project.id AS id',
                'project.code AS code',
                'project.name AS name',
                'project.status AS status',
                'project.description AS description',
            ])
            ->orderBy('project.id', 'DESC')
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
