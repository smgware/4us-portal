<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class QuoteMakerController extends BaseController
{
    #[Route('/quote-maker', name: 'index_quote_maker', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('quote_maker/index.html.twig');
    }
}
