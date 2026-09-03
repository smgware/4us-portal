<?php

namespace App\Controller;

use App\Repository\WorksheetTypeRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SettingsController extends BaseController
{
    #[Route('/settings', name: 'index_settings')]
    public function index(): Response
    {
        return $this->response(true, [
            'template' => 'settings/index.html.twig',
        ], 'html');
    }

    #[Route('/settings/machine-categories', name: 'main_settings_machine_categories')]
    public function settings_categories(): Response
    {
        $categories = [];

        return $this->response(true, [
            'template' => 'machine_categories/main.html.twig',
            'templateData' => [
                'categories' => $categories,
            ],
            'data' => [
                'count' => count($categories),
            ],
        ]);
    }

    #[Route('/settings/permissions', name: 'main_settings_permissions')]
    public function settings_permissions(): Response
    {
        $Permissions = [];

        return $this->response(true, [
            'template' => 'permissions/main.html.twig',
            'templateData' => [
            ],
            'data' => [
                'count' => count($Permissions),
            ],
        ]);
    }

    #[Route('/settings/users', name: 'main_settings_users')]
    public function settingsUsers(): Response
    {
        $users = [];

        return $this->response(true, [
            'template' => 'users/main.html.twig',
            'templateData' => [
                'users' => $users,
            ],
            'data' => [
                'count' => count($users),
            ],
        ]);
    }

    #[Route('/settings/worksheet-types', name: 'main_settings_worksheet_types')]
    public function settingsWorksheetTypes(): Response
    {
        $worksheetTypes = [];

        return $this->response(true, [
            'template' => 'worksheet_types/main.html.twig',
            'templateData' => [
                'worksheetTypes' => $worksheetTypes,
            ],
            'data' => [
                'count' => count($worksheetTypes),
            ],
        ]);
    }

    #[Route('/settings/worksheet-status-types', name: 'main_settings_worksheet_status_types')]
    public function settingsWorksheetStatusTypes(WorksheetTypeRepository $worksheetTypeRepository): Response
    {
        return $this->response(true, [
            'template' => 'worksheet_status_types/main.html.twig',
            'templateData' => [
                'worksheetTypes' => $worksheetTypeRepository->findBy([], ['title' => 'ASC']),
            ],
        ]);
    }

    #[Route('/settings/notification-events', name: 'main_settings_notification_events')]
    public function settingsNotificationEvents(): Response
    {
        return $this->response(true, [
            'template' => 'notification_events/main.html.twig',
        ]);
    }

    #[Route('/settings/email-logs', name: 'main_settings_email_logs')]
    public function settingsEmailLogs(): Response
    {
        return $this->response(true, [
            'template' => 'email_logs/main.html.twig',
        ]);
    }

    #[Route('/settings/partners', name: 'main_settings_partners')]
    public function settingsPartners(): Response
    {
        $partners = [];

        return $this->response(true, [
            'template' => 'partners/main.html.twig',
            'templateData' => [
                'partners' => $partners,
            ],
            'data' => [
                'count' => count($partners),
            ],
        ]);
    }

}
