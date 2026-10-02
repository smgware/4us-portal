<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserNotificationEvent;
use App\Entity\UserPermission;
use App\Repository\NotificationEventRepository;
use App\Repository\PermissionRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class UsersController extends BaseController
{
    private const ITEMS_PER_PAGE = 10;

    #[Route('/users/add', name: 'index_users_add')]
    public function indexAdd(
        Request $request,
        UserRepository $userRepository,
        PermissionRepository $permissionRepository,
        NotificationEventRepository $notificationEventRepository,
    ): Response {
        $idRaw = $request->query->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $item = $id > 0 ? $userRepository->find($id) : null;
        $defaultPermission = $item ? null : $permissionRepository->findOneBy(['code' => 'ROLE_USER']);

        return $this->response(true, [
            'template' => 'users/index_add.html.twig',
            'templateData' => [
                'item' => $item,
                'permissions' => $permissionRepository->findBy([], ['title' => 'ASC']),
                'selectedPermissionId' => $item?->getPermission()?->getId() ?? $defaultPermission?->getId(),
                'notificationEvents' => $notificationEventRepository->findBy([], [
                    'status' => 'DESC',
                    'name' => 'ASC',
                ]),
                'selectedNotificationEventIds' => $item
                    ? array_values(array_filter(array_map(
                        static fn (UserNotificationEvent $assignment): ?int => $assignment->getNotificationEvent()?->getId(),
                        $item->getUserNotificationEvents()->toArray(),
                    )))
                    : [],
            ],
        ]);
    }

    #[Route('/users/save', name: 'save_users', methods: ['POST'])]
    public function saveUsers(
        Request $request,
        UserRepository $userRepository,
        PermissionRepository $permissionRepository,
        NotificationEventRepository $notificationEventRepository,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $postData = [];
        foreach ($request->request->all() as $key => $value) {
            $postData[$key] = is_string($value) ? trim($value) : $value;
        }

        $id = (int) ($postData['id'] ?? 0);
        $user = $id > 0 ? $userRepository->find($id) : null;
        $isNew = $id <= 0;

        if (!$isNew && !$user) {
            return $this->response(false, [
                'data' => ['error' => 'A felhasználó nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        if ($isNew) {
            $user = new User();
        }

        $name = (string) ($postData['name'] ?? '');
        $email = (string) ($postData['email'] ?? '');
        $username = (string) ($postData['username'] ?? '');
        $password = (string) ($postData['password'] ?? '');
        $passwordAgain = (string) ($postData['password_again'] ?? '');

        if ($name === '' || $email === '' || $username === '') {
            return $this->response(false, [
                'data' => ['error' => 'A nev, email es username megadasa kotelezo.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response(false, [
                'data' => ['error' => 'Ervenyes email cimet adj meg.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        if ($isNew && $password === '') {
            return $this->response(false, [
                'data' => ['error' => 'Uj felhasznalonal a jelszo megadasa kotelezo.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        if ($password !== '' && $password !== $passwordAgain) {
            return $this->response(false, [
                'data' => ['error' => 'A ket jelszo nem egyezik.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode((string) ($postData['data'] ?? ''), true);
        if (!is_array($data) || !is_array($data['user_notification_events'] ?? null)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen értesítési esemény adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $userNotificationEventSettings = [];
        foreach ($data['user_notification_events'] as $userNotificationEventInput) {
            if (!is_array($userNotificationEventInput)) {
                return $this->response(false, [
                    'data' => ['error' => 'Érvénytelen értesítési esemény beállítás.'],
                ], 'json', Response::HTTP_BAD_REQUEST);
            }

            $notificationEventIdValue = is_scalar($userNotificationEventInput['id'] ?? null)
                ? trim((string) $userNotificationEventInput['id'])
                : '';

            if ($notificationEventIdValue === '' || !ctype_digit($notificationEventIdValue)) {
                return $this->response(false, [
                    'data' => ['error' => 'Érvénytelen értesítési esemény azonosító.'],
                ], 'json', Response::HTTP_BAD_REQUEST);
            }

            $notificationEventId = (int) $notificationEventIdValue;
            if ($notificationEventId <= 0) {
                return $this->response(false, [
                    'data' => ['error' => 'Érvénytelen értesítési esemény azonosító.'],
                ], 'json', Response::HTTP_BAD_REQUEST);
            }

            $enabledRaw = $userNotificationEventInput['enabled'] ?? null;
            if ($enabledRaw === true || $enabledRaw === 1 || $enabledRaw === '1') {
                $enabled = true;
            } elseif ($enabledRaw === false || $enabledRaw === 0 || $enabledRaw === '0') {
                $enabled = false;
            } else {
                return $this->response(false, [
                    'data' => ['error' => 'Az értesítési esemény enabled értéke csak true vagy false lehet.'],
                ], 'json', Response::HTTP_BAD_REQUEST);
            }

            $userNotificationEventSettings[$notificationEventId] = $enabled;
        }

        $notificationEventsById = [];
        if ($userNotificationEventSettings !== []) {
            foreach ($notificationEventRepository->findBy(['id' => array_keys($userNotificationEventSettings)]) as $notificationEvent) {
                $notificationEventsById[$notificationEvent->getId()] = $notificationEvent;
            }

            if (count($notificationEventsById) !== count($userNotificationEventSettings)) {
                return $this->response(false, [
                    'data' => ['error' => 'A kiválasztott értesítési események egyike nem található.'],
                ], 'json', Response::HTTP_BAD_REQUEST);
            }
        }

        /*
        $emailOwner = $userRepository->findOneBy(['email' => $email]);
        if ($emailOwner && $emailOwner->getId() !== $user->getId()) {
            return $this->response(false, [
                'data' => ['error' => 'Ez az email cim mar hasznalatban van.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }
        */

        $usernameOwner = $userRepository->findOneBy(['userName' => $username]);
        if ($usernameOwner && $usernameOwner->getId() !== $user->getId()) {
            return $this->response(false, [
                'data' => ['error' => 'Ez a username mar hasznalatban van.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $permissionId = (int) ($postData['permission_id'] ?? 0);
        if ($isNew) {
            $defaultPermission = $permissionRepository->findOneBy(['code' => 'ROLE_USER']);
            if ($permissionId <= 0 && $defaultPermission) {
                $permissionId = (int) $defaultPermission->getId();
            }
        }

        $permission = $permissionId > 0 ? $permissionRepository->find($permissionId) : null;

        $signature = (string) ($postData['signature'] ?? '');
        if ($signature !== '') {
            $signaturePrefix = 'data:image/png;base64,';
            if (!str_starts_with($signature, $signaturePrefix)) {
                return $this->response(false, [
                    'data' => ['error' => 'Az aláírás formátuma érvénytelen.'],
                ], 'json', Response::HTTP_BAD_REQUEST);
            }

            $signatureBinary = base64_decode(substr($signature, strlen($signaturePrefix)), true);
            if (
                $signatureBinary === false
                || !str_starts_with($signatureBinary, "\x89PNG\r\n\x1a\n")
                || strlen($signatureBinary) > 2 * 1024 * 1024
            ) {
                return $this->response(false, [
                    'data' => ['error' => 'Az aláírás képe érvénytelen vagy túl nagy.'],
                ], 'json', Response::HTTP_BAD_REQUEST);
            }
        }

        $user
            ->setName($name)
            ->setEmail($email)
            ->setUserName($username)
            ->setPermission($permission)
            ->setPhone((string) ($postData['phone'] ?? '') ?: null)
            ->setImage((string) ($postData['image'] ?? '') ?: null)
            ->setSignature($signature !== '' ? $signature : null)
            ->setStatus((string) ($postData['status'] ?? '1'));

        if ($password !== '') {
            $user->setPassword($passwordHasher->hashPassword($user, $password));
        }

        if ($isNew) {
            $user->setRoles(['ROLE_USER']);
        }

        $permissionUpdatedAt = new \DateTime();
        $currentUserId = $this->getUser()?->getId();
        $selectedUserPermission = null;

        foreach ($user->getUserPermissions()->toArray() as $userPermission) {
            $assignedPermissionId = $userPermission->getPermission()?->getId();
            $isSelectedPermission = $permission !== null
                && $assignedPermissionId === $permission->getId()
                && $selectedUserPermission === null;

            if ($isSelectedPermission) {
                $selectedUserPermission = $userPermission;
                continue;
            }

            $user->removeUserPermission($userPermission);
        }

        if ($permission !== null) {
            if ($selectedUserPermission === null) {
                $selectedUserPermission = (new UserPermission())
                    ->setPermission($permission)
                    ->setDatetimeAdd($permissionUpdatedAt)
                    ->setUidAdd($currentUserId);
                $user->addUserPermission($selectedUserPermission);
            }

            $selectedUserPermission
                ->setDatetimeLast($permissionUpdatedAt)
                ->setUidLast($currentUserId);
        }

        $now = new \DateTimeImmutable();
        $existingUserNotificationEvents = [];
        foreach ($user->getUserNotificationEvents()->toArray() as $userNotificationEvent) {
            $notificationEventId = $userNotificationEvent->getNotificationEvent()?->getId();
            if ($notificationEventId !== null) {
                $existingUserNotificationEvents[$notificationEventId] = $userNotificationEvent;
            }
        }

        foreach ($userNotificationEventSettings as $notificationEventId => $enabled) {
            $existingUserNotificationEvent = $existingUserNotificationEvents[$notificationEventId] ?? null;

            if (!$enabled) {
                if ($existingUserNotificationEvent !== null) {
                    $user->removeUserNotificationEvent($existingUserNotificationEvent);
                }

                continue;
            }

            if ($existingUserNotificationEvent !== null) {
                continue;
            }

            $userNotificationEvent = (new UserNotificationEvent())
                ->setNotificationEvent($notificationEventsById[$notificationEventId])
                ->setUidAdd($currentUserId)
                ->setUidLast($currentUserId)
                ->setDatetimeAdd($now)
                ->setDatetimeLast($now);
            $user->addUserNotificationEvent($userNotificationEvent);
        }

        $userRepository->save($user);

        return $this->response(true, [
            'data' => [
                'id' => $user->getId(),
                'mode' => $isNew ? 'insert' : 'update',
            ],
        ]);
    }

    #[Route('/users/delete', name: 'delete_users', methods: ['DELETE'])]
    public function deleteUsers(Request $request, UserRepository $userRepository): Response
    {
        $idRaw = $request->request->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;

        if ($id <= 0) {
            return $this->response(false, [
                'data' => ['error' => 'Missing id.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->find($id);
        if (!$user) {
            return $this->response(false, [
                'data' => ['error' => 'User not found.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $userRepository->delete($user);

        return $this->response(true, [
            'data' => [
                'id' => $id,
                'deleted' => true,
            ],
        ]);
    }

    #[Route('/users/change-password', name: 'change_user_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $id = $request->request->getInt('id');
        $password = trim((string) $request->request->get('password', ''));
        $passwordAgain = trim((string) $request->request->get('password_again', ''));

        $user = $id > 0 ? $userRepository->find($id) : null;
        if (!$user) {
            return $this->response(false, [
                'data' => ['error' => 'User not found.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        if ($password === '' || $password !== $passwordAgain) {
            return $this->response(false, [
                'data' => ['error' => 'A ket jelszo nem egyezik vagy ures.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $userRepository->save($user);

        return $this->response(true, [
            'data' => ['id' => $user->getId()],
        ]);
    }

    #[Route('/users/list', name: 'list_users', methods: ['GET', 'POST'])]
    public function listUsers(Request $request, UserRepository $userRepository): Response
    {
        $filtersJson = $request->request->get('filters', '{}');
        $filters = json_decode($filtersJson, true);

        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Invalid filters JSON.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $pageRaw = $request->request->get('page', $request->query->get('page', 1));
        $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;
        $list = $userRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        $content = $this->renderView('users/list_users.html.twig', [
            'records' => $list['records'],
            'filters' => $filters,
            'page' => $list['page'],
            'totalPages' => $list['totalPages'],
            'totalRecords' => $list['totalRecords'],
            'itemsPerPage' => $list['itemsPerPage'],
        ]);

        return $this->response(true, [
            'content' => $content,
            'data' => [
                'filters' => $filters,
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
            ],
        ]);
    }
}
