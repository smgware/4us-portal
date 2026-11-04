<?php

namespace App\Controller;

use App\Entity\Partner;
use App\Entity\Project;
use App\Repository\PartnerRepository;
use App\Repository\ProjectRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class QuoteMakerController extends BaseController
{
    private const ITEMS_PER_PAGE = 10;

    #[Route('/quote-maker', name: 'index_quote_maker')]
    public function index(): Response
    {
        return $this->render('quote_maker/index.html.twig');
    }

    #[Route('/quote-maker/quotes', name: 'index_quote_maker_quotes')]
    public function indexQuoteMakerQuotes(): Response
    {
        return $this->response('true',[
            'template' => 'quote_maker/quotes/index_quotes.html.twig',
            'templateData' => []
        ]);
    }

    #[Route('/quote-maker/quote/add', name: 'index_quote_maker_quote_add', methods: ['POST'])]
    public function indexQuoteMakerQuoteAdd(
        Request $request,
        PartnerRepository $partnerRepository,
        ProjectRepository $projectRepository
    ): Response
    {
        return $this->response('true',[
                'template' => 'quote_maker/quotes/index_quote_add.html.twig',
                'templateData' => []

        ]);
    }


    #[Route('/quote-maker/quotes/list-index', name: 'index_quote_maker_quotes_list_index', methods: ['POST'])]
    public function indexQuoteMakerQuotesListIndex(
        Request $request,
    ): Response
    {
        return $this->response('true',[
            'template' => 'quote_maker/quotes/index_quotes_list_index.html.twig',
            'templateData' => []
        ]);
    }

    #[Route('/quote-maker/quotes/list', name: 'index_quote_maker_quotes_list', methods: ['POST'])]
    public function indexQuoteMakerQuotesList(
        Request $request,
    ): Response
    {
        return $this->response('true',[
            'template' => 'quote_maker/quotes/index_quotes_list.html.twig',
            'templateData' => []
        ]);
    }

    #[Route('/quote-maker/machines', name: 'index_quote_maker_machines')]
    public function indexQuoteMakerMachines(): Response
    {
        return $this->response('true',[
            'template' => 'quote_maker/machines/index_quote_maker_machines.html.twig',
            'templateData' => []
        ]);
    }



    #[Route('/quote-maker/machines/list-index', name: 'index_quote_maker_machines_list_index', methods: ['POST'])]
    public function indexQuoteMakerMachinesListIndex(
        Request $request,
    ): Response
    {
        return $this->response('true',[
            'template' => 'quote_maker/machines/index_quote_maker_machines_list_index.html.twig',
            'templateData' => []
        ]);
    }

    #[Route('/quote-maker/machines/list', name: 'index_quote_maker_machines_list', methods: ['POST'])]
    public function indexQuoteMakerMachinesList(
        Request $request,
    ): Response
    {
        return $this->response('true',[
            'template' => 'quote_maker/machines/index_quote_maker_machines_list.html.twig',
            'templateData' => []
        ]);
    }

    #[Route('/quote-maker/machines/add', name: 'index_quote_maker_machine_add', methods: ['POST'])]
    public function indexQuoteMakerMachineAdd(
        Request $request
    ): Response
    {
        return $this->response('true',[
            'template' => 'quote_maker/machines/index_quote_maker_machines_add.html.twig',
            'templateData' => []

        ]);
    }




}
