<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProjectsController extends BaseController
{
    private const ITEMS_PER_PAGE = 10;

    #[Route('/projects', name: 'index_projects')]
    public function index(): Response
    {
        return $this->render('projects/index.html.twig');
    }

    #[Route('/projects/add', name: 'index_projects_add', methods: ['GET'])]
    public function indexAdd(Request $request, ProjectRepository $projectRepository): Response
    {
        $idRaw = $request->query->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $item = $id > 0 ? $projectRepository->find($id) : null;

        if ($id > 0 && !$item) {
            return $this->response(false, [
                'data' => ['error' => 'A projekt nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        return $this->response(true, [
            'template' => 'projects/index_add.html.twig',
            'templateData' => [
                'item' => $item,
            ],
            'data' => [
                'mode' => $item ? 'update' : 'insert',
            ],
        ]);
    }

    #[Route('/projects/save', name: 'save_projects', methods: ['POST'])]
    public function saveProjects(Request $request, ProjectRepository $projectRepository): Response
    {
        $idRaw = $request->request->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $name = trim((string) $request->request->get('name', ''));

        if ($name === '') {
            return $this->response(false, [
                'data' => ['error' => 'A név megadása kötelező.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $project = $id > 0 ? $projectRepository->find($id) : null;
        if ($id > 0 && !$project) {
            return $this->response(false, [
                'data' => ['error' => 'A projekt nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $isNew = $project === null;
        $now = new \DateTimeImmutable();
        $userId = $this->getUser()?->getId();

        if ($isNew) {
            $project = (new Project())
                ->setCode($this->generateProjectCode($projectRepository))
                ->setDatetimeAdd($now)
                ->setStatus(1)
                ->setUidAdd($userId);
        }

        $project
            ->setName($name)
            ->setDescription($this->nullableString($request->request->get('description')))
            ->setStatus($this->nullableString($request->request->get('status')))
            ->setDatetimeLast($now)
            ->setUidLast($userId);

        try {
            $projectRepository->save($project);
        } catch (UniqueConstraintViolationException) {
            return $this->response(false, [
                'data' => ['error' => 'Nem sikerült egyedi projektkódot generálni. Próbáld újra.'],
            ], 'json', Response::HTTP_CONFLICT);
        }

        return $this->response(true, [
            'data' => [
                'id' => $project->getId(),
                'code' => $project->getCode(),
                'mode' => $isNew ? 'insert' : 'update',
            ],
        ]);
    }

    #[Route('/projects/list', name: 'list_projects', methods: ['GET', 'POST'])]
    public function listProjects(Request $request, ProjectRepository $projectRepository): Response
    {
        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen szűrési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $pageRaw = $request->request->get('page', $request->query->get('page', 1));
        $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;
        $list = $projectRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        return $this->response(true, [
            'content' => $this->renderView('projects/list_projects.html.twig', [
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

    private function generateProjectCode(ProjectRepository $projectRepository): string
    {
        $year = (new \DateTimeImmutable())->format('y');

        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $code = sprintf('P%s-%05d', $year, random_int(10000, 99999));
            if ($projectRepository->findOneBy(['code' => $code]) === null) {
                return $code;
            }
        }

        throw new \RuntimeException('Nem sikerült egyedi projektkódot generálni.');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
