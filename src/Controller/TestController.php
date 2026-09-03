<?php

namespace App\Controller;

use App\Repository\CompareMachinesQuoteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use PHPMailer\PHPMailer\PHPMailer;

class TestController extends BaseController
{
    #[Route('/mail-test', name: 'email_test')]
    public function email_test(): Response
    {
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
        $mail->addAddress("sumegicsabi@gmail.com");
        $mail->isHTML(true);
        $mail->Subject = 'Teszt üzenetküldés';

        $mail->Body = '
            <!DOCTYPE html>
            <html lang="hu">
            <head>
                <meta charset="UTF-8">
            </head>
            <body style="margin:0; padding:20px; background:#f4f4f4; font-family:Arial, sans-serif;">

                <div style="
                    max-width:600px;
                    margin:0 auto;
                    background:#ffffff;
                    border-radius:8px;
                    padding:30px;
                    box-shadow:0 2px 8px rgba(0,0,0,0.1);
                ">

                    <h2 style="color:#333333; margin-top:0;">
                        Teszt e-mail
                    </h2>

                    <p style="color:#555555; font-size:15px; line-height:1.6;">
                        Ez egy <strong>HTML formázással</strong> elküldött teszt e-mail.
                    </p>

                    <div style="
                        background:#f0f7ff;
                        border-left:4px solid #0d6efd;
                        padding:15px;
                        margin:20px 0;
                    ">
                        Az e-mail küldés megfelelően működik.
                    </div>

                    <a href="https://example.com"
                       style="
                           display:inline-block;
                           background:#0d6efd;
                           color:#ffffff;
                           text-decoration:none;
                           padding:10px 18px;
                           border-radius:5px;
                       ">
                        Teszt gomb
                    </a>

                    <hr style="border:0; border-top:1px solid #dddddd; margin:30px 0;">

                    <small style="color:#999999;">
                        Automatikusan generált teszt üzenet.
                    </small>

                </div>

            </body>
            </html>
        ';
        $mail->AltBody = '';
        $mail->send();

        return $this->response(true, [
            'data' => [],
        ]);

    }
}
