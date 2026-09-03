<?php

namespace App\Controller;

use App\Entity\WorksheetType;
use App\Repository\WorksheetTypeRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WorksheetTypesController extends BaseController
{
    private const ITEMS_PER_PAGE = 4;

    #[Route('/worksheet-types', name: 'index_worksheet_types')]
    public function index(): Response
    {
        return $this->render('worksheet_types/index.html.twig');
    }

    #[Route('/worksheet-types/add', name: 'index_worksheet_types_add')]
    public function indexAdd(Request $request, WorksheetTypeRepository $worksheetTypeRepository): Response
    {
        $idRaw = $request->query->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;

        $item = $id > 0 ? $worksheetTypeRepository->find($id) : null;

        return $this->response(true, [
            'template' => 'worksheet_types/index_add.html.twig',
            'templateData' => [
                'item' => $item,
            ],
            'data' => [],
        ]);
    }

    #[Route('/worksheet-types/save-worksheet-types', name: 'save_worksheet_types', methods: ['POST'])]
    public function saveWorksheetTypes(Request $request, WorksheetTypeRepository $worksheetTypeRepository): Response
    {
        $postData = [];
        foreach ($request->request->all() as $key => $value) {
            $postData[$key] = is_string($value) ? trim($value) : $value;
        }

        $id = (int) ($postData['id'] ?? 0);
        $worksheetType = $id > 0 ? $worksheetTypeRepository->find($id) : null;
        $isNew = $worksheetType === null;

        if ($isNew) {
            $worksheetType = new WorksheetType();
            $worksheetType->setDatetimeAdd(new \DateTimeImmutable());
            $worksheetType->setUidAdd($this->getUser()?->getId());
        }

        $title = (string) ($postData['title'] ?? $postData['name'] ?? '');
        $code = (string) ($postData['code'] ?? '');
        if ($code === '') {
            $code = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
        }

        $worksheetType
            ->setTitle($title)
            ->setCode($code)
            ->setStatus((string) ($postData['status'] ?? ''));

        $worksheetType->setDatetimeLast(new \DateTimeImmutable());
        $worksheetType->setUidLast($this->getUser()?->getId());

        $worksheetTypeRepository->save($worksheetType);

        return $this->response(true, [
            'template' => null,
            'templateData' => [],
            'data' => [
                'id' => $worksheetType->getId(),
                'mode' => $isNew ? 'insert' : 'update',
                'post' => $postData,
            ],
        ]);
    }

    #[Route('/worksheet-types/delete-worksheet-type', name: 'delete_worksheet_type', methods: ['DELETE'])]
    public function deleteWorksheetType(Request $request, WorksheetTypeRepository $worksheetTypeRepository): Response
    {
        $id = $request->request->getInt('id');
        if ($id <= 0) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Missing id.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $worksheetType = $worksheetTypeRepository->find($id);
        if (!$worksheetType) {
            return $this->response(false, [
                'data' => [
                    'id' => $id,
                    'error' => 'Worksheet type not found.',
                ],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $worksheetTypeRepository->delete($worksheetType);

        return $this->response(true, [
            'data' => [
                'id' => $id,
                'deleted' => true,
            ],
        ]);
    }

    #[Route('/worksheet-types/list', name: 'list_worksheet_types', methods: ['GET', 'POST'])]
    public function listWorksheetTypes(Request $request, WorksheetTypeRepository $worksheetTypeRepository): Response
    {
        $filtersJson = $request->request->get('filters', '{}');
        $filters = json_decode($filtersJson, true);

        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Invalid filters JSON.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $pageRaw = $request->request->get('page', $request->query->get('page', 1));
        $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;
        $list = $worksheetTypeRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        $content = $this->renderView('worksheet_types/list_worksheet_types.html.twig', [
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
