<?php

namespace App\Controller;

use App\Entity\NotificationEvent;
use App\Repository\NotificationEventRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NotificationEventsController extends BaseController
{
    private const ITEMS_PER_PAGE = 10;

    #[Route('/notification-events/add', name: 'index_notification_events_add', methods: ['GET'])]
    public function indexAdd(
        Request $request,
        NotificationEventRepository $notificationEventRepository,
    ): Response {
        $idRaw = $request->query->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $item = $id > 0 ? $notificationEventRepository->find($id) : null;

        if ($id > 0 && !$item) {
            return $this->response(false, [
                'data' => ['error' => 'Az értesítési esemény nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        return $this->response(true, [
            'template' => 'notification_events/index_add.html.twig',
            'templateData' => ['item' => $item],
            'data' => ['mode' => $item ? 'update' : 'insert'],
        ]);
    }

    #[Route('/notification-events/save', name: 'save_notification_events', methods: ['POST'])]
    public function saveNotificationEvent(
        Request $request,
        NotificationEventRepository $notificationEventRepository,
    ): Response {
        $idRaw = $request->request->get('id');
        if ($idRaw !== null && $idRaw !== '' && !is_numeric($idRaw)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen értesítési esemény azonosító.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $notificationEvent = $id > 0 ? $notificationEventRepository->find($id) : null;
        $isNew = $id <= 0;

        if (!$isNew && !$notificationEvent) {
            return $this->response(false, [
                'data' => ['error' => 'Az értesítési esemény nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $name = trim((string) $request->request->get('name', ''));
        $code = mb_strtoupper(trim((string) $request->request->get('code', '')));
        $status = trim((string) $request->request->get('status', '1'));

        if ($name === '') {
            return $this->response(false, [
                'data' => ['error' => 'A név megadása kötelező.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }
        if (mb_strlen($name) > 255) {
            return $this->response(false, [
                'data' => ['error' => 'A név legfeljebb 255 karakter lehet.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }
        if ($code === '') {
            return $this->response(false, [
                'data' => ['error' => 'A kód megadása kötelező.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }
        if (mb_strlen($code) > 100) {
            return $this->response(false, [
                'data' => ['error' => 'A kód legfeljebb 100 karakter lehet.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }
        if (!in_array($status, ['0', '1'], true)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen státusz.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $codeOwner = $notificationEventRepository->findOneBy(['code' => $code]);
        if ($codeOwner && $codeOwner->getId() !== $notificationEvent?->getId()) {
            return $this->response(false, [
                'data' => ['error' => 'Ezzel a kóddal már létezik értesítési esemény.'],
            ], 'json', Response::HTTP_CONFLICT);
        }

        $now = new \DateTimeImmutable();
        if ($isNew) {
            $notificationEvent = (new NotificationEvent())
                ->setDatetimeAdd($now)
                ->setUidAdd($this->getUser()?->getId());
        }

        $notificationEvent
            ->setName($name)
            ->setCode($code)
            ->setStatus($status)
            ->setDatetimeLast($now)
            ->setUidLast($this->getUser()?->getId());

        try {
            $notificationEventRepository->save($notificationEvent);
        } catch (UniqueConstraintViolationException) {
            return $this->response(false, [
                'data' => ['error' => 'Ezzel a kóddal már létezik értesítési esemény.'],
            ], 'json', Response::HTTP_CONFLICT);
        }

        return $this->response(true, [
            'data' => [
                'id' => $notificationEvent->getId(),
                'name' => $notificationEvent->getName(),
                'code' => $notificationEvent->getCode(),
                'status' => $notificationEvent->getStatus(),
                'mode' => $isNew ? 'insert' : 'update',
            ],
        ]);
    }

    #[Route('/notification-events/delete', name: 'delete_notification_events', methods: ['POST', 'DELETE'])]
    public function deleteNotificationEvent(
        Request $request,
        NotificationEventRepository $notificationEventRepository,
    ): Response {
        $id = $request->request->getInt('id');
        $notificationEvent = $id > 0 ? $notificationEventRepository->find($id) : null;

        if (!$notificationEvent) {
            return $this->response(false, [
                'data' => ['error' => 'Az értesítési esemény nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $notificationEventRepository->delete($notificationEvent);

        return $this->response(true, [
            'data' => [
                'id' => $id,
                'deleted' => true,
            ],
        ]);
    }

    #[Route('/notification-events/list', name: 'list_notification_events', methods: ['GET', 'POST'])]
    public function listNotificationEvents(
        Request $request,
        NotificationEventRepository $notificationEventRepository,
    ): Response {
        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen szűrési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $pageRaw = $request->request->get('page', $request->query->get('page', 1));
        $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;
        $list = $notificationEventRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        return $this->response(true, [
            'template' => 'notification_events/list_notification_events.html.twig',
            'templateData' => [
                'records' => $list['records'],
                'filters' => $filters,
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
            ],
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
