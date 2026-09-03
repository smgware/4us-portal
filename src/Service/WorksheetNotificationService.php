<?php

namespace App\Service;

use App\Entity\EmailLog;
use App\Entity\Machine;
use App\Entity\Worksheet;
use App\Repository\EmailLogRepository;
use App\Repository\MachineRepository;
use App\Repository\NotificationEventRepository;
use App\Repository\PartnerContactRepository;
use App\Repository\UserRepository;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

final class WorksheetNotificationService
{
    private const EVENT_CODES = [
        'ERROR_REPORT' => [
            'add' => 'ERROR_REPORTING_WORKSHEET_ADD',
            'modify' => 'ERROR_REPORTING_WORKSHEET_MODIFY',
        ],
        'ERROR_REPORTING' => [
            'add' => 'ERROR_REPORTING_WORKSHEET_ADD',
            'modify' => 'ERROR_REPORTING_WORKSHEET_MODIFY',
        ],
        'MACHINE_HANDOVER' => [
            'add' => 'MACHINE_HANDOVER_WORKSHEET_ADD',
            'modify' => 'MACHINE_HANDOVER_WORKSHEET_MODIFY',
        ],
        'MACHINE_RETURN' => [
            'add' => 'MACHINE_RETURN_WORKSHEET_ADD',
            'modify' => 'MACHINE_RETURN_WORKSHEET_MODIFY',
        ],
    ];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PartnerContactRepository $partnerContactRepository,
        private readonly NotificationEventRepository $notificationEventRepository,
        private readonly MachineRepository $machineRepository,
        private readonly EmailLogRepository $emailLogRepository,
        private readonly Environment $twig,
        private readonly ParameterBagInterface $parameterBag,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Sends separate partner-contact and internal-user notifications.
     * Email failures are reported in the result and do not interrupt worksheet saving.
     *
     * @return array{event_code: ?string, sent: int, failed: int, partner_recipients: int, user_recipients: int, skipped?: bool}
     */
    public function sendForWorksheet(Worksheet $worksheet, bool $isNew): array
    {
        try {
            return $this->doSendForWorksheet($worksheet, $isNew);
        } catch (\Throwable $exception) {
            $worksheetTypeCode = mb_strtoupper(trim((string) $worksheet->getWorksheetType()?->getCode()));
            $eventCode = self::EVENT_CODES[$worksheetTypeCode][$isNew ? 'add' : 'modify'] ?? null;
            $this->logger->error('Worksheet notifications could not be processed.', [
                'worksheet_id' => $worksheet->getId(),
                'event_code' => $eventCode,
                'exception' => $exception,
            ]);

            return [
                'event_code' => $eventCode,
                'sent' => 0,
                'failed' => 1,
                'partner_recipients' => 0,
                'user_recipients' => 0,
            ];
        }
    }

    /**
     * @return array{event_code: ?string, sent: int, failed: int, partner_recipients: int, user_recipients: int, skipped?: bool}
     */
    private function doSendForWorksheet(Worksheet $worksheet, bool $isNew): array
    {
        $worksheetTypeCode = mb_strtoupper(trim((string) $worksheet->getWorksheetType()?->getCode()));
        $eventCode = self::EVENT_CODES[$worksheetTypeCode][$isNew ? 'add' : 'modify'] ?? null;

        if ($eventCode === null) {
            return [
                'event_code' => null,
                'sent' => 0,
                'failed' => 0,
                'partner_recipients' => 0,
                'user_recipients' => 0,
                'skipped' => true,
            ];
        }

        $notificationEvent = $this->notificationEventRepository->findOneBy(['code' => $eventCode]);
        $eventName = trim((string) $notificationEvent?->getName());
        if ($eventName === '') {
            $eventName = $eventCode;
        }

        $worksheetData = $worksheet->getData() ?? [];
        $machine = $this->resolveMachine($worksheet, $worksheetData);
        $companyName = trim((string) ($worksheet->getPartner()?->getName()
            ?? $worksheetData['company_name']
            ?? $worksheetData['partner_name']
            ?? 'Ismeretlen cég'));
        $machineName = $this->formatMachineName($machine, $worksheetData);
        $statusName = trim((string) ($worksheet->getWorksheetStatusType()?->getTitle()
            ?? $worksheet->getWorksheetStatusType()?->getCode()
            ?? 'Nincs megadva'));
        $action = $isNew ? 'jött létre' : 'módosult';
        $subject = sprintf(
            'Munkalap %s: %s',
            $isNew ? 'létrejött' : 'módosult',
            $worksheet->getCode() ?: ('#' . $worksheet->getId()),
        );
        $templateContext = [
            'worksheet' => $worksheet,
            'companyName' => $companyName,
            'machineName' => $machineName,
            'statusName' => $statusName,
            'action' => $action,
        ];

        $selectedContactIds = $this->extractSelectedContactIds($worksheetData['contacts'] ?? []);
        $partnerRecipients = $worksheet->getPartner() === null
            ? []
            : $this->partnerContactRepository->findWorksheetNotificationRecipients(
                $worksheet->getPartner(),
                $selectedContactIds,
            );
        $userRecipients = $this->userRepository->findUsersByNotificationEventCode($eventCode);

        $sent = 0;
        $failed = 0;

        foreach ($partnerRecipients as $contact) {
            $recipientName = trim((string) $contact->getName());
            $recipientEmail = trim((string) $contact->getEmail());
            $html = $this->twig->render('mails/worksheet_partner_notification.html.twig', $templateContext + [
                'recipientName' => $recipientName,
            ]);

            if ($this->sendRecipient($recipientEmail, $recipientName, $subject, $html, $eventName, $eventCode)) {
                ++$sent;
            } else {
                ++$failed;
            }
        }

        foreach ($userRecipients as $user) {
            $recipientName = trim((string) $user->getName());
            $recipientEmail = trim((string) $user->getEmail());
            $html = $this->twig->render('mails/worksheet_user_notification.html.twig', $templateContext + [
                'recipientName' => $recipientName,
            ]);

            if ($this->sendRecipient($recipientEmail, $recipientName, $subject, $html, $eventName, $eventCode)) {
                ++$sent;
            } else {
                ++$failed;
            }
        }

        return [
            'event_code' => $eventCode,
            'sent' => $sent,
            'failed' => $failed,
            'partner_recipients' => count($partnerRecipients),
            'user_recipients' => count($userRecipients),
        ];
    }

