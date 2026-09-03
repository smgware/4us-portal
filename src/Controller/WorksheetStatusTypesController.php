<?php

namespace App\Controller;

use App\Entity\WorksheetStatusType;
use App\Repository\WorksheetRepository;
use App\Repository\WorksheetStatusTypeRepository;
use App\Repository\WorksheetTypeRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WorksheetStatusTypesController extends BaseController
{
    private const ITEMS_PER_PAGE = 10;

    #[Route('/worksheet-status-types', name: 'index_worksheet_status_types')]
    public function index(WorksheetTypeRepository $worksheetTypeRepository): Response
    {
        return $this->render('worksheet_status_types/index.html.twig', [
            'worksheetTypes' => $worksheetTypeRepository->findBy([], ['title' => 'ASC']),
        ]);
    }

    #[Route('/worksheet-status-types/add', name: 'index_worksheet_status_types_add', methods: ['GET'])]
    public function indexAdd(
        Request $request,
        WorksheetStatusTypeRepository $worksheetStatusTypeRepository,
        WorksheetTypeRepository $worksheetTypeRepository,
    ): Response {
        $idRaw = $request->query->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $item = $id > 0 ? $worksheetStatusTypeRepository->find($id) : null;

        if ($id > 0 && !$item) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap státusz nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        return $this->response(true, [
            'template' => 'worksheet_status_types/index_add.html.twig',
            'templateData' => [
                'item' => $item,
                'worksheetTypes' => $worksheetTypeRepository->findBy([], ['title' => 'ASC']),
            ],
            'data' => [
                'mode' => $item ? 'update' : 'insert',
            ],
        ]);
    }

    #[Route('/worksheet-status-types/save', name: 'save_worksheet_status_types', methods: ['POST'])]
    public function saveWorksheetStatusTypes(
        Request $request,
        WorksheetStatusTypeRepository $worksheetStatusTypeRepository,
        WorksheetTypeRepository $worksheetTypeRepository,
        WorksheetRepository $worksheetRepository,
    ): Response {
        $idRaw = $request->request->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $worksheetTypeId = $request->request->getInt('worksheet_type_id');
        $title = trim((string) $request->request->get('name', ''));
        $code = $this->uppercase(trim((string) $request->request->get('code', '')));

        if ($worksheetTypeId <= 0 || $title === '' || $code === '') {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap típusa, a név és a kód megadása kötelező.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $worksheetType = $worksheetTypeRepository->find($worksheetTypeId);
        if (!$worksheetType) {
            return $this->response(false, [
                'data' => ['error' => 'A kiválasztott munkalaptípus nem található.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $worksheetStatusType = $id > 0 ? $worksheetStatusTypeRepository->find($id) : null;
        if ($id > 0 && !$worksheetStatusType) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap státusz nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        if (
            $worksheetStatusType
            && $worksheetStatusType->getWorksheetType()?->getId() !== $worksheetType->getId()
            && $worksheetRepository->count(['worksheetStatusType' => $worksheetStatusType]) > 0
        ) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalaptípus nem módosítható, mert ezt a státuszt már használja munkalap.'],
            ], 'json', Response::HTTP_CONFLICT);
        }

        $codeOwner = $worksheetStatusTypeRepository->findOneBy([
            'worksheetType' => $worksheetType,
            'code' => $code,
        ]);
        if ($codeOwner && $codeOwner->getId() !== $worksheetStatusType?->getId()) {
            return $this->response(false, [
                'data' => ['error' => 'Ehhez a munkalaptípushoz már létezik ilyen kódú státusz.'],
            ], 'json', Response::HTTP_CONFLICT);
        }

        $isNew = $worksheetStatusType === null;
        $now = new \DateTimeImmutable();
        $userId = $this->getUser()?->getId();

        if ($isNew) {
            $worksheetStatusType = (new WorksheetStatusType())
                ->setDatetimeAdd($now)
                ->setUidAdd($userId);
        }

        $worksheetStatusType
            ->setWorksheetType($worksheetType)
            ->setTitle($title)
            ->setCode($code)
            ->setDatetimeLast($now)
            ->setUidLast($userId);

        try {
            $worksheetStatusTypeRepository->save($worksheetStatusType);
        } catch (UniqueConstraintViolationException) {
            return $this->response(false, [
                'data' => ['error' => 'Ehhez a munkalaptípushoz már létezik ilyen kódú státusz.'],
            ], 'json', Response::HTTP_CONFLICT);
        }

        return $this->response(true, [
            'data' => [
                'id' => $worksheetStatusType->getId(),
                'code' => $worksheetStatusType->getCode(),
                'mode' => $isNew ? 'insert' : 'update',
            ],
        ]);
    }

    #[Route('/worksheet-status-types/delete', name: 'delete_worksheet_status_type', methods: ['DELETE'])]
    public function deleteWorksheetStatusType(
        Request $request,
        WorksheetStatusTypeRepository $worksheetStatusTypeRepository,
    ): Response {
        $worksheetStatusType = $worksheetStatusTypeRepository->find($request->request->getInt('id'));
        if (!$worksheetStatusType) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap státusz nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        try {
            $worksheetStatusTypeRepository->delete($worksheetStatusType);
        } catch (ForeignKeyConstraintViolationException) {
            return $this->response(false, [
                'data' => ['error' => 'A státusz nem törölhető, mert munkalap tartozik hozzá.'],
            ], 'json', Response::HTTP_CONFLICT);
        }

        return $this->response(true, [
            'data' => ['deleted' => true],
        ]);
    }

    #[Route('/worksheet-status-types/list', name: 'list_worksheet_status_types', methods: ['GET', 'POST'])]
    public function listWorksheetStatusTypes(
        Request $request,
        WorksheetStatusTypeRepository $worksheetStatusTypeRepository,
    ): Response {
        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen szűrési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $pageRaw = $request->request->get('page', $request->query->get('page', 1));
        $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;
        $list = $worksheetStatusTypeRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        return $this->response(true, [
            'content' => $this->renderView('worksheet_status_types/list_worksheet_status_types.html.twig', [
                'records' => $list['records'],
                'filters' => $filters,
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
                'queryParams' => $request->query->all(),
            ]),
            'data' => [
                'filters' => $filters,
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
            ],
        ]);
    }

    private function uppercase(string $value): string
    {
        return function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
    }
}
