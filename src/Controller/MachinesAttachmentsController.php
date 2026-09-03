<?php

namespace App\Controller;

use App\Entity\Machine;
use App\Entity\MachineAttachment;
use App\Repository\MachineAttachmentRepository;
use App\Repository\MachineRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class MachinesAttachmentsController extends BaseController
{
    private const ITEMS_PER_PAGE = 20;
    private const MAX_ATTACHMENT_FILE_SIZE = 268435456; // 256 MB
    private const ATTACHMENTS_DIRECTORY = 'uploads/machines';

    #[Route('/machines/attachments/add', name: 'index_machines_attachment_add', methods: ['POST'])]
    public function index_machines_attachment_add(
        Request $request,
        MachineRepository $machineRepository,
    ): Response {
        $machine = $this->findRequestedMachine($request, $machineRepository);
        if ($machine === null) {
            return $this->response(false, [
                'data' => ['error' => 'A gép nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        return $this->response(true, [
            'template' => 'machines_attachments/index_add.html.twig',
            'templateData' => [
                'machine_id' => $machine->getId(),
                'max_attachment_file_size' => self::MAX_ATTACHMENT_FILE_SIZE,
            ],
            'data' => [
                'machine_id' => $machine->getId(),
                'max_attachment_file_size' => self::MAX_ATTACHMENT_FILE_SIZE,
            ],
        ]);
    }

    #[Route('/machines/attachments/main', name: 'main_machines_attachments', methods: ['POST'])]
    public function main_machines_attachments(
        Request $request,
        MachineRepository $machineRepository,
    ): Response {
        $machine = $this->findRequestedMachine($request, $machineRepository);
        if ($machine === null) {
            return $this->response(false, [
                'data' => ['error' => 'A gép nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        return $this->response(true, [
            'template' => 'machines_attachments/main.html.twig',
            'templateData' => ['machine_id' => $machine->getId()],
            'data' => ['machine_id' => $machine->getId()],
        ]);
    }

    #[Route('/machines/attachments/list', name: 'list_machines_attachments_list', methods: ['POST'])]
    public function list_machines_attachments_list(
        Request $request,
        MachineRepository $machineRepository,
        MachineAttachmentRepository $machineAttachmentRepository,
    ): Response {
        $machine = $this->findRequestedMachine($request, $machineRepository);
        if ($machine === null) {
            return $this->response(false, [
                'data' => ['error' => 'A gép nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen szűrési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $pageRaw = $request->request->get('page', $request->query->get('page', 1));
        $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;
        $list = $machineAttachmentRepository->findList($machine, $filters, $page, self::ITEMS_PER_PAGE);

        foreach ($list['records'] as &$record) {
            $record['formatted_size'] = $this->formatFileSize((int) ($record['data']['size'] ?? 0));
        }
        unset($record);

        return $this->response(true, [
            'template' => 'machines_attachments/list_machines_attachments_list.html.twig',
            'templateData' => [
                'attachments' => $list['records'],
                'machine_id' => $machine->getId(),
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
            ],
            'data' => [
                'machine_id' => $machine->getId(),
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
            ],
        ]);
    }

    #[Route('/machines/attachments/save', name: 'save_machine_attachments', methods: ['POST'])]
    public function save_machine_attachments(
        Request $request,
        MachineRepository $machineRepository,
        MachineAttachmentRepository $machineAttachmentRepository,
    ): Response {
        $contentLength = (int) $request->server->get('CONTENT_LENGTH', 0);
        if ($contentLength > 0 && $request->request->count() === 0 && $request->files->count() === 0) {
            return $this->response(false, [
                'data' => [
                    'error' => 'A feltöltési kérés túl nagy. A csatolmány legfeljebb 256 MB lehet.',
                    'max_file_size' => self::MAX_ATTACHMENT_FILE_SIZE,
                ],
            ], 'json', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $machine = $this->findRequestedMachine($request, $machineRepository);
        if ($machine === null) {
            return $this->response(false, [
                'data' => ['error' => 'A gép nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $operation = mb_strtolower(trim((string) $request->request->get('function', 'add')));
        if (!in_array($operation, ['add', 'delete'], true)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen csatolmány művelet.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        if ($operation === 'delete') {
            $attachmentId = $request->request->get('id');
            $attachment = is_numeric($attachmentId)
                ? $machineAttachmentRepository->find((int) $attachmentId)
                : null;

            if ($attachment === null || $attachment->getMachine()?->getId() !== $machine->getId()) {
                return $this->response(false, [
                    'data' => ['error' => 'A csatolmány nem található.'],
                ], 'json', Response::HTTP_NOT_FOUND);
            }

            $filePath = $this->resolveAttachmentPath($attachment->getData() ?? []);
            if ($filePath !== null && is_file($filePath) && !unlink($filePath)) {
                return $this->response(false, [
                    'data' => ['error' => 'A csatolmány fájlja nem törölhető.'],
                ], 'json', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $machineAttachmentRepository->delete($attachment);

            return $this->response(true, [
                'data' => ['id' => (int) $attachmentId, 'deleted' => true],
            ]);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->response(false, [
                'data' => ['error' => 'A feltöltendő fájl hiányzik vagy hibás.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        if (($file->getSize() ?? 0) > self::MAX_ATTACHMENT_FILE_SIZE) {
            return $this->response(false, [
                'data' => [
                    'error' => 'A csatolmány legfeljebb 256 MB lehet.',
                    'max_file_size' => self::MAX_ATTACHMENT_FILE_SIZE,
                ],
            ], 'json', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $name = mb_substr(trim((string) $request->request->get('name', $file->getClientOriginalName())), 0, 255);
        if ($name === '') {
            $name = mb_substr($file->getClientOriginalName() ?: 'csatolmány', 0, 255);
        }
        $description = mb_substr(trim((string) $request->request->get('description', '')), 0, 255);

        try {
            $storedFile = $this->storeAttachmentFile($file, $machine->getCode() ?: ('machine-' . $machine->getId()));
        } catch (\RuntimeException $exception) {
            return $this->response(false, [
                'data' => ['error' => $exception->getMessage()],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $now = new \DateTimeImmutable();
        $attachment = (new MachineAttachment())
            ->setMachine($machine)
            ->setName($name)
            ->setDescription($description !== '' ? $description : null)
            ->setData([
                'filename' => $storedFile['fileName'],
                'size' => $storedFile['size'],
                'path' => $storedFile['path'],
                'type' => $storedFile['mimeType'],
            ])
            ->setDatetimeAdd($now)
            ->setDatetimeLast($now)
            ->setUidAdd($this->getUser()?->getId())
            ->setUidLast($this->getUser()?->getId())
            ->setStatus('1');

        try {
            $machineAttachmentRepository->save($attachment);
        } catch (\Throwable $exception) {
            if (is_file($storedFile['absolutePath'])) {
                @unlink($storedFile['absolutePath']);
            }

            throw $exception;
        }

        return $this->response(true, [
            'data' => [
                'machine_id' => $machine->getId(),
                'attachment' => $this->serializeAttachment($attachment),
            ],
        ]);
    }

    #[Route('/machines/attachments/file', name: 'get_machine_attachment', methods: ['GET'])]
    public function getMachineAttachment(
        Request $request,
        MachineAttachmentRepository $machineAttachmentRepository,
    ): Response {
        $id = $request->query->getInt('id');
        $attachment = $id > 0 ? $machineAttachmentRepository->find($id) : null;
        if ($attachment === null) {
            return new Response('Attachment not found.', Response::HTTP_NOT_FOUND);
        }

        $filePath = $this->resolveAttachmentPath($attachment->getData() ?? []);
        if ($filePath === null) {
            return new Response('Attachment file not found.', Response::HTTP_NOT_FOUND);
        }

        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $fileName = $attachment->getName() ?: basename($filePath);
        $fallbackFileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $fileName) ?: 'attachment';
        $disposition = $request->query->getBoolean('download')
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;

        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', $mimeType);
        $response->setContentDisposition($disposition, $fileName, $fallbackFileName);

        return $response;
    }

    private function findRequestedMachine(Request $request, MachineRepository $machineRepository): ?Machine
    {
        $machineId = $request->request->get(
            'machine_id',
            $request->query->get('machine_id', $request->query->get('machineId', '')),
        );

        return is_numeric($machineId) ? $machineRepository->find((int) $machineId) : null;
    }

    /**
     * @return array{fileName: string, originalName: string, size: int, mimeType: string, path: string, absolutePath: string}
     */
    private function storeAttachmentFile(UploadedFile $file, string $machineCode): array
    {
        $safeMachineCode = preg_replace('/[^A-Za-z0-9._-]+/', '-', $machineCode) ?: 'machine';
        $relativeDirectory = self::ATTACHMENTS_DIRECTORY . '/' . $safeMachineCode . '/attachments';
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/' . $relativeDirectory;

        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            throw new \RuntimeException('Nem sikerült létrehozni a feltöltési mappát.');
        }

        $originalName = $file->getClientOriginalName() ?: 'attachment';
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $originalName) ?: 'attachment';
        $fileName = uniqid('', true) . '-' . $safeName;
        $size = (int) ($file->getSize() ?? 0);
        $detectedMimeType = function_exists('mime_content_type')
            ? mime_content_type($file->getPathname())
            : false;
        $mimeType = (string) ($detectedMimeType ?: $file->getClientMimeType() ?: 'application/octet-stream');

        try {
            $file->move($uploadDirectory, $fileName);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Nem sikerült elmenteni a csatolmányt.', 0, $exception);
        }

        return [
            'fileName' => $fileName,
            'originalName' => $originalName,
            'size' => $size,
            'mimeType' => $mimeType,
            'path' => '/' . str_replace('\\', '/', $relativeDirectory . '/' . $fileName),
            'absolutePath' => $uploadDirectory . '/' . $fileName,
        ];
    }

    private function resolveAttachmentPath(array $data): ?string
    {
        $path = trim((string) ($data['path'] ?? ''));
        if ($path === '') {
            return null;
        }

        $projectDirectory = (string) $this->getParameter('kernel.project_dir');
        $candidate = $projectDirectory . '/' . ltrim(str_replace('\\', '/', $path), '/');
        $resolvedPath = realpath($candidate);
        $allowedRoot = realpath($projectDirectory . '/' . self::ATTACHMENTS_DIRECTORY);

        if ($resolvedPath === false || $allowedRoot === false || !is_file($resolvedPath)) {
            return null;
        }

        $normalizedPath = strtolower(str_replace('\\', '/', $resolvedPath));
        $normalizedRoot = strtolower(rtrim(str_replace('\\', '/', $allowedRoot), '/'));

        return str_starts_with($normalizedPath, $normalizedRoot . '/') ? $resolvedPath : null;
    }

    private function serializeAttachment(MachineAttachment $attachment): array
    {
        return [
            'id' => $attachment->getId(),
            'machine_id' => $attachment->getMachine()?->getId(),
            'name' => $attachment->getName(),
            'description' => $attachment->getDescription(),
            'data' => $attachment->getData() ?? [],
            'datetime_add' => $attachment->getDatetimeAdd()?->format(\DateTimeInterface::ATOM),
            'status' => $attachment->getStatus(),
        ];
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

}
