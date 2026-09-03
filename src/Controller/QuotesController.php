<?php

namespace App\Controller;

use App\Repository\CompareMachinesQuoteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class QuotesController extends BaseController
{
    private const ITEMS_PER_PAGE = 10;

    #[Route('/quotes', name: 'index_quotes', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('quotes/index.html.twig');
    }

    #[Route('/incoming-quotes', name: 'index_incoming_quotes', methods: ['GET'])]
    public function index_incoming_quotes(): Response
    {
        return $this->response(true, [
            'template' => 'quotes/incoming_quotes/main.html.twig',
        ]);
    }

    #[Route('/quotes', name: 'index_quote_maker', methods: ['GET'])]
    public function index_quote_maker(): Response
    {
        return $this->render('quotes/quote_maker/main.html.twig');
    }

    #[Route('/quotes/incoming/list', name: 'list_incoming_quotes', methods: ['GET', 'POST'])]
    public function listIncomingQuotes(Request $request, CompareMachinesQuoteRepository $quoteRepository): Response
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
        $list = $quoteRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        return $this->response(true, [
            'template' => 'quotes/incoming_quotes/list.html.twig',
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

    #[Route('/quotes/incoming/detail', name: 'detail_incoming_quote', methods: ['GET', 'POST'])]
    public function detailIncomingQuote(Request $request, CompareMachinesQuoteRepository $quoteRepository): Response
    {
        $idRaw = $request->request->get('id', $request->query->get('id'));
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;

        if ($id <= 0) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Missing id.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $quote = $quoteRepository->find($id);
        if (!$quote) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Quote not found.',
                ],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        return $this->response(true, [
            'template' => 'quotes/incoming_quotes/detail.html.twig',
            'templateData' => [
                'quote' => $quote,
                'quoteData' => $quote->getData() ?? [],
            ],
            'data' => [
                'id' => $quote->getId(),
            ],
        ]);
    }
}
