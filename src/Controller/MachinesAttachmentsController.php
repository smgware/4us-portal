<?php

namespace App\Controller;

use App\Entity\Machine;
use App\Entity\MachineAttachment;
use App\Repository\MachineAttachmentRepository;
use App\Repository\MachineRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class MachinesAttachmentsController extends BaseController
{
    private const ITEMS_PER_PAGE = 5;
    private const MAX_ATTACHMENT_FILE_SIZE = 268435456;
    private const ATTACHMENTS_DIRECTORY = 'uploads/machines';

    #[Route('/machines/attachments/list', name: 'list_machine_attachments', methods: ['POST'])]
    public function listAttachments(
        Request $request,
        MachineRepository $machineRepository,
        MachineAttachmentRepository $attachmentRepository,
        UserRepository $userRepository,
    ): Response {
        $machine = $this->findRequestedMachine($request, $machineRepository);
        if (!$machine) {
            return $this->machineNotFoundResponse();
        }

        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen szűrési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $page = max(1, (int) $request->request->get('page', 1));
        $list = $attachmentRepository->findPage($machine, $filters, $page, self::ITEMS_PER_PAGE);
        $items = array_map(
            fn (MachineAttachment $attachment): array => $this->serializeAttachment($attachment, $userRepository),
            $list['records'],
        );

        return $this->response(true, [
            'template' => 'machines_attachments/_list.html.twig',
            'templateData' => [
                'attachments' => $items,
                'page' => $list['page'],
                'hasMore' => $list['hasMore'],
            ],
            'data' => [
                'items' => $items,
                'page' => $list['page'],
                'hasMore' => $list['hasMore'],
                'totalRecords' => $list['totalRecords'],
            ],
        ]);
    }

    #[Route('/machines/attachments/upload', name: 'upload_machine_attachment', methods: ['POST'])]
    public function uploadAttachment(
        Request $request,
        MachineRepository $machineRepository,
        MachineAttachmentRepository $attachmentRepository,
        UserRepository $userRepository,
    ): Response {
        $maximumFileSize = self::maximumFileSize();
        $contentLength = (int) $request->server->get('CONTENT_LENGTH', 0);
        if ($contentLength > 0 && $request->request->count() === 0 && $request->files->count() === 0) {
            return $this->response(false, [
                'data' => [
                    'error' => 'A feltöltési kérés túl nagy. Egy csatolmány legfeljebb ' . $this->formatFileSize($maximumFileSize) . ' lehet.',
                    'maxFileSize' => $maximumFileSize,
                ],
            ], 'json', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $machine = $this->findRequestedMachine($request, $machineRepository);
        if (!$machine) {
            return $this->machineNotFoundResponse();
        }

        $file = $request->files->get('attachment');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $message = $file instanceof UploadedFile
                ? $file->getErrorMessage()
                : 'Nincs feltöltendő fájl.';

            return $this->response(false, [
                'data' => ['error' => $message],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $fileSize = (int) ($file->getSize() ?? 0);
        if ($fileSize > $maximumFileSize) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Egy csatolmány legfeljebb ' . $this->formatFileSize($maximumFileSize) . ' lehet.',
                    'maxFileSize' => $maximumFileSize,
                ],
            ], 'json', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $originalName = trim($file->getClientOriginalName());
        if ($originalName === '') {
            $originalName = 'csatolmany';
        }
        $displayName = mb_substr($originalName, 0, 255);
        $description = mb_substr(trim((string) $request->request->get('description', '')), 0, 5000);

        try {
            $storedFile = $this->storeFile($file, $machine);
        } catch (\RuntimeException $exception) {
            return $this->response(false, [
                'data' => ['error' => $exception->getMessage()],
            ], 'json', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $now = new \DateTimeImmutable();
        $attachment = (new MachineAttachment())
            ->setMachine($machine)
            ->setName($displayName)
            ->setDescription($description !== '' ? $description : null)
            ->setData([
                'originalName' => $originalName,
                'storedName' => $storedFile['storedName'],
                'clientMimeType' => (string) $file->getClientMimeType(),
                'mimeType' => $storedFile['mimeType'],
                'extension' => $storedFile['extension'],
                'size' => $fileSize,
                'path' => $storedFile['relativePath'],
                'sha256' => hash_file('sha256', $storedFile['absolutePath']) ?: null,
                'clientLastModified' => $this->positiveInt($request->request->get('last_modified')),
                'isImage' => $this->isSafeInlineImage($storedFile['mimeType']),
                'uploadedAt' => $now->format(DATE_ATOM),
            ])
            ->setUidAdd($this->getUser()?->getId())
            ->setUidLast($this->getUser()?->getId())
            ->setDatetimeAdd($now)
            ->setDatetimeLast($now)
            ->setStatus('1');

        try {
            $attachmentRepository->save($attachment);
        } catch (\Throwable $exception) {
            if (is_file($storedFile['absolutePath'])) {
                @unlink($storedFile['absolutePath']);
            }

            return $this->response(false, [
                'data' => ['error' => 'A csatolmány adatai nem menthetők.'],
            ], 'json', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->response(true, [
            'data' => [
                'attachment' => $this->serializeAttachment($attachment, $userRepository),
                'maxFileSize' => $maximumFileSize,
            ],
        ]);
    }

    #[Route('/machines/attachments/description', name: 'update_machine_attachment_description', methods: ['POST'])]
    public function updateDescription(
        Request $request,
        MachineRepository $machineRepository,
        MachineAttachmentRepository $attachmentRepository,
        UserRepository $userRepository,
    ): Response {
        $machine = $this->findRequestedMachine($request, $machineRepository);
        if (!$machine) {
            return $this->machineNotFoundResponse();
        }

        $attachment = $this->findActiveAttachment($request, $machine, $attachmentRepository);
        if (!$attachment) {
            return $this->attachmentNotFoundResponse();
        }

        $description = mb_substr(trim((string) $request->request->get('description', '')), 0, 5000);
        $attachment
            ->setDescription($description !== '' ? $description : null)
            ->setUidLast($this->getUser()?->getId())
            ->setDatetimeLast(new \DateTimeImmutable());
        $attachmentRepository->save($attachment);

        return $this->response(true, [
            'data' => ['attachment' => $this->serializeAttachment($attachment, $userRepository)],
        ]);
    }

    #[Route('/machines/attachments/delete', name: 'delete_machine_attachment', methods: ['POST', 'DELETE'])]
    public function deleteAttachment(
        Request $request,
        MachineRepository $machineRepository,
        MachineAttachmentRepository $attachmentRepository,
    ): Response {
        $machine = $this->findRequestedMachine($request, $machineRepository);
        if (!$machine) {
            return $this->machineNotFoundResponse();
        }

        $attachment = $this->findActiveAttachment($request, $machine, $attachmentRepository);
        if (!$attachment) {
            return $this->attachmentNotFoundResponse();
        }

        $attachmentId = (int) $attachment->getId();
        $absolutePath = $this->resolveAttachmentPath((string) (($attachment->getData() ?? [])['path'] ?? ''));
        $attachmentRepository->delete($attachment);
        if ($absolutePath && is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        return $this->response(true, [
            'data' => ['id' => $attachmentId, 'deleted' => true],
        ]);
    }

    #[Route('/machines/attachments/file/{id}', name: 'machine_attachment_file', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function attachmentFile(
        int $id,
        Request $request,
        MachineAttachmentRepository $attachmentRepository,
    ): Response {
        $attachment = $attachmentRepository->find($id);
        if (!$attachment || $attachment->getStatus() !== '1') {
            return new Response('A csatolmány nem található.', Response::HTTP_NOT_FOUND);
        }

        $data = $attachment->getData() ?? [];
        $absolutePath = $this->resolveAttachmentPath((string) ($data['path'] ?? ''));
        if (!$absolutePath) {
            return new Response('A csatolmány fájlja nem található.', Response::HTTP_NOT_FOUND);
        }

        $mimeType = strtolower((string) ($data['mimeType'] ?? $data['type'] ?? ''));
        if ($mimeType === '' || $mimeType === 'application/octet-stream') {
            $mimeType = $this->detectFileMimeType($absolutePath);
        }
        $inline = $request->query->getBoolean('inline') && $this->isSafeInlinePreview($mimeType);
        $originalName = (string) ($data['originalName'] ?? $attachment->getName() ?? 'csatolmany');
        $fallbackName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $originalName) ?: 'attachment';

        $response = new BinaryFileResponse($absolutePath);
        $response->headers->set('Content-Type', $mimeType ?: 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(
            $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $originalName,
            $fallbackName,
        );

        return $response;
    }

    private function findRequestedMachine(Request $request, MachineRepository $machineRepository): ?Machine
    {
        $machineId = $this->positiveInt($request->request->get('machine_id'));

        return $machineId ? $machineRepository->find($machineId) : null;
    }

    private function findActiveAttachment(
        Request $request,
        Machine $machine,
        MachineAttachmentRepository $attachmentRepository,
    ): ?MachineAttachment {
        $attachmentId = $this->positiveInt($request->request->get('attachment_id'));
        $attachment = $attachmentId ? $attachmentRepository->find($attachmentId) : null;

        if (
            !$attachment
            || $attachment->getStatus() !== '1'
            || $attachment->getMachine()?->getId() !== $machine->getId()
        ) {
            return null;
        }

        return $attachment;
    }

    /**
     * @return array{storedName: string, extension: string, mimeType: string, relativePath: string, absolutePath: string}
     */
    private function storeFile(UploadedFile $file, Machine $machine): array
    {
        $originalName = trim($file->getClientOriginalName());
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $safeExtension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: '';
        $baseName = (string) pathinfo($originalName, PATHINFO_FILENAME);
        $safeBaseName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $baseName) ?: 'file';
        $safeBaseName = trim(substr($safeBaseName, 0, 100), '.-_') ?: 'file';
        $storedName = bin2hex(random_bytes(12)) . '-' . $safeBaseName;
        if ($safeExtension !== '') {
            $storedName .= '.' . $safeExtension;
        }

        $safeMachineCode = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $machine->getCode());
        $machineDirectory = $machine->getId() . ($safeMachineCode ? '-' . $safeMachineCode : '');
        $relativeDirectory = self::ATTACHMENTS_DIRECTORY . '/' . $machineDirectory;
        $absoluteDirectory = $this->getParameter('kernel.project_dir') . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            throw new \RuntimeException('Nem sikerült létrehozni a feltöltési mappát.');
        }

        $mimeType = $this->detectUploadedFileMimeType($file);
        try {
            $file->move($absoluteDirectory, $storedName);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Nem sikerült elmenteni a csatolmányt.', 0, $exception);
        }

        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $storedName;

        return [
            'storedName' => $storedName,
            'extension' => $safeExtension,
            'mimeType' => $mimeType,
            'relativePath' => $relativeDirectory . '/' . $storedName,
            'absolutePath' => $absolutePath,
        ];
    }

    private function detectUploadedFileMimeType(UploadedFile $file): string
    {
        return $this->detectFileMimeType($file->getPathname());
    }

    private function detectFileMimeType(string $path): string
    {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo === false) {
            return (string) (mime_content_type($path) ?: 'application/octet-stream');
        }

        try {
            return strtolower((string) (finfo_file($fileInfo, $path) ?: 'application/octet-stream'));
        } finally {
            finfo_close($fileInfo);
        }
    }

    private function isSafeInlineImage(string $mimeType): bool
    {
        return in_array(strtolower($mimeType), [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/avif',
        ], true);
    }

    private function isSafeInlinePreview(string $mimeType): bool
    {
        return $this->isSafeInlineImage($mimeType)
            || in_array(strtolower($mimeType), ['application/pdf', 'text/plain'], true);
    }

    private function resolveAttachmentPath(string $relativePath): ?string
    {
        $relativePath = ltrim(str_replace('\\', '/', trim($relativePath)), '/');
        if ($relativePath === '') {
            return null;
        }

        $projectDirectory = (string) $this->getParameter('kernel.project_dir');
        $allowedRoot = realpath($projectDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::ATTACHMENTS_DIRECTORY));
        $candidate = realpath($projectDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if ($allowedRoot === false || $candidate === false || !is_file($candidate)) {
            return null;
        }

        $normalizedRoot = strtolower(rtrim(str_replace('\\', '/', $allowedRoot), '/'));
        $normalizedCandidate = strtolower(str_replace('\\', '/', $candidate));

        return str_starts_with($normalizedCandidate, $normalizedRoot . '/') ? $candidate : null;
    }

    private function serializeAttachment(MachineAttachment $attachment, UserRepository $userRepository): array
    {
        $data = $attachment->getData() ?? [];
        $mimeType = strtolower((string) ($data['mimeType'] ?? $data['type'] ?? 'application/octet-stream'));
        $originalName = (string) ($data['originalName'] ?? $attachment->getName() ?? 'csatolmany');
        $size = (int) ($data['size'] ?? 0);
        $userId = $attachment->getUidAdd() ?? $attachment->getUidLast();
        $user = $userId ? $userRepository->find($userId) : null;
        $previewType = $this->isSafeInlineImage($mimeType)
            ? 'image'
            : ($mimeType === 'application/pdf' ? 'pdf' : ($mimeType === 'text/plain' ? 'text' : 'file'));

        return [
            'id' => $attachment->getId(),
            'name' => $attachment->getName(),
            'originalName' => $originalName,
            'description' => $attachment->getDescription(),
            'mimeType' => $mimeType,
            'extension' => (string) ($data['extension'] ?? pathinfo($originalName, PATHINFO_EXTENSION)),
            'size' => $size,
            'sizeFormatted' => $this->formatFileSize($size),
            'sha256' => $data['sha256'] ?? null,
            'previewType' => $previewType,
            'isImage' => $previewType === 'image',
            'isPreviewable' => $previewType !== 'file',
            'uploadedAt' => $attachment->getDatetimeAdd()?->format(DATE_ATOM),
            'uploadedAtFormatted' => $attachment->getDatetimeAdd()?->format('Y-m-d H:i'),
            'uploadedBy' => [
                'id' => $user?->getId(),
                'name' => $user?->getName() ?: $user?->getUserName() ?: 'Ismeretlen',
            ],
            'contentUrl' => $this->generateUrl('machine_attachment_file', [
                'id' => $attachment->getId(),
                'inline' => 1,
            ]),
            'downloadUrl' => $this->generateUrl('machine_attachment_file', [
                'id' => $attachment->getId(),
                'download' => 1,
            ]),
        ];
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    public static function maximumFileSize(): int
    {
        return min(self::MAX_ATTACHMENT_FILE_SIZE, (int) UploadedFile::getMaxFilesize());
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function machineNotFoundResponse(): Response
    {
        return $this->response(false, [
            'data' => ['error' => 'A gép nem található.'],
        ], 'json', Response::HTTP_NOT_FOUND);
    }

    private function attachmentNotFoundResponse(): Response
    {
        return $this->response(false, [
            'data' => ['error' => 'A csatolmány nem található.'],
        ], 'json', Response::HTTP_NOT_FOUND);
    }
}
