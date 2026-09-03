<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends BaseController
{
    #[Route('/profile', name: 'profile')]
    #[Route('/profil', name: 'profile_hu')]
    public function index(): Response
    {
        if (!$this->getUser() instanceof User) {
            return $this->redirectToRoute('app_login_index');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/profile/save', name: 'profile_save', methods: ['POST'])]
    public function save(Request $request, UserRepository $userRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->response(false, [
                'data' => ['error' => 'Nincs bejelentkezett felhasznalo.'],
            ], 'json', Response::HTTP_FORBIDDEN);
        }

        $name = trim((string) $request->request->get('name', ''));
        $email = trim((string) $request->request->get('email', ''));
        $phone = trim((string) $request->request->get('phone', ''));
        $image = trim((string) $request->request->get('image', ''));

        if ($name === '' || $email === '') {
            return $this->response(false, [
                'data' => ['error' => 'A nev es az email megadasa kotelezo.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response(false, [
                'data' => ['error' => 'Ervenyes email cimet adj meg.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $emailOwner = $userRepository->findOneBy(['email' => $email]);
        if ($emailOwner && $emailOwner->getId() !== $user->getId()) {
            return $this->response(false, [
                'data' => ['error' => 'Ez az email cim mar hasznalatban van.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $user
            ->setName($name)
            ->setEmail($email)
            ->setUserName($email)
            ->setPhone($phone !== '' ? $phone : null)
            ->setImage($image !== '' ? $image : null);

        $userRepository->save($user);

        return $this->response(true, [
            'data' => [
                'id' => $user->getId(),
            ],
        ]);
    }

    #[Route('/profile/change-password', name: 'profile_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->response(false, [
                'data' => ['error' => 'Nincs bejelentkezett felhasznalo.'],
            ], 'json', Response::HTTP_FORBIDDEN);
        }

        $password = trim((string) $request->request->get('password', ''));
        $passwordAgain = trim((string) $request->request->get('password_again', ''));

        if ($password === '' || $password !== $passwordAgain) {
            return $this->response(false, [
                'data' => ['error' => 'A ket jelszo nem egyezik vagy ures.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $userRepository->save($user);

        return $this->response(true, [
            'data' => [
                'id' => $user->getId(),
            ],
        ]);
    }
}
