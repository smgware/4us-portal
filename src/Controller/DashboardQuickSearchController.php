<?php

namespace App\Controller;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DashboardQuickSearchController extends BaseController
{
    #[Route('/dashboard/machine-quicksearch', name: 'index_dashboard_machine_quick_search', methods: ['POST'])]
    public function indexDashboardMachineQuickSearch(
        Request $request
    ): Response
    {

        return $this->response(true, [
            'template' => 'dashboard/quick_search/index_dashboard_machine_quick_search.html.twig',
            'data' => [

            ],
        ]);
    }
}
