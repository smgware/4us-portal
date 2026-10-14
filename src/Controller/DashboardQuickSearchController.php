<?php

namespace App\Controller;

use App\Repository\MachineRentalRepository;
use App\Repository\MachineRepository;
use App\Repository\WorksheetRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DashboardQuickSearchController extends BaseController
{
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

        if (!$machine) {
            return $this->response(false, [
                'data' => ['error' => 'A kiválasztott gép nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $activeMachineRental = $machineRentalRepository->findOpenByMachine($machine);
        if ($activeMachineRental && !$worksheetRepository->findLatestOpenByRentalAndType($activeMachineRental->getId(), 'MACHINE_RETURN')) {
            $activeMachineRental = null;
        }

        return $this->response(true, [
            'template' => 'dashboard/quick_search/index_dashboard_machine_quick_search_machine_info.html.twig',
            'templateData' => [
                'machine' => $machine,
                'activeMachineRental' => $activeMachineRental,
            ],
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

        if (!$machine) {
            return $this->response(false, [
                'data' => ['error' => 'A kiválasztott gép nem található.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $activeMachineRental = $machineRentalRepository->findOpenByMachine($machine);
        $handoverWorksheet = $activeMachineRental
            ? $worksheetRepository->findLatestOpenByRentalAndType($activeMachineRental->getId(), 'MACHINE_HANDOVER')
            : null;
        $returnWorksheet = $activeMachineRental
            ? $worksheetRepository->findLatestOpenByRentalAndType($activeMachineRental->getId(), 'MACHINE_RETURN')
            : null;

        return $this->response(true, [
            'template' => 'dashboard/quick_search/index_dashboard_machine_quick_search_actions.html.twig',
            'templateData' => [
                'machine' => $machine,
                // A kölcsönzés mentésekor a visszavételi munkalap is létrejön.
                // Csak annak nyitott állapotában jelennek meg a kölcsönzési műveletek.
                'hasActiveMachineRental' => $activeMachineRental !== null && $returnWorksheet !== null,
                'handoverWorksheetId' => $handoverWorksheet?->getId(),
                'returnWorksheetId' => $returnWorksheet?->getId(),
            ],
        ]);
    }

}
