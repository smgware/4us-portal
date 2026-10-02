<?php

namespace App\Controller;

use App\Entity\CompanySite;
use App\Repository\CompanySiteRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CompanySitesController extends BaseController
{
    private const ITEMS_PER_PAGE = 100;

    #[Route('/company-sites', name: 'index_company_sites')]
    public function index(): Response
    {
        return $this->render('company_sites/index.html.twig');
    }

    #[Route('/company-sites/add', name: 'index_company_sites_add')]
    public function indexAdd(Request $request, CompanySiteRepository $companySiteRepository): Response
    {
        $idRaw = $request->query->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $item = $id > 0 ? $companySiteRepository->find($id) : null;

        if ($id > 0 && !$item) {
            return $this->response(false, [
                'data' => ['error' => 'A telephely nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        return $this->response(true, [
            'template' => 'company_sites/index_add.html.twig',
            'templateData' => ['item' => $item],
        ]);
    }

    #[Route('/company-sites/save', name: 'save_company_sites', methods: ['POST'])]
    public function save(Request $request, CompanySiteRepository $companySiteRepository): Response
    {
        $postData = [];
        foreach ($request->request->all() as $key => $value) {
            $postData[$key] = is_string($value) ? trim($value) : $value;
        }

        $id = (int) ($postData['id'] ?? 0);
        $companySite = $id > 0 ? $companySiteRepository->find($id) : null;
        if ($id > 0 && !$companySite) {
            return $this->response(false, [
                'data' => ['error' => 'A telephely nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $title = mb_substr((string) ($postData['title'] ?? $postData['name'] ?? ''), 0, 255);
        if ($title === '') {
            return $this->response(false, [
                'data' => ['error' => 'A telephely nevének megadása kötelező.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $isNew = $companySite === null;
        if ($isNew) {
            $companySite = new CompanySite();
            $companySite->setDatetimeAdd(new \DateTimeImmutable());
            $companySite->setUidAdd($this->getUser()?->getId());
        }

        $companySite
            ->setTitle($title)
            ->setCode(mb_substr((string) ($postData['code'] ?? ''), 0, 255))
            ->setStatus(($postData['status'] ?? '1') === '0' ? '0' : '1')
            ->setDatetimeLast(new \DateTimeImmutable())
            ->setUidLast($this->getUser()?->getId());

        $companySiteRepository->save($companySite);

        return $this->response(true, [
            'data' => [
                'id' => $companySite->getId(),
                'mode' => $isNew ? 'insert' : 'update',
            ],
        ]);
    }

    #[Route('/company-sites/delete', name: 'delete_company_site', methods: ['DELETE'])]
    public function delete(Request $request, CompanySiteRepository $companySiteRepository): Response
    {
        $id = $request->request->getInt('id');
        $companySite = $id > 0 ? $companySiteRepository->find($id) : null;

        if (!$companySite) {
            return $this->response(false, [
                'data' => ['error' => 'A telephely nem található.'],
            ], 'json', $id > 0 ? Response::HTTP_NOT_FOUND : Response::HTTP_BAD_REQUEST);
        }

        try {
            $companySiteRepository->delete($companySite);
        } catch (ForeignKeyConstraintViolationException) {
            return $this->response(false, [
                'data' => ['error' => 'A telephely nem törölhető, mert gép van hozzárendelve.'],
            ], 'json', Response::HTTP_CONFLICT);
        }

        return $this->response(true, [
            'data' => ['id' => $id, 'deleted' => true],
        ]);
    }

    #[Route('/company-sites/list', name: 'list_company_sites', methods: ['GET', 'POST'])]
    public function listCompanySites(Request $request, CompanySiteRepository $companySiteRepository): Response
    {
        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        if (!is_array($filters)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen szűrési adatok.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $pageRaw = $request->request->get('page', $request->query->get('page', 1));
        $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;
        $list = $companySiteRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        return $this->response(true, [
            'template' => 'company_sites/list_company_sites.html.twig',
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
