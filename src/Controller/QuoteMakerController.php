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

    #[Route('/quote-maker/add', name: 'index_quote_maker_add', methods: ['GET'])]
    public function indexAdd(
        Request $request,
        PartnerRepository $partnerRepository): Response
    {
        return $this->response('true',[
                'template' => 'quote_maker/index.html.twig'
        ]
        );
    }

}
