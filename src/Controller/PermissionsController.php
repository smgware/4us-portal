<?php

namespace App\Controller;

use App\Entity\Permission;
use App\Repository\PermissionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PermissionsController extends BaseController
{
    private const ITEMS_PER_PAGE = 4;

    #[Route('/permissions', name: 'index_permissions')]
    public function index(): Response
    {
        return $this->render('permissions/index.html.twig');
    }

    #[Route('/permissions/add', name: 'index_permissions_add')]
    public function index_add(Request $request, PermissionRepository $permissionRepository): Response
    {
        $idRaw = $request->query->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $item = $id > 0 ? $permissionRepository->find($id) : null;

        return $this->response(true, [
            'template' => 'permissions/index_add.html.twig',
            'templateData' => [
                'item' => $item,
                'parents' => $permissionRepository->findParentOptions($item?->getId()),
            ],
            'data' => [],
        ]);
    }

    #[Route('/permissions/save', name: 'save_permissions', methods: ['POST'])]
    public function save_permissions(Request $request, PermissionRepository $permissionRepository): Response
    {
        $postData = [];
        foreach ($request->request->all() as $key => $value) {
            $postData[$key] = is_string($value) ? trim($value) : $value;
        }

        $id = (int) ($postData['id'] ?? 0);
        $permission = $id > 0 ? $permissionRepository->find($id) : null;
        $isNew = $permission === null;

        if ($isNew) {
            $permission = new Permission();
            $permission->setDatetimeAdd(new \DateTimeImmutable());
            $permission->setUidAdd($this->getUser()?->getId());
        }

        $title = (string) ($postData['title'] ?? $postData['name'] ?? '');
        $code = (string) ($postData['code'] ?? '');
        if ($code === '') {
            $code = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
        }

        $parentId = (int) ($postData['parent_id'] ?? 0);

        $permission
            ->setTitle($title)
            ->setCode($code)
            ->setParentId($parentId > 0 ? $parentId : null)
            ->setStatus((string) ($postData['status'] ?? ''));

        $permission->setDatetimeLast(new \DateTimeImmutable());
        $permission->setUidLast($this->getUser()?->getId());

        $permissionRepository->save($permission);

        return $this->response(true, [
            'data' => [
                'id' => $permission->getId(),
                'mode' => $isNew ? 'insert' : 'update',
                'post' => $postData,
            ],
        ]);
    }

    #[Route('/permissions/delete', name: 'delete_permissions', methods: ['DELETE'])]
    public function delete_permissions(Request $request, PermissionRepository $permissionRepository): Response
    {
        $idRaw = $request->request->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;

        if ($id <= 0) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Missing id.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $permission = $permissionRepository->find($id);
        if (!$permission) {
            return $this->response(false, [
                'data' => [
                    'id' => $id,
                    'error' => 'Permission not found.',
                ],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $permissionRepository->delete($permission);

        return $this->response(true, [
            'data' => [
                'id' => $id,
                'deleted' => true,
            ],
        ]);
    }

    #[Route('/permissions/list', name: 'list_permissions', methods: ['GET', 'POST'])]
    public function list_permissions(Request $request, PermissionRepository $permissionRepository): Response
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
        $list = $permissionRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        $content = $this->renderView('permissions/list_permissions.html.twig', [
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
