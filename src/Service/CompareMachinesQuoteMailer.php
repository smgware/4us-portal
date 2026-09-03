<?php

namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

final class CompareMachinesQuoteMailer
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly Environment $twig,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function send(array $context): void
    {
        $customer = is_array($context['customer'] ?? null) ? $context['customer'] : [];
        $selectedMachine = is_array($context['selectedMachine'] ?? null) ? $context['selectedMachine'] : [];
        $otherMachine = is_array($context['otherMachine'] ?? null) ? $context['otherMachine'] : [];
        $recipientEmail = trim((string) ($customer['email'] ?? ''));
        $recipientName = trim((string) ($customer['name'] ?? ''));

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Érvénytelen címzett e-mail-cím.');
        }

        $mail = $this->createMailer();
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->isHTML(true);

        $projectDirectory = (string) $this->parameterBag->get('kernel.project_dir');
        $logoPath = $projectDirectory . '/public/assets/img/4us_logo_mail.png';
        $logoEmbedded = is_file($logoPath);
        if ($logoEmbedded) {
            $mail->addEmbeddedImage(
                $logoPath,
                '4us-logo-mail',
                '4us_logo_mail.png',
                PHPMailer::ENCODING_BASE64,
                'image/png',
            );
        }

        $context['logoEmbedded'] = $logoEmbedded;
        $mail->Subject = sprintf(
            'Darupár összehasonlítás: %s / %s - 4US Zrt.',
            (string) ($selectedMachine['name'] ?? ''),
            (string) ($otherMachine['name'] ?? ''),
        );
        $mail->Body = $this->twig->render('compare_machines/mail_compare_machines.html.twig', $context);
        $mail->AltBody = sprintf(
            "Kedves %s!\n\nAjánlatkérés kódja: %s\nKiválasztott modell: %s (%s)\nA darupár másik tagja: %s (%s)\nFutamidő: %d hónap\nBecsült teljes költségek: %s / %s\n\nÜdvözlettel:\na 4US Zrt. csapata\nhello@4uszrt.hu\n+36 1 99 888 99",
            $recipientName,
            (string) ($context['quoteCode'] ?? ''),
            (string) ($selectedMachine['name'] ?? ''),
            (string) ($selectedMachine['type'] ?? ''),
            (string) ($otherMachine['name'] ?? ''),
            (string) ($otherMachine['type'] ?? ''),
            (int) ($context['months'] ?? 0),
            (string) ($selectedMachine['totalFormatted'] ?? ''),
            (string) ($otherMachine['totalFormatted'] ?? ''),
        );
        $mail->send();
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
}