    private function sendRecipient(
        string $recipientEmail,
        string $recipientName,
        string $subject,
        string $html,
        string $eventName,
        string $eventCode,
    ): bool {
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('Worksheet notification skipped because the recipient email is invalid.', [
                'event_code' => $eventCode,
                'recipient_email' => $recipientEmail,
            ]);

            return false;
        }

        try {
            $mail = $this->createMailer();
            $mail->addAddress($recipientEmail, $recipientName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $this->createPlainTextBody($html);
            $mail->send();
        } catch (\Throwable $exception) {
            $this->logger->error('Worksheet notification email could not be sent.', [
                'event_code' => $eventCode,
                'recipient_email' => $recipientEmail,
                'exception' => $exception,
            ]);

            return false;
        }

        try {
            $emailLog = (new EmailLog())
                ->setEventName($eventName)
                ->setEventCode($eventCode)
                ->setEmailHtml($html)
                ->setRecipientEmail($recipientEmail)
                ->setRecipientName($recipientName !== '' ? $recipientName : $recipientEmail)
                ->setDatetimeAdd(new \DateTimeImmutable());
            $this->emailLogRepository->save($emailLog);
        } catch (\Throwable $exception) {
            $this->logger->error('The successfully sent worksheet notification could not be logged.', [
                'event_code' => $eventCode,
                'recipient_email' => $recipientEmail,
                'exception' => $exception,
            ]);
        }

        return true;
    }

    private function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $host = trim((string) $this->parameterBag->get('app.mail.host'));

        if ($host !== '') {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = (bool) $this->parameterBag->get('app.mail.smtp_auth');
            $mail->Username = (string) $this->parameterBag->get('app.mail.username');
            $mail->Password = (string) $this->parameterBag->get('app.mail.password');
            $mail->Port = (int) $this->parameterBag->get('app.mail.port');

            $encryption = trim((string) $this->parameterBag->get('app.mail.encryption'));
            if ($encryption !== '' && mb_strtolower($encryption) !== 'none') {
                $mail->SMTPSecure = $encryption;
            }
        }

        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom(
            (string) $this->parameterBag->get('app.mail.from'),
            (string) $this->parameterBag->get('app.mail.from_name'),
        );

        return $mail;
    }

    private function resolveMachine(Worksheet $worksheet, array $worksheetData): ?Machine
    {
        $rentalMachine = $worksheet->getMachineRental()?->getMachine();
        if ($rentalMachine !== null) {
            return $rentalMachine;
        }

        $machineId = $worksheetData['machine_id'] ?? null;
        if (is_numeric($machineId)) {
            $machine = $this->machineRepository->find((int) $machineId);
            if ($machine !== null) {
                return $machine;
            }
        }

        $machineCode = trim((string) ($worksheetData['machine_code'] ?? ''));

        return $machineCode === '' ? null : $this->machineRepository->findOneBy(['code' => $machineCode]);
    }

    private function formatMachineName(?Machine $machine, array $worksheetData): string
    {
        if ($machine !== null) {
            $title = trim((string) $machine->getTitle());
            $code = trim((string) $machine->getCode());

            if ($title !== '' && $code !== '' && $title !== $code) {
                return sprintf('%s (%s)', $title, $code);
            }

            return $title !== '' ? $title : ($code !== '' ? $code : 'Ismeretlen gép');
        }

        foreach (['machine_name', 'machine_title', 'machine_code'] as $field) {
            $value = trim((string) ($worksheetData[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'Ismeretlen gép';
    }

    /**
     * @return int[]
     */
    private function extractSelectedContactIds(mixed $contacts): array
    {
        if (!is_array($contacts)) {
            return [];
        }

        $selectedIds = [];
        foreach ($contacts as $contact) {
            if (is_numeric($contact)) {
                $selectedIds[] = (int) $contact;
                continue;
            }

            if (!is_array($contact)) {
                continue;
            }

            $function = mb_strtolower(trim((string) (
                $contact['function']
                ?? $contact['data']['function']
                ?? ''
            )));
            if (in_array($function, ['delete', 'remove'], true)) {
                continue;
            }

            $id = $contact['id'] ?? null;
            if (is_numeric($id) && (int) $id > 0) {
                $selectedIds[] = (int) $id;
            }
        }

        return array_values(array_unique($selectedIds));
    }

    private function createPlainTextBody(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
