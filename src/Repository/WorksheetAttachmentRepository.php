<?php

namespace App\Repository;

use App\Entity\WorksheetAttachment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class WorksheetAttachmentRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorksheetAttachment::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(WorksheetAttachment $worksheetAttachment, bool $flush = true): void
    {
        $this->entityManager->persist($worksheetAttachment);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(WorksheetAttachment $worksheetAttachment, bool $flush = true): void
    {
        $this->entityManager->remove($worksheetAttachment);

        if ($flush) {
            $this->entityManager->flush();
        }
    }
}
