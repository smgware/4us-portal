<?php

namespace App\Repository;

use App\Entity\Worksheet;
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

    public function softDelete(WorksheetAttachment $worksheetAttachment, ?int $userId = null): void
    {
        $worksheetAttachment
            ->setStatus('0')
            ->setUidLast($userId)
            ->setDatetimeLast(new \DateTimeImmutable());

        $this->save($worksheetAttachment);
    }

    /** @return WorksheetAttachment[] */
    public function findActiveForWorksheet(Worksheet $worksheet): array
    {
        return $this->findBy(
            ['worksheet' => $worksheet, 'status' => '1'],
            ['datetimeAdd' => 'DESC', 'id' => 'DESC'],
        );
    }

    /**
     * @return array{records: WorksheetAttachment[], page: int, totalPages: int, totalRecords: int, itemsPerPage: int, hasMore: bool}
     */
    public function findRegularPage(Worksheet $worksheet, string $search, int $page, int $itemsPerPage = 5): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);
        $needle = mb_strtolower(trim($search));

        $records = array_values(array_filter(
            $this->findActiveForWorksheet($worksheet),
            static function (WorksheetAttachment $attachment) use ($needle): bool {
                $data = $attachment->getData() ?? [];
                if ((bool) ($data['isConditionImage'] ?? false)) {
                    return false;
                }

                if ($needle === '') {
                    return true;
                }

                $haystack = implode(' ', [
                    (string) $attachment->getName(),
                    (string) $attachment->getDescription(),
                    (string) ($data['originalName'] ?? ''),
                    (string) ($data['mimeType'] ?? ''),
                    (string) ($data['extension'] ?? ''),
                ]);

                return str_contains(mb_strtolower($haystack), $needle);
            },
        ));

        $totalRecords = count($records);
        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $offset = ($page - 1) * $itemsPerPage;
        $pageRecords = array_slice($records, $offset, $itemsPerPage);

        return [
            'records' => $pageRecords,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'itemsPerPage' => $itemsPerPage,
            'hasMore' => $offset + count($pageRecords) < $totalRecords,
        ];
    }

    /** @return array<string, WorksheetAttachment> */
    public function findConditionImages(Worksheet $worksheet): array
    {
        $result = [];
        foreach ($this->findActiveForWorksheet($worksheet) as $attachment) {
            $data = $attachment->getData() ?? [];
            if (!(bool) ($data['isConditionImage'] ?? false)) {
                continue;
            }

            $conditionType = trim((string) ($data['conditionType'] ?? ''));
            if ($conditionType !== '' && !isset($result[$conditionType])) {
                $result[$conditionType] = $attachment;
            }
        }

        return $result;
    }

    /** @return WorksheetAttachment[] */
    public function findConditionImagesByType(Worksheet $worksheet, string $conditionType): array
    {
        return array_values(array_filter(
            $this->findActiveForWorksheet($worksheet),
            static function (WorksheetAttachment $attachment) use ($conditionType): bool {
                $data = $attachment->getData() ?? [];

                return (bool) ($data['isConditionImage'] ?? false)
                    && trim((string) ($data['conditionType'] ?? '')) === trim($conditionType);
            },
        ));
    }
}
