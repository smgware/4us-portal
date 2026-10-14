<?php

namespace App\Controller;

use App\Entity\UserNotificationEvent;
use App\Repository\MachineRentalRepository;
use App\Repository\MachineRepository;
use App\Repository\WorksheetRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DashboardQuickSearchController extends BaseController
{
    private const FINAL_WORKSHEET_STATUS_CODES = ['CLOSED', 'REPAIRED_RETURNED', 'TAKEN_BACK'];

    #[Route('/dashboard/quick-search/machine-info', name: 'index_dashboard_quick_search_machine_info', methods: ['POST'])]
    public function indexDashboardQuickSearchMachineInfo(
        Request $request,
        MachineRepository $machineRepository,
        MachineRentalRepository $machineRentalRepository,
        WorksheetRepository $worksheetRepository,
    ): Response
    {
        $machineId = $request->request->getInt('machine_id');
        $machine = $machineId > 0 ? $machineRepository->find($machineId) : null;

        return $this->response(true, [
            'template' => 'dashboard/quick_search/index_dashboard_machine_quick_search_machine_info.html.twig',
            'templateData' => [ ],
        ]);
    }


    #[Route('/dashboard/quick-search/actions', name: 'index_dashboard_quick_search_actions', methods: ['POST'])]
    public function indexDashboardQuickSearchActions(
        Request $request,
        MachineRepository $machineRepository,
        MachineRentalRepository $machineRentalRepository,
        WorksheetRepository $worksheetRepository,
    ): Response
    {
        $machineId = $request->request->getInt('machine_id');
        $machine = $machineId > 0 ? $machineRepository->find($machineId) : null;

        return $this->response(true, [
            'template' => 'dashboard/quick_search/index_dashboard_machine_quick_search_actions.html.twig',
            'templateData' => [ ],
        ]);
    }

}
