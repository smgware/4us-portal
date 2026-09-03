<?php

namespace App\Controller;

use App\Repository\EmailLogRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmailLogsController extends BaseController
{
    private const ITEMS_PER_PAGE = 20;

    #[Route('/email-logs/list', name: 'list_email_logs', methods: ['POST'])]
    public function listEmailLogs(Request $request, EmailLogRepository $emailLogRepository): Response
    {
        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen szűrési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $pageRaw = $request->request->get('page', 1);
        $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;
        $list = $emailLogRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        return $this->response(true, [
            'template' => 'email_logs/list_email_logs.html.twig',
            'templateData' => [
                'records' => $list['records'],
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
            ],
            'data' => [
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
            ],
        ]);
    }

    #[Route('/email-logs/view', name: 'view_email_log', methods: ['GET'])]
    public function viewEmailLog(Request $request, EmailLogRepository $emailLogRepository): Response
    {
        $idRaw = $request->query->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $emailLog = $id > 0 ? $emailLogRepository->find($id) : null;

        if ($emailLog === null) {
            return $this->response(false, [
                'data' => ['error' => 'Az e-mail naplóbejegyzés nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        return $this->response(true, [
            'template' => 'email_logs/view_email_log.html.twig',
            'templateData' => ['item' => $emailLog],
        ]);
    }
}
