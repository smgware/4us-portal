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
    private const FINAL_WORKSHEET_STATUS_CODES = ['CLOSED', 'REPAIRED_RETURNED', 'TAKEN_BACK'];

    #[Route('/dashboard/machine-quicksearch', name: 'index_dashboard_machine_quick_search', methods: ['POST'])]
    public function indexDashboardMachineQuickSearch(
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
                'data' => ['error' => 'Válassz egy érvényes gépet.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $rental = $machineRentalRepository->findOpenByMachine($machine);
        $latestHandoverWorksheet = $rental
            ? $worksheetRepository->findLatestByRentalAndTypeCode((int) $rental->getId(), 'MACHINE_HANDOVER')
            : null;
        $isHandoverClosed = $latestHandoverWorksheet && in_array(
            (string) $latestHandoverWorksheet->getWorksheetStatusType()?->getCode(),
            self::FINAL_WORKSHEET_STATUS_CODES,
            true,
        );
        $handoverWorksheet = $latestHandoverWorksheet && !$isHandoverClosed
            ? $latestHandoverWorksheet
            : null;
        $returnWorksheet = $rental && $isHandoverClosed
            ? $worksheetRepository->findLatestOpenByRentalAndType((int) $rental->getId(), 'MACHINE_RETURN')
            : null;

        return $this->response(true, [
            'template' => 'dashboard/quick_search/index_dashboard_machine_quick_search.html.twig',
            'templateData' => [
                'machine' => $machine,
                'rental' => $rental,
                'handoverWorksheet' => $handoverWorksheet,
                'returnWorksheet' => $returnWorksheet,
            ],
            'data' => [
                'machine_id' => $machine->getId(),
                'rental_id' => $rental?->getId(),
            ],
        ]);
    }
}
