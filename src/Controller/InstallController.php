<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class InstallController extends AbstractController
{
    #[Route('/install-admin', name: 'app_install_admin')]
    public function installAdmin(
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if ($userRepository->count([]) > 0) {
            return $this->json([
                'status' => 'skipped',
                'message' => 'Admin létrehozás kihagyva, mert már létezik felhasználó.'
            ], 409);
        }

        $admin = new User();
        $admin->setName('Portal Sigma Admin');
        $admin->setEmail('admin@portal-sigma.local');
        $admin->setUserName('admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, 'Admin1234!'));

        $entityManager->persist($admin);
        $entityManager->flush();

        return $this->json([
            'status' => 'created',
            'username' => 'admin',
            'email' => 'admin@portal-sigma.local',
            'password' => 'Admin1234!',
            'warning' => 'Első belépés után azonnal változtasd meg a jelszót, majd tiltsd vagy töröld ezt az install route-ot.'
        ]);
    }   

	#[Route('/update-admin', name: 'update_admin')]
    public function updateAdmin(
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {


		$user = $entityManager
			->getRepository(User::class)
			->findOneBy(['userName' => 'admin']);

		if ($user) {
			$user->setPassword(
				$passwordHasher->hashPassword($user, 'admin')
			);

			$entityManager->flush();
		}else{
            return $this->json([
                'status' => 'skipped',
                'message' => 'Admin létrehozás kihagyva, mert nem létezik felhasználó.'
            ], 409);			
		}

        return $this->json([
            'status' => 'updated'
        ]);
    }
}
