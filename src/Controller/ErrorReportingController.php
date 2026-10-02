<?php

namespace App\Controller;

use App\Entity\Worksheet;
use App\Entity\WorksheetAttachment;
use App\Repository\MachineRepository;
use App\Repository\WorksheetAttachmentRepository;
use App\Repository\WorksheetRepository;
use App\Repository\WorksheetStatusTypeRepository;
use App\Repository\WorksheetTypeRepository;
use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ErrorReportingController extends AbstractController
{
    #[Route('/error-reporting', name: 'error_reporting_index', methods: ['GET'])]
    #[Route('/hiba-bejelentes', name: 'error_reporting_index_hu', methods: ['GET'])]
    #[Route('/hiba-bejelentese', name: 'error_reporting_index_hu2', methods: ['GET'])]
    #[Route('/hiba-bejelentes/{machineCode}', name: 'error_reporting_index_machine', methods: ['GET'])]
    public function index(?string $machineCode = null): Response
    {
        return $this->render('error_reporting/index.html.twig', [
            'machine_code' => $machineCode,
        ]);
    }

    #[Route('/error-reporting/getmachine', name: 'error_reporting_getmachine', methods: ['POST'])]
    public function getMachine(
        Request $request,
        MachineRepository $machineRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): JsonResponse {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('error_reporting', $csrfToken))) {
            return $this->json([
                'success' => false,
                'message' => 'Ervenytelen biztonsagi token. Frissitsd az oldalt es probald ujra.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $code = trim((string) $request->request->get('code', ''));
        if ($code === '') {
            return $this->json([
                'success' => false,
                'message' => 'Nincs beolvasott gepkod.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $machine = $machineRepository->findOneBy(['code' => $code]);
        if (!$machine) {
            return $this->json([
                'success' => false,
                'message' => 'Nem talalhato gep ezzel a koddal.',
                'data' => [
                    'code' => $code,
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'message' => 'Gep megtalalva.',
            'data' => [
                'id' => $machine->getId(),
                'code' => $machine->getCode(),
                'title' => $machine->getTitle(),
                'category' => $machine->getMachineCategory()?->getTitle(),
            ],
        ]);
    }

    #[Route('/error-reporting/save', name: 'error_reporting_save', methods: ['POST'])]
    public function save(
        Request $request,
        WorksheetAttachmentRepository $worksheetAttachmentRepository,
        WorksheetRepository $worksheetRepository,
        WorksheetTypeRepository $worksheetTypeRepository,
        WorksheetStatusTypeRepository $worksheetStatusTypeRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): JsonResponse {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('error_reporting', $csrfToken))) {
            return $this->json([
                'success' => false,
                'message' => 'Ervenytelen biztonsagi token. Frissitsd az oldalt es probald ujra.',
            ], Response::HTTP_BAD_REQUEST);
        }
        $errors = [];

        $email = trim((string) $request->request->get('email', ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ervenyes e-mail cimet adj meg.';
        }

        if ($errors !== []) {
            return $this->json([
                'success' => false,
                'message' => implode('<br>', $errors),
                'errors' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        $worksheetType = $worksheetTypeRepository->findOneBy(['code' => 'ERROR_REPORT']);
        if (!$worksheetType) {
            return $this->json([
                'success' => false,
                'message' => 'Hianyzik az ERROR_REPORT kodu munkalap tipus.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $worksheetStatusType = $worksheetStatusTypeRepository->findOneBy([
            'worksheetType' => $worksheetType,
            'code' => 'NEW',
        ]);
        if (!$worksheetStatusType) {
            return $this->json([
                'success' => false,
                'message' => 'Hianyzik az ERROR_REPORT munkalap tipus NEW kodu statusza.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $worksheetCode = $this->generateWorksheetCode($worksheetRepository);

        try {
            $attachments = $this->storeAttachments($request, $worksheetCode);
        } catch (\RuntimeException $exception) {
            return $this->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $attachmentsForData = array_map(static function (array $attachment): array {
            unset($attachment['absolutePath']);

            return $attachment;
        }, $attachments);

        $errorReportingData = $request->request->all();
        unset($errorReportingData['_csrf_token']);
        $errorReportingData['attachments'] = $attachmentsForData;
        $worksheetData = [
            'source' => 'external',
            'worksheet_type_code' => (string) $worksheetType->getCode(),
            'error_reporting_data' => $errorReportingData,
        ];

        $now = new \DateTimeImmutable();
        $worksheet = new Worksheet();
        $worksheet
            ->setTitle('Hibajegy: ' . $worksheetCode)
            ->setCode($worksheetCode)
            ->setWorksheetType($worksheetType)
            ->setWorksheetStatusType($worksheetStatusType)
            ->setData($worksheetData)
            ->setStatus('1')
            ->setDatetimeAdd($now)
            ->setDatetimeLast($now)
            ->setDatetimeOpen($now)
            ->setDatetimeClosed(null)
            ->setUidAdd(null)
            ->setUidLast($this->getUser()?->getId());

        $worksheetRepository->save($worksheet);

        foreach ($attachmentsForData as $attachmentData) {
            $worksheetAttachment = new WorksheetAttachment();
            $worksheetAttachment
                ->setWorksheet($worksheet)
                ->setName(mb_substr($attachmentData['originalName'], 0, 255))
                ->setData([
                    'originalName' => $attachmentData['originalName'],
                    'storedName' => $attachmentData['fileName'],
                    'filename' => $attachmentData['fileName'],
                    'clientMimeType' => $attachmentData['clientMimeType'],
                    'mimeType' => $attachmentData['mimeType'],
                    'extension' => $attachmentData['extension'],
                    'size' => $attachmentData['size'],
                    'path' => $attachmentData['path'],
                    'sha256' => $attachmentData['sha256'],
                    'isImage' => $attachmentData['isImage'],
                    'isConditionImage' => false,
                    'conditionType' => null,
                    'uploadedAt' => $now->format(DATE_ATOM),
                ])
                ->setDatetimeAdd($now)
                ->setDatetimeLast(null)
                ->setDatetimeOpen($now)
                ->setDatetimeClosed(null)
                ->setUidAdd(null)
                ->setUidLast($this->getUser()?->getId())
                ->setStatus('1');

            $worksheetAttachmentRepository->save($worksheetAttachment);
        }

        $notificationResult = [];

        $emailSent = false;
        $emailError = null;
        $email = $request->request->get('email');
        if ($email !== null && trim((string) $email) !== '') {
            try {
                $recipientEmail = (string) $email;
                if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Ervenytelen email cim.');
                }

                $fieldLabels = [
                    'machine_code' => 'Gép száma',
                    'error_description' => 'Hiba leírása',
                    'company_name' => 'Cégnév',
                    'tax_number' => 'Adószám',
                    'name' => 'Bejelentő neve',
                    'email' => 'E-mail cím',
                    'phone' => 'Telefonszám',
                    'address' => 'Helyszín',
                    'attachments' => 'Csatolmányok',
                ];

                $mail = new PHPMailer(true);

                $host = (string) $this->getParameter('app.mail.host');
                if ($host !== '') {
                    $mail->isSMTP();
                    $mail->Host = $host;
                    $mail->SMTPAuth = (bool) $this->getParameter('app.mail.smtp_auth');
                    $mail->Username = (string) $this->getParameter('app.mail.username');
                    $mail->Password = (string) $this->getParameter('app.mail.password');
                    $mail->Port = (int) $this->getParameter('app.mail.port');
                    $encryption = (string) $this->getParameter('app.mail.encryption');
                    if ($encryption !== '' && strtolower($encryption) !== 'none') {
                        $mail->SMTPSecure = $encryption;
                    }
                }

                $fromEmail = (string) $this->getParameter('app.mail.from');
                $fromName = (string) $this->getParameter('app.mail.from_name');

                $mail->CharSet = 'UTF-8';
                $mail->setFrom($fromEmail, $fromName);
                $mail->addAddress($recipientEmail);
                $mail->isHTML(true);

                $mailLogoPath = $this->getParameter('kernel.project_dir') . '/public/assets/img/4us_logo_mail.png';
                if (!is_file($mailLogoPath)) {
                    throw new \RuntimeException('Az e-mail logo nem talalhato.');
                }
                $mail->addEmbeddedImage(
                    $mailLogoPath,
                    '4us-logo-mail',
                    '4us_logo_mail.png',
                    PHPMailer::ENCODING_BASE64,
                    'image/png',
                );
                $mail->Subject = 'Hibabejelentés rözítve: ' . $worksheetCode;
                $mail->Body = $this->renderView('emails/error_reporting_email_user.html.twig', [
                    'code' => $worksheetCode,
                    'data' => $errorReportingData,
                    'fieldLabels' => $fieldLabels,
                    'attachments' => $attachments,
                ]);
                $mail->AltBody = 'A hibabejelentés rögzítve. Kod: ' . $worksheetCode;

                foreach ($attachments as $attachment) {
                    $absolutePath = $attachment['absolutePath'] ?? '';
                    if ($absolutePath !== '' && is_file($absolutePath)) {
                        $mail->addAttachment($absolutePath, $attachment['originalName']);
                    }
                }

                $mail->send();
                $emailSent = true;
            } catch (\Throwable $exception) {
                $emailError = $exception->getMessage();
            }
        }

        return $this->json([
            'success' => true,
            'message' => $emailSent
                ? 'A hibabejelentes sikeresen rogzitve, az email elkuldve.'
                : 'A hibabejelentes sikeresen rogzitve, de az email kuldese nem sikerult.',
            'data' => [
                'id' => $worksheet->getId(),
                'code' => $worksheetCode,
                'worksheetId' => $worksheet->getId(),
                'worksheetCode' => $worksheetCode,
                'attachments' => $attachmentsForData,
                'emailSent' => $emailSent,
                'emailError' => $emailError,
                'notifications' => $notificationResult,
            ],
        ]);
    }

    private function generateWorksheetCode(WorksheetRepository $worksheetRepository): string
    {
        do {
            $code = 'M' . date('Ym') . '-' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        } while ($worksheetRepository->findOneBy(['code' => $code]) !== null);

        return $code;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function storeAttachments(Request $request, string $code): array
    {
        $files = $request->files->get('attachments', []);
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (!is_array($files)) {
            return [];
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/uploads/worksheets/' . $code;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException('Nem sikerult letrehozni a feltoltesi mappat.');
        }

        $attachments = [];
        foreach ($files as $index => $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }
            /*
            $mimeType = (string) $file->getMimeType();
            if (!str_starts_with($mimeType, 'image/')) {
                throw new \RuntimeException('Csak kep fajlok tolthetok fel.');
            }
            */
            $originalName = $file->getClientOriginalName();
            $fileSize = $file->getSize() ?? 0;
            $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo !== false
                ? (string) (finfo_file($fileInfo, $file->getPathname()) ?: 'application/octet-stream')
                : 'application/octet-stream';
            if ($fileInfo !== false) {
                finfo_close($fileInfo);
            }
            $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $originalName) ?: 'image';
            $fileName = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) . '-' . uniqid('', true) . '-' . $safeName;

            $file->move($uploadDir, $fileName);
            $absolutePath = $uploadDir . '/' . $fileName;

            $attachments[] = [
                'originalName' => $originalName,
                'fileName' => $fileName,
                'clientMimeType' => (string) $file->getClientMimeType(),
                'mimeType' => strtolower($mimeType),
                'extension' => $extension,
                'size' => $fileSize,
                'path' => '/uploads/worksheets/' . $code . '/' . $fileName,
                'absolutePath' => $absolutePath,
                'sha256' => is_file($absolutePath) ? hash_file('sha256', $absolutePath) : null,
                'isImage' => in_array(strtolower($mimeType), [
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'image/webp',
                    'image/bmp',
                    'image/avif',
                ], true),
            ];
        }

        return $attachments;
    }
}
