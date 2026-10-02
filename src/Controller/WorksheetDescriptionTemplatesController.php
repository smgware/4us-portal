<?php

namespace App\Controller;

use App\Entity\WorksheetDescriptionTemplate;
use App\Repository\WorksheetDescriptionTemplateRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WorksheetDescriptionTemplatesController extends BaseController
{
    private const ITEMS_PER_PAGE = 100;

    #[Route('/worksheet-description-templates', name: 'index_worksheet_description_templates')]
    public function index(): Response
    {
        return $this->render('worksheet_description_templates/index.html.twig');
    }

    #[Route('/worksheet-description-templates/add', name: 'index_worksheet_description_templates_add')]
    public function indexAdd(
        Request $request,
        WorksheetDescriptionTemplateRepository $templateRepository,
    ): Response {
        $idRaw = $request->query->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $item = $id > 0 ? $templateRepository->find($id) : null;

        if ($id > 0 && !$item) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap megjegyzéssablon nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        return $this->response(true, [
            'template' => 'worksheet_description_templates/index_add.html.twig',
            'templateData' => ['item' => $item],
        ]);
    }

    #[Route('/worksheet-description-templates/save', name: 'save_worksheet_description_templates', methods: ['POST'])]
    public function save(
        Request $request,
        WorksheetDescriptionTemplateRepository $templateRepository,
    ): Response {
        $postData = [];
        foreach ($request->request->all() as $key => $value) {
            $postData[$key] = is_string($value) ? trim($value) : $value;
        }

        $id = (int) ($postData['id'] ?? 0);
        $template = $id > 0 ? $templateRepository->find($id) : null;
        if ($id > 0 && !$template) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap megjegyzéssablon nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $title = mb_substr((string) ($postData['title'] ?? ''), 0, 255);
        $description = (string) ($postData['description'] ?? '');
        $errors = [];
        if ($title === '') {
            $errors[] = 'A cím megadása kötelező.';
        }
        if ($description === '') {
            $errors[] = 'A leírás megadása kötelező.';
        }

        if ($errors !== []) {
            return $this->response(false, [
                'data' => ['error' => implode('<br>', $errors), 'errors' => $errors],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $isNew = $template === null;
        if ($isNew) {
            $template = (new WorksheetDescriptionTemplate())
                ->setDatetimeAdd(new \DateTimeImmutable())
                ->setUidAdd($this->getUser()?->getId());
        }

        $template
            ->setTitle($title)
            ->setDescription($description)
            ->setStatus(($postData['status'] ?? '1') === '0' ? '0' : '1')
            ->setDatetimeLast(new \DateTimeImmutable())
            ->setUidLast($this->getUser()?->getId());

        $templateRepository->save($template);

        return $this->response(true, [
            'data' => [
                'id' => $template->getId(),
                'mode' => $isNew ? 'insert' : 'update',
            ],
        ]);
    }

    #[Route('/worksheet-description-templates/delete', name: 'delete_worksheet_description_template', methods: ['DELETE'])]
    public function delete(
        Request $request,
        WorksheetDescriptionTemplateRepository $templateRepository,
    ): Response {
        $id = $request->request->getInt('id');
        $template = $id > 0 ? $templateRepository->find($id) : null;

        if (!$template) {
            return $this->response(false, [
                'data' => ['error' => 'A munkalap megjegyzéssablon nem található.'],
            ], 'json', $id > 0 ? Response::HTTP_NOT_FOUND : Response::HTTP_BAD_REQUEST);
        }

        $templateRepository->delete($template);

        return $this->response(true, [
            'data' => ['id' => $id, 'deleted' => true],
        ]);
    }

    #[Route('/worksheet-description-templates/list', name: 'list_worksheet_description_templates', methods: ['GET', 'POST'])]
    public function listTemplates(
        Request $request,
        WorksheetDescriptionTemplateRepository $templateRepository,
    ): Response {
        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen szűrési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $pageRaw = $request->request->get('page', $request->query->get('page', 1));
        $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;
        $list = $templateRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        return $this->response(true, [
            'template' => 'worksheet_description_templates/list_worksheet_description_templates.html.twig',
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
