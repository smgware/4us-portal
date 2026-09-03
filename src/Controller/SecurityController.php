<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login_index', methods: ['GET'])]
    public function index(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('index_dashboard');
        }

        return $this->render('login/index.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/login/form', name: 'login_form', methods: ['GET'])]
    public function loginForm(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('login/login_form.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/login/password-recovery', name: 'password_recovery_form', methods: ['GET'])]
    public function passwordRecoveryForm(): Response
    {
        return $this->render('login/password_recovery.html.twig');
    }

    #[Route('/login/passwordreset/form', name: 'password_reset_form', methods: ['GET'])]
    public function passwordResetForm(Request $request, UserRepository $userRepository): Response
    {
        $token = trim((string) $request->query->get('token', ''));
        $user = $token !== '' ? $userRepository->findOneBy(['passwordRecoveryToken' => $token]) : null;

        if (!$user) {
            return new Response('<div class="alert alert-danger">Érvénytelen vagy lejárt visszaállító link.</div><a href="#" class="forgot-link" data-auth-page="login">Vissza a bejelentkezéshez</a>', Response::HTTP_NOT_FOUND);
        }

        return $this->render('login/passwordreset.html.twig', [
            'token' => $token,
        ]);
    }

    #[Route('/login/passwordreset/check', name: 'password_reset_token_check', methods: ['POST'])]
    public function checkPasswordResetToken(Request $request, UserRepository $userRepository): JsonResponse
    {
        $token = trim((string) $request->request->get('token', ''));
        $user = $token !== '' ? $userRepository->findOneBy(['passwordRecoveryToken' => $token]) : null;

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Érvénytelen vagy lejárt visszaállító token.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/login/passwordreset/save', name: 'password_reset_save', methods: ['POST'])]
    public function passwordResetSave(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $token = trim((string) $request->request->get('token', ''));

        if ($token === '') {
            return $this->requestPasswordRecovery($request, $userRepository, $entityManager);
        }

        $password = (string) $request->request->get('password', '');
        $passwordConfirm = (string) $request->request->get('password_confirm', '');

        if (strlen($password) < 5) {
            return $this->json([
                'success' => false,
                'message' => 'Az új jelszónak legalább 5 karakter hosszúnak kell lennie.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($password !== $passwordConfirm) {
            return $this->json([
                'success' => false,
                'message' => 'A két jelszó nem egyezik.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['passwordRecoveryToken' => $token]);
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Érvénytelen vagy lejárt visszaállító token.',
            ], Response::HTTP_NOT_FOUND);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setPasswordRecoveryToken(null);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'A jelszó sikeresen frissült. Most már bejelentkezhetsz.',
        ]);
    }

    private function requestPasswordRecovery(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $email = trim((string) $request->request->get('email', ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'success' => false,
                'message' => 'Adj meg egy érvényes email címet.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Ehhez az email címhez nem található felhasználó.',
            ], Response::HTTP_NOT_FOUND);
        }

        $token = bin2hex(random_bytes(32));
        $user->setPasswordRecoveryToken($token);
        $entityManager->flush();

        try {
            $this->sendPasswordRecoveryEmail($request, $email, $token);
        } catch (\Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => 'A token mentése sikerült, de az email küldése nem sikerült: ' . $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'success' => true,
            'message' => 'Elküldtük a jelszó-visszaállító emailt.',
        ]);
    }


    #[Route('/login/auth', name: 'login_auth', methods: ['POST'])]
    public function ajaxAuth(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        CsrfTokenManagerInterface $csrfTokenManager,
        TokenStorageInterface $tokenStorage
    ): JsonResponse {
        $username = trim((string) ($request->request->get('_username') ?: $request->request->get('username') ?: $request->request->get('email')));
        $password = (string) ($request->request->get('_password') ?: $request->request->get('password'));
        $csrfToken = (string) $request->request->get('_csrf_token', '');

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('authenticate', $csrfToken))) {
            return $this->json([
                'success' => false,
                'message' => 'Érvénytelen biztonsági token. Frissítsd az oldalt és próbáld újra.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($username === '' || $password === '') {
            return $this->json([
                'success' => false,
                'message' => 'Add meg az email/felhasználó mezőt és a jelszót.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = filter_var($username, FILTER_VALIDATE_EMAIL)
            ? $userRepository->findOneBy(['email' => $username])
            : $userRepository->findOneBy(['userName' => $username]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json([
                'success' => false,
                'message' => 'Hibás email/felhasználó vagy jelszó.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $request->getSession()->migrate(true);

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->setToken($token);
        $request->getSession()->set('_security_main', serialize($token));
        $request->getSession()->save();

        return $this->json([
            'success' => true,
            'message' => 'Sikeres bejelentkezés.',
            'redirect' => $this->generateUrl('index_dashboard'),
        ]);
    }

    #[Route('/login/check', name: 'app_login', methods: ['POST'])]
    public function loginCheck(): void
    {
        throw new \LogicException('This route is handled by Symfony security.');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This route is handled by Symfony security.');
    }

    private function sendPasswordRecoveryEmail(Request $request, string $email, string $token): void
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
        $resetUrl = $request->getSchemeAndHttpHost() . $request->getBaseUrl() . '/login?token=' . urlencode($token);

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Jelszó visszaállítása';
        $safeResetUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $mail->Body = '<p>Jelszó-visszaállítást kértél.</p><p><a href="' . $safeResetUrl . '">Új jelszó megadása</a></p>';
        $mail->AltBody = 'Jelszó-visszaállítás: ' . $resetUrl;
        $mail->send();
    }
}
