<?php

namespace App\Controller;

use App\Entity\Partner;
use App\Entity\Project;
use App\Entity\QuoteMakerMachine;
use App\Entity\QuoteMakerQuote;
use App\Repository\PartnerRepository;
use App\Repository\ProjectRepository;
use App\Repository\QuoteMakerQuoteRepository;
use App\Repository\QuoteMakerRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class QuoteMakerController extends BaseController
{
    private const ITEMS_PER_PAGE = 10;
    private const EUR_HUF = 385;

    public array $ankerCosts = [
        '80' => ['cost_daily' => 350000, 'cost_budapest' => 600000, 'cost_videk' => 6000],
        '100' => ['cost_daily' => 400000, 'cost_budapest' => 800000, 'cost_videk' => 8000],
        '120' => ['cost_daily' => 450000, 'cost_budapest' => 800000, 'cost_videk' => 8000],
        '150' => ['cost_daily' => 600000, 'cost_budapest' => 800000, 'cost_videk' => 8000],
        '200' => ['cost_daily' => 650000, 'cost_budapest' => 1000000, 'cost_videk' => 10000],
        '250' => ['cost_daily' => 800000, 'cost_budapest' => 1000000, 'cost_videk' => 10000],
    ];

    public array $other_costs = [
        'gepkezelo_oradija_07_17' => ['title' => 'Gépkezelő óradíja (7:00-17:00)', 'value' => 7350],
        'gepkezelo_oradija_17_07' => ['title' => 'Gépkezelő óradíja (17:00-7:00)', 'value' => 9900],
        'gepkezelo_oradija_vasarnap_unnepnap' => ['title' => 'Gépkezelő óradíja (vasárnap és ünnepnap)', 'value' => 14700],
        'veszelymentes_tervirat' => ['title' => 'Veszélymentes tervirat (egyszeri díj)', 'value' => 150000],
        'toronydaru_uzembe_helyezes_ev_meres' => ['title' => 'Toronydaru üzembe helyezés + ÉV mérés', 'value' => 300000],
        'kamion_szallitasi_dij' => ['title' => 'Kamion szállítási díja', 'value' => 150000],
        'szerelocsapat_dija' => ['title' => 'Szerelőcsapat díja', 'value' => 1000000],
    ];

    public array $fieldOptions = [
        ['field_title' => 'Megnevezés', 'field_code' => 'megnevezes', 'field_calculated' => false, 'field_unit' => '', 'field_type' => 'text'],
        ['field_title' => 'Típus', 'field_code' => 'tipus', 'field_calculated' => false, 'field_unit' => '', 'field_type' => 'text'],
        ['field_title' => 'Évjárat', 'field_code' => 'evjarat', 'field_calculated' => false, 'field_unit' => 'év', 'field_type' => 'text'],
        ['field_title' => 'Horogmagasság', 'field_code' => 'horogmagassag', 'field_calculated' => false, 'field_unit' => 'm', 'field_type' => 'text'],
        ['field_title' => 'Gémkinyúlás', 'field_code' => 'gemkinyulas', 'field_calculated' => false, 'field_unit' => 'm', 'field_type' => 'text'],
        ['field_title' => 'Max. teherbírás', 'field_code' => 'max_teherbiras', 'field_calculated' => false, 'field_unit' => '', 'field_type' => 'text'],
        ['field_title' => 'Telepítési szint', 'field_code' => 'telepitesi_szint', 'field_calculated' => false, 'field_unit' => '', 'field_type' => 'text'],
        ['field_title' => 'Telepítési mód', 'field_code' => 'telepitesi_mod', 'field_calculated' => false, 'field_unit' => '', 'field_type' => 'text'],
        ['field_title' => 'Elektromos csatlakozás', 'field_code' => 'elektromos_csatlakozas', 'field_calculated' => false, 'field_unit' => '', 'field_type' => 'text'],
        ['field_title' => 'Havi díj 200 üó-ig', 'field_code' => 'havi_dij_200_uo_ig', 'field_calculated' => false, 'field_unit' => 'Ft', 'field_type' => 'text'],
        ['field_title' => '200 üó felett / óra', 'field_code' => '200_uo_felett_ora', 'field_calculated' => false, 'field_unit' => 'Ft', 'field_type' => 'text'],
        ['field_title' => 'Kezelő 7-17', 'field_code' => 'kezelo_7_17', 'field_calculated' => false, 'field_unit' => 'Ft', 'field_type' => 'text'],
        ['field_title' => 'Kezelő 17-7', 'field_code' => 'kezelo_17_7', 'field_calculated' => false, 'field_unit' => 'Ft', 'field_type' => 'text'],
        ['field_title' => 'Kezelő vasárnap/ünnep', 'field_code' => 'kezelo_vasarnap_unnep', 'field_calculated' => false, 'field_unit' => 'Ft', 'field_type' => 'text'],
        ['field_title' => 'Kapcsolószekrény / hó', 'field_code' => 'kapcsoloszekreny_ho', 'field_calculated' => false, 'field_unit' => 'Ft', 'field_type' => 'text'],
        ['field_title' => 'Telepítés (egyszeri díj)', 'field_code' => 'telepites_egyszeri_dij', 'field_calculated' => false, 'field_unit' => 'Ft', 'field_type' => 'text'],
        ['field_title' => 'Bontás (egyszeri díj)', 'field_code' => 'bontas_egyszeri_dij', 'field_calculated' => false, 'field_unit' => 'Ft', 'field_type' => 'text'],
        ['field_title' => 'Veszélymentes tervirat (egyszeri díj)', 'field_code' => 'veszelymentes_tervirat_egyszeri_dij', 'field_calculated' => false, 'field_unit' => 'Ft', 'field_type' => 'text'],
        ['field_title' => 'Üzembe helyezés + ÉV', 'field_code' => 'uzembe_helyezes_ev', 'field_calculated' => false, 'field_unit' => 'Ft', 'field_type' => 'text'],
        ['field_title' => 'Ház magassága', 'field_code' => 'haz_magassaga', 'field_calculated' => false, 'field_unit' => 'm', 'field_type' => 'text'],
        ['field_title' => 'Telepítési szint (mag.)', 'field_code' => 'telepitesi_szint_mag', 'field_calculated' => false, 'field_unit' => 'm', 'field_type' => 'text'],
        ['field_title' => 'Ház absz. mag.', 'field_code' => 'haz_absz_mag', 'field_calculated' => true, 'field_unit' => 'm', 'field_type' => 'text'],
        ['field_title' => 'Védőtáv', 'field_code' => 'vedotav', 'field_calculated' => false, 'field_unit' => 'm', 'field_type' => 'text'],
        ['field_title' => 'Tényleges mag.', 'field_code' => 'tenyleges_mag', 'field_calculated' => true, 'field_unit' => 'm', 'field_type' => 'text'],
        ['field_title' => 'Absz. mag. daru', 'field_code' => 'absz_mag_daru', 'field_calculated' => true, 'field_unit' => 'm', 'field_type' => 'text'],
        ['field_title' => 'Telepíthető autódaru max. 10m', 'field_code' => 'telepitheto_autodaru_max_10m', 'field_calculated' => false, 'field_unit' => 't', 'field_type' => 'text'],
        ['field_title' => 'Anker elhelyezéshez csavarok száma', 'field_code' => 'anker_elhelyezeshez_csavarok_szama', 'field_calculated' => false, 'field_unit' => 'db', 'field_type' => 'text'],
        ['field_title' => 'Anker elhelyezés díja (EUR)', 'field_code' => 'anker_elhelyezes_dija_eur', 'field_calculated' => false, 'field_unit' => 'EUR', 'field_type' => 'text'],
        ['field_title' => 'Anker elhelyezés díja (HUF)', 'field_code' => 'anker_elhelyezes_dija_huf', 'field_calculated' => true, 'field_unit' => 'Ft', 'field_type' => 'text'],
        ['field_title' => 'Beszerzési ár', 'field_code' => 'beszerzesi_ar', 'field_calculated' => false, 'field_unit' => 'EUR', 'field_type' => 'text'],
        ['field_title' => 'ÁR (EUR)', 'field_code' => 'ar_eur', 'field_calculated' => true, 'field_unit' => 'EUR', 'field_type' => 'text'],
        ['field_title' => 'ÁR (HUF)', 'field_code' => 'ar_huf', 'field_calculated' => true, 'field_unit' => 'Ft', 'field_type' => 'text'],
        ['field_title' => 'Legnagyobb súly', 'field_code' => 'legnagyobb_suly', 'field_calculated' => false, 'field_unit' => 'kg', 'field_type' => 'text'],
    ];

    #[Route('/quote-maker', name: 'index_quote_maker')]
    public function index(): Response
    {
        return $this->render('quote_maker/index.html.twig');
    }

    #[Route('/quote-maker/quotes', name: 'index_quote_maker_quotes')]
    public function indexQuoteMakerQuotes(): Response
    {
        return $this->response(true, ['template' => 'quote_maker/quotes/index_quotes.html.twig', 'templateData' => []]);
    }

    #[Route('/quote-maker/quotes/add', name: 'index_quote_maker_quotes_add', methods: ['POST'])]
    public function indexQuoteMakerQuoteAdd(Request $request, QuoteMakerQuoteRepository $quoteRepository): Response
    {
        $id = $this->positiveInt($request->request->get('id'));
        $item = $id ? $quoteRepository->find($id) : null;
        if ($id && !$item) {
            return $this->response(false, ['data' => ['error' => 'Az árajánlat nem található.']], 'json', Response::HTTP_NOT_FOUND);
        }

        return $this->response(true, [
            'template' => 'quote_maker/quotes/index_quotes_add.html.twig',
            'templateData' => ['item' => $item, 'itemData' => $item?->getData() ?? [], 'fieldOptions' => $this->fieldOptions],
        ]);
    }

    #[Route('/quote-maker/quotes/save-add', name: 'save_quote_maker_quotes_add', methods: ['POST'])]
    public function saveQuoteMakerQuotesAdd(
        Request $request,
        QuoteMakerQuoteRepository $quoteRepository,
        PartnerRepository $partnerRepository,
        ProjectRepository $projectRepository,
        QuoteMakerRepository $machineRepository,
    ): Response {
        $id = $this->positiveInt($request->request->get('id'));
        $quote = $id ? $quoteRepository->find($id) : null;
        if ($id && !$quote) {
            return $this->response(false, ['data' => ['error' => 'Az árajánlat nem található.']], 'json', Response::HTTP_NOT_FOUND);
        }

        $payload = $this->decodeJsonObject($request->request->get('data'));
        if ($payload === null) {
            return $this->response(false, ['data' => ['error' => 'Az árajánlat adatai nem érvényes JSON-adatok.']], 'json', Response::HTTP_BAD_REQUEST);
        }

        try {
            $data = $this->buildQuoteSnapshot($payload, $partnerRepository, $projectRepository, $machineRepository, $quote?->getData());
        } catch (\InvalidArgumentException $exception) {
            return $this->response(false, ['data' => ['error' => $exception->getMessage()]], 'json', Response::HTTP_BAD_REQUEST);
        }

        $isNew = !$quote;
        $now = new \DateTimeImmutable();
        $userId = $this->getUser()?->getId();
        if ($isNew) {
            $quote = (new QuoteMakerQuote())
                ->setCode($this->generateQuoteCode($quoteRepository))
                ->setDatetimeAdd($now)
                ->setUidAdd($userId);
        }
        $data['quote_code'] = $quote->getCode();

        $title = trim(implode(' - ', array_filter([(string) ($data['partner']['name'] ?? ''), (string) ($data['project']['name'] ?? '')])));
        $quote
            ->setTitle($title !== '' ? mb_substr($title, 0, 255) : null)
            ->setData($data)
            ->setDatetimeLast($now)
            ->setUidLast($userId);
        $quoteRepository->save($quote);

        return $this->response(true, ['data' => [
            'id' => $quote->getId(),
            'code' => $quote->getCode(),
            'mode' => $isNew ? 'insert' : 'update',
        ]]);
    }

    #[Route('/quote-maker/quotes/list-index', name: 'index_quote_maker_quotes_list_index', methods: ['POST'])]
    public function indexQuoteMakerQuotesListIndex(): Response
    {
        return $this->response(true, ['template' => 'quote_maker/quotes/index_quotes_list_index.html.twig', 'templateData' => []]);
    }

    #[Route('/quote-maker/quotes/list', name: 'index_quote_maker_quotes_list', methods: ['POST'])]
    public function indexQuoteMakerQuotesList(Request $request, QuoteMakerQuoteRepository $quoteRepository): Response
    {
        $filters = $this->decodeFilters($request);
        if ($filters === null) {
            return $this->response(false, ['data' => ['error' => 'Érvénytelen szűrési adatok.']], 'json', Response::HTTP_BAD_REQUEST);
        }

        $page = $this->positiveInt($request->request->get('page')) ?? 1;
        $list = $quoteRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        return $this->response(true, [
            'template' => 'quote_maker/quotes/index_quotes_list.html.twig',
            'templateData' => $list,
            'data' => ['filters' => $filters, 'page' => $list['page'], 'totalPages' => $list['totalPages'], 'totalRecords' => $list['totalRecords']],
        ]);
    }

    #[Route('/quote-maker/quotes/partners/select/list', name: 'list_quote_maker_partner_select', methods: ['POST'])]
    public function listPartnerOptions(Request $request, PartnerRepository $partnerRepository): Response
    {
        $filters = $this->decodeFilters($request);
        if ($filters === null) {
            return $this->response(false, ['data' => ['error' => 'Érvénytelen partnerkeresési adatok.']], 'json', Response::HTTP_BAD_REQUEST);
        }

        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);
        $items = array_map(static function (array $record): array {
            $active = (string) ($record['status'] ?? '') === '1';
            $meta = array_filter([
                trim((string) ($record['code'] ?? '')),
                !empty($record['tax_number']) ? 'Adószám: ' . $record['tax_number'] : null,
                trim(implode(' ', array_filter([(string) ($record['city'] ?? ''), (string) ($record['address'] ?? '')]))),
            ]);
            return [
                'id' => (int) $record['id'],
                'label' => (string) $record['name'],
                'meta' => implode(' · ', $meta),
                'selectable' => $active,
                'badge' => $active ? null : 'Inaktív',
                'disabledReason' => $active ? null : 'Az inaktív partner nem választható ki.',
                'partner' => $record,
            ];
        }, $partnerRepository->findForWorksheetSelect($search, 20));

        return $this->response(true, ['data' => ['items' => $items]]);
    }

    #[Route('/quote-maker/quotes/projects/select/list', name: 'list_quote_maker_project_select', methods: ['POST'])]
    public function listProjectOptions(Request $request, ProjectRepository $projectRepository): Response
    {
        $filters = $this->decodeFilters($request);
        if ($filters === null) {
            return $this->response(false, ['data' => ['error' => 'Érvénytelen projektkeresési adatok.']], 'json', Response::HTTP_BAD_REQUEST);
        }

        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);
        $items = array_map(static function (array $record): array {
            $active = (string) ($record['status'] ?? '') === '1';
            return [
                'id' => (int) $record['id'],
                'label' => (string) $record['name'],
                'meta' => trim((string) ($record['code'] ?? '')),
                'selectable' => $active,
                'badge' => $active ? null : 'Inaktív',
                'disabledReason' => $active ? null : 'Az inaktív projekt nem választható ki.',
                'project' => $record,
            ];
        }, $projectRepository->findForWorksheetSelect($search, 20));

        return $this->response(true, ['data' => ['items' => $items]]);
    }

    #[Route('/quote-maker/quotes/machines/select/list', name: 'list_quote_maker_machine_select', methods: ['POST'])]
    public function listMachineOptions(Request $request, QuoteMakerRepository $machineRepository): Response
    {
        $filters = $this->decodeFilters($request);
        if ($filters === null) {
            return $this->response(false, ['data' => ['error' => 'Érvénytelen gépkeresési adatok.']], 'json', Response::HTTP_BAD_REQUEST);
        }

        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);
        $items = array_map(static function (array $record): array {
            $data = is_array($record['data'] ?? null) ? $record['data'] : [];
            $title = trim((string) ($record['title'] ?? ''));
            if ($title === '') {
                $title = trim((string) ($data['megnevezes'] ?? $data['tipus'] ?? ''));
            }
            if ($title === '') {
                $title = 'Gép #' . (int) $record['id'];
            }
            return [
                'id' => (int) $record['id'],
                'label' => $title,
                'meta' => implode(' · ', array_filter([trim((string) ($record['code'] ?? '')), trim((string) ($data['tipus'] ?? '')), trim((string) ($data['evjarat'] ?? ''))])),
                'selectable' => true,
                'machine' => ['id' => (int) $record['id'], 'title' => $record['title'], 'code' => $record['code'], 'data' => $data],
            ];
        }, $machineRepository->findForQuoteSelect($search, 20));

        return $this->response(true, ['data' => ['items' => $items]]);
    }

    #[Route('/quote-maker/machines', name: 'index_quote_maker_machines')]
    public function indexQuoteMakerMachines(): Response
    {
        return $this->response(true, ['template' => 'quote_maker/machines/index_quote_maker_machines.html.twig', 'templateData' => []]);
    }

    #[Route('/quote-maker/machines/list-index', name: 'index_quote_maker_machines_list_index', methods: ['POST'])]
    public function indexQuoteMakerMachinesListIndex(): Response
    {
        return $this->response(true, ['template' => 'quote_maker/machines/index_quote_maker_machines_list_index.html.twig', 'templateData' => []]);
    }

    #[Route('/quote-maker/machines/list', name: 'index_quote_maker_machines_list', methods: ['POST'])]
    public function indexQuoteMakerMachinesList(Request $request, QuoteMakerRepository $machineRepository): Response
    {
        $filters = $this->decodeFilters($request);
        if ($filters === null) {
            return $this->response(false, ['data' => ['error' => 'Érvénytelen szűrési adatok.']], 'json', Response::HTTP_BAD_REQUEST);
        }

        $page = $this->positiveInt($request->request->get('page')) ?? 1;
        $list = $machineRepository->findAllQuoteMachines($filters, $page, self::ITEMS_PER_PAGE);
        $fieldOptionsByCode = [];
        foreach ($this->fieldOptions as $fieldOption) {
            $fieldOptionsByCode[$fieldOption['field_code']] = $fieldOption;
        }

        return $this->response(true, [
            'template' => 'quote_maker/machines/index_quote_maker_machines_list.html.twig',
            'templateData' => $list + ['fieldOptionsByCode' => $fieldOptionsByCode],
            'data' => ['filters' => $filters, 'page' => $list['page'], 'totalPages' => $list['totalPages'], 'totalRecords' => $list['totalRecords']],
        ]);
    }

    #[Route('/quote-maker/machines/add', name: 'index_quote_maker_machine_add', methods: ['POST'])]
    public function indexQuoteMakerMachineAdd(Request $request, QuoteMakerRepository $machineRepository): Response
    {
        $id = $this->positiveInt($request->request->get('id'));
        $item = $id ? $machineRepository->find($id) : null;
        if ($id && !$item) {
            return $this->response(false, ['data' => ['error' => 'A gép nem található.']], 'json', Response::HTTP_NOT_FOUND);
        }
        return $this->response(true, ['template' => 'quote_maker/machines/index_quote_maker_machines_add.html.twig', 'templateData' => ['fieldOptions' => $this->fieldOptions, 'item' => $item]]);
    }

    #[Route('/quote-maker/machines/save-add', name: 'save_quote_maker_machines_add', methods: ['POST'])]
    public function saveQuoteMakerMachinesAdd(Request $request, QuoteMakerRepository $machineRepository): Response
    {
        $id = $this->positiveInt($request->request->get('id'));
        $machine = $id ? $machineRepository->find($id) : null;
        if ($id && !$machine) {
            return $this->response(false, ['data' => ['error' => 'A gép nem található.']], 'json', Response::HTTP_NOT_FOUND);
        }

        $data = $this->decodeJsonObject($request->request->get('data'));
        if ($data === null) {
            return $this->response(false, ['data' => ['error' => 'A data mező nem érvényes JSON.']], 'json', Response::HTTP_BAD_REQUEST);
        }

        $isNew = !$machine;
        $now = new \DateTimeImmutable();
        $userId = $this->getUser()?->getId();
        if ($isNew) {
            $machine = (new QuoteMakerMachine())->setDatetimeAdd($now)->setUidAdd($userId);
        }
        $machine->setData($data)->setDatetimeLast($now)->setUidLast($userId);
        if ($request->request->has('title')) {
            $title = trim((string) $request->request->get('title'));
            $machine->setTitle($title !== '' ? mb_substr($title, 0, 255) : null);
        }
        if ($request->request->has('code')) {
            $code = trim((string) $request->request->get('code'));
            $machine->setCode($code !== '' ? mb_substr($code, 0, 255) : null);
        }
        $machineRepository->save($machine);

        return $this->response(true, ['data' => ['id' => $machine->getId(), 'mode' => $isNew ? 'insert' : 'update']]);
    }

    #[Route('/quote-maker/pdf-preview', name: 'index_quote_maker_pdf_preview', methods: ['POST'])]
    public function indexQuoteMakerPdfPreview(
        Request $request,
        QuoteMakerQuoteRepository $quoteRepository,
        PartnerRepository $partnerRepository,
        ProjectRepository $projectRepository,
        QuoteMakerRepository $machineRepository,
    ): Response {
        $quoteCode = trim((string) $request->request->get('quote_code', ''));
        if ($quoteCode !== '') {
            // Előkészítve: a következő ütemben itt töltjük vissza kód alapján a mentett pillanatképet.
            // $storedQuote = $quoteRepository->findOneBy(['code' => $quoteCode]);
            // $quoteData = $storedQuote?->getData();
        }

        $payload = $this->decodeJsonObject($request->request->get('data'));
        if ($payload === null) {
            return $this->response(false, ['data' => ['error' => 'Az előnézet adatai nem érvényes JSON-adatok.']], 'json', Response::HTTP_BAD_REQUEST);
        }

        $existingData = null;
        $id = $this->positiveInt($payload['id'] ?? $request->request->get('id'));
        if ($id) {
            $existingData = $quoteRepository->find($id)?->getData();
        }

        try {
            $quoteData = $this->buildQuoteSnapshot($payload, $partnerRepository, $projectRepository, $machineRepository, $existingData);
        } catch (\InvalidArgumentException $exception) {
            return $this->response(false, ['data' => ['error' => $exception->getMessage()]], 'json', Response::HTTP_BAD_REQUEST);
        }

        $projectDirectory = (string) $this->getParameter('kernel.project_dir');
        try {
            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);
            $options->set('isPhpEnabled', false);
            $dompdf = new Dompdf($options);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->loadHtml($this->renderView('quote_maker/quotes/pdf/index_quote_pdf_preview.html.twig', [
                'quote' => $quoteData,
                'fieldOptions' => $this->fieldOptions,
                'logoDataUri' => $this->imageDataUri($projectDirectory . '/public/assets/img/4us_logo_mail.png'),
            ]), 'UTF-8');
            $dompdf->render();
            $pdf = $dompdf->output();
        } catch (\Throwable) {
            return $this->response(false, ['data' => ['error' => 'A PDF előállítása nem sikerült.']], 'json', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = new Response($pdf);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Length', (string) strlen($pdf));
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'arajanlat-elonezet.pdf'));
        return $response;
    }

    private function buildQuoteSnapshot(
        array $payload,
        PartnerRepository $partnerRepository,
        ProjectRepository $projectRepository,
        QuoteMakerRepository $machineRepository,
        ?array $existingData = null,
    ): array {
        $partnerInput = is_array($payload['partner'] ?? null) ? $payload['partner'] : [];
        $projectInput = is_array($payload['project'] ?? null) ? $payload['project'] : [];
        $partnerId = $this->positiveInt($partnerInput['id'] ?? $payload['partner_id'] ?? null);
        $projectId = $this->positiveInt($projectInput['id'] ?? $payload['project_id'] ?? null);
        if (!$partnerId) {
            throw new \InvalidArgumentException('A partner kiválasztása kötelező.');
        }
        if (!$projectId) {
            throw new \InvalidArgumentException('A projekt kiválasztása kötelező.');
        }

        $existingPartner = is_array($existingData['partner'] ?? null) ? $existingData['partner'] : null;
        if ((int) ($existingPartner['id'] ?? 0) === $partnerId) {
            $partner = $existingPartner;
        } else {
            $partnerEntity = $partnerRepository->find($partnerId);
            if (!$partnerEntity) {
                throw new \InvalidArgumentException('A kiválasztott partner nem található.');
            }
            $partner = $this->snapshotPartner($partnerEntity);
        }

        $existingProject = is_array($existingData['project'] ?? null) ? $existingData['project'] : null;
        if ((int) ($existingProject['id'] ?? 0) === $projectId) {
            $project = $existingProject;
        } else {
            $projectEntity = $projectRepository->find($projectId);
            if (!$projectEntity) {
                throw new \InvalidArgumentException('A kiválasztott projekt nem található.');
            }
            $project = $this->snapshotProject($projectEntity);
        }

        $machineInputs = is_array($payload['machines'] ?? null) ? array_values($payload['machines']) : [];
        if (count($machineInputs) < 1 || count($machineInputs) > 3) {
            throw new \InvalidArgumentException('Az ajánlatban legalább 1, legfeljebb 3 gép szerepelhet.');
        }

        $existingMachines = is_array($existingData['machines'] ?? null) ? array_values($existingData['machines']) : [];
        $usedExistingMachineIndexes = [];
        $machines = [];
        foreach ($machineInputs as $index => $machineInput) {
            if (!is_array($machineInput)) {
                throw new \InvalidArgumentException('A gépadatok formátuma hibás.');
            }
            $machineId = $this->positiveInt($machineInput['id'] ?? $machineInput['machine_id'] ?? null);
            if (!$machineId) {
                throw new \InvalidArgumentException(($index + 1) . '. daru kiválasztása kötelező.');
            }

            $existingMachine = null;
            foreach ($existingMachines as $existingIndex => $candidate) {
                if (!isset($usedExistingMachineIndexes[$existingIndex])
                    && is_array($candidate)
                    && (int) ($candidate['id'] ?? 0) === $machineId) {
                    $existingMachine = $candidate;
                    $usedExistingMachineIndexes[$existingIndex] = true;
                    break;
                }
            }
            if ((int) ($existingMachine['id'] ?? 0) === $machineId) {
                $base = $existingMachine;
            } else {
                $machineEntity = $machineRepository->find($machineId);
                if (!$machineEntity) {
                    throw new \InvalidArgumentException(($index + 1) . '. daru nem található.');
                }
                $base = $this->snapshotMachine($machineEntity);
            }
            $machines[] = $this->calculateMachineSnapshot($base, $machineInput);
        }

        $quoteDate = trim((string) ($payload['quote_date'] ?? date('Y-m-d')));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $quoteDate);
        if (!$date || $date->format('Y-m-d') !== $quoteDate) {
            throw new \InvalidArgumentException('Az ajánlat dátuma érvénytelen.');
        }

        $status = (string) ($payload['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'closed'], true)) {
            $status = 'draft';
        }

        return [
            'schema_version' => 1,
            'quote_code' => mb_substr(trim((string) ($payload['quote_code'] ?? $existingData['quote_code'] ?? '')), 0, 255),
            'quote_date' => $quoteDate,
            'status' => $status,
            'status_title' => $status === 'closed' ? 'Lezárt' : 'Piszkozat',
            'partner' => $partner,
            'project' => $project,
            'contact_name' => mb_substr(trim((string) ($payload['contact_name'] ?? $partner['name'] ?? '')), 0, 255),
            'contact_email' => mb_substr(trim((string) ($payload['contact_email'] ?? $partner['email'] ?? '')), 0, 255),
            'project_location' => mb_substr(trim((string) ($payload['project_location'] ?? $project['description'] ?? '')), 0, 500),
            'notes' => mb_substr(trim((string) ($payload['notes'] ?? '')), 0, 5000),
            'machines' => $machines,
            'costs' => ['eur_huf' => self::EUR_HUF, 'anker_costs' => $this->ankerCosts, 'other_costs' => $this->other_costs],
        ];
    }

    private function calculateMachineSnapshot(array $base, array $input): array
    {
        $data = is_array($base['data'] ?? null) ? $base['data'] : [];
        $inputData = is_array($input['data'] ?? null) ? $input['data'] : [];
        foreach ($inputData as $key => $value) {
            if (is_string($key)) {
                $data[$key] = is_scalar($value) || $value === null ? $value : '';
            }
        }

        $anchored = filter_var($input['anchored'] ?? false, FILTER_VALIDATE_BOOL);
        $data['kezelo_7_17'] = $this->valueOrDefault($data['kezelo_7_17'] ?? null, $this->other_costs['gepkezelo_oradija_07_17']['value']);
        $data['kezelo_17_7'] = $this->valueOrDefault($data['kezelo_17_7'] ?? null, $this->other_costs['gepkezelo_oradija_17_07']['value']);
        $data['kezelo_vasarnap_unnep'] = $this->valueOrDefault($data['kezelo_vasarnap_unnep'] ?? null, $this->other_costs['gepkezelo_oradija_vasarnap_unnepnap']['value']);
        $data['veszelymentes_tervirat_egyszeri_dij'] = $this->valueOrDefault($data['veszelymentes_tervirat_egyszeri_dij'] ?? null, $this->other_costs['veszelymentes_tervirat']['value']);
        $data['uzembe_helyezes_ev'] = $this->valueOrDefault($data['uzembe_helyezes_ev'] ?? null, $this->other_costs['toronydaru_uzembe_helyezes_ev_meres']['value']);
        $data['vedotav'] = $this->valueOrDefault($data['vedotav'] ?? null, 6);
        $context = ['anchored' => $anchored, 'eur_huf' => self::EUR_HUF];

        foreach ($this->fieldOptions as $fieldOption) {
            if ($fieldOption['field_calculated']) {
                $data[$fieldOption['field_code']] = $this->calculateField(array_replace($data, ['_context' => $context]), $fieldOption['field_code']);
            }
        }

        $installationLocation = (string) ($input['installation_location'] ?? 'budapest');
        if (!in_array($installationLocation, ['budapest', 'videk'], true)) {
            $installationLocation = 'budapest';
        }
        $installationDays = max(1, min(10, (int) ($input['installation_days'] ?? 2)));
        $distanceKm = max(0, $this->numericValue($input['distance_km'] ?? 0));
        $jibLength = $this->metricValue($data['gemkinyulas'] ?? 0);
        $hookHeight = $this->metricValue($data['horogmagassag'] ?? 0);
        $truckUnits = ($jibLength + $hookHeight) > 0 ? (($jibLength + $hookHeight) / 12) + 1 : 0;
        $shippingCost = $truckUnits * $this->other_costs['kamion_szallitasi_dij']['value'];
        $assemblyCost = $this->other_costs['szerelocsapat_dija']['value'];
        $craneCategory = (string) (int) round($this->numericValue($data['telepitheto_autodaru_max_10m'] ?? 0));
        $craneCostRow = $this->ankerCosts[$craneCategory] ?? null;
        $mobileCraneCost = 0.0;
        if ($anchored && !$craneCostRow) {
            throw new \InvalidArgumentException('Ankeres telepítésnél a „Telepíthető autódaru max. 10m” értékének szerepelnie kell az autódaru díjtáblában.');
        }
        if ($anchored && $craneCostRow) {
            $dispatchCost = $installationLocation === 'videk' ? $distanceKm * $craneCostRow['cost_videk'] : $craneCostRow['cost_budapest'];
            $mobileCraneCost = ($installationDays * $craneCostRow['cost_daily']) + $dispatchCost;
            $calculatedOneTimeCost = $shippingCost + $mobileCraneCost + $assemblyCost;
            $data['telepites_egyszeri_dij'] = $this->normalNumber($calculatedOneTimeCost);
            $data['bontas_egyszeri_dij'] = $this->normalNumber($calculatedOneTimeCost);
        }

        return [
            'id' => (int) ($base['id'] ?? 0),
            'title' => $base['title'] ?? null,
            'code' => $base['code'] ?? null,
            'datetime_add' => $base['datetime_add'] ?? null,
            'datetime_last' => $base['datetime_last'] ?? null,
            'uid_add' => $base['uid_add'] ?? null,
            'uid_last' => $base['uid_last'] ?? null,
            'anchored' => $anchored,
            'installation_date' => mb_substr(trim((string) ($input['installation_date'] ?? '')), 0, 100),
            'rental_interval' => mb_substr(trim((string) ($input['rental_interval'] ?? '')), 0, 100),
            'installation_location' => $installationLocation,
            'distance_km' => $this->normalNumber($distanceKm),
            'installation_days' => $installationDays,
            'data' => $data,
            'calculation' => [
                'eur_huf' => self::EUR_HUF,
                'truck_units' => round($truckUnits, 4),
                'shipping_cost' => $this->normalNumber($shippingCost),
                'mobile_crane_category' => $craneCategory !== '0' ? $craneCategory : null,
                'mobile_crane_cost_row' => $craneCostRow,
                'mobile_crane_cost' => $this->normalNumber($mobileCraneCost),
                'assembly_team_cost' => $assemblyCost,
            ],
        ];
    }

    private function calculateField(array $data, string $fieldCode = ''): mixed
    {
        $context = is_array($data['_context'] ?? null) ? $data['_context'] : [];
        return match ($fieldCode) {
            'haz_absz_mag' => $this->normalNumber($this->numericValue($data['haz_magassaga'] ?? 0) - $this->numericValue($data['telepitesi_szint_mag'] ?? 0)),
            'tenyleges_mag' => $this->normalNumber($this->numericValue($data['haz_absz_mag'] ?? 0) + $this->numericValue($data['vedotav'] ?? 6)),
            'absz_mag_daru' => $this->normalNumber($this->numericValue($data['tenyleges_mag'] ?? 0) + $this->numericValue($data['telepitesi_szint_mag'] ?? 0)),
            'anker_elhelyezes_dija_huf' => filter_var($context['anchored'] ?? false, FILTER_VALIDATE_BOOL)
                ? $this->normalNumber($this->numericValue($data['anker_elhelyezes_dija_eur'] ?? 0) * $this->numericValue($context['eur_huf'] ?? self::EUR_HUF))
                : 0,
            'ar_eur' => $this->normalNumber($this->numericValue($data['beszerzesi_ar'] ?? 0) / 24),
            'ar_huf' => $this->normalNumber($this->numericValue($data['ar_eur'] ?? 0) * $this->numericValue($context['eur_huf'] ?? self::EUR_HUF)),
            default => $data[$fieldCode] ?? null,
        };
    }

    private function snapshotPartner(Partner $partner): array
    {
        return [
            'id' => $partner->getId(), 'code' => $partner->getCode(), 'name' => $partner->getName(), 'type' => $partner->getType(),
            'tax_number' => $partner->getTaxNumber(), 'email' => $partner->getEmail(), 'phone' => $partner->getPhone(), 'website' => $partner->getWebsite(),
            'country' => $partner->getCountry(), 'zip' => $partner->getZip(), 'city' => $partner->getCity(), 'address' => $partner->getAddress(),
            'description' => $partner->getDescription(), 'status' => $partner->getStatus(),
            'datetime_add' => $partner->getDatetimeAdd()?->format(DATE_ATOM), 'datetime_last' => $partner->getDatetimeLast()?->format(DATE_ATOM),
            'uid_add' => $partner->getUidAdd(), 'uid_last' => $partner->getUidLast(),
        ];
    }

    private function snapshotProject(Project $project): array
    {
        return [
            'id' => $project->getId(), 'code' => $project->getCode(), 'name' => $project->getName(), 'description' => $project->getDescription(),
            'status' => $project->getStatus(), 'datetime_add' => $project->getDatetimeAdd()?->format(DATE_ATOM),
            'datetime_last' => $project->getDatetimeLast()?->format(DATE_ATOM), 'uid_add' => $project->getUidAdd(), 'uid_last' => $project->getUidLast(),
        ];
    }

    private function snapshotMachine(QuoteMakerMachine $machine): array
    {
        return [
            'id' => $machine->getId(), 'title' => $machine->getTitle(), 'code' => $machine->getCode(), 'data' => $machine->getData() ?? [],
            'datetime_add' => $machine->getDatetimeAdd()?->format(DATE_ATOM), 'datetime_last' => $machine->getDatetimeLast()?->format(DATE_ATOM),
            'uid_add' => $machine->getUidAdd(), 'uid_last' => $machine->getUidLast(),
        ];
    }

    private function generateQuoteCode(QuoteMakerQuoteRepository $quoteRepository): string
    {
        do {
            $code = 'A' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        } while ($quoteRepository->findOneBy(['code' => $code]));
        return $code;
    }

    private function decodeFilters(Request $request): ?array
    {
        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        return is_array($filters) ? $filters : null;
    }

    private function decodeJsonObject(mixed $value): ?array
    {
        if (!is_string($value)) {
            return null;
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return is_array($decoded) ? $decoded : null;
    }

    private function positiveInt(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }
        $string = trim((string) $value);
        if ($string === '' || strtolower($string) === 'null' || !ctype_digit($string)) {
            return null;
        }
        $number = (int) $string;
        return $number > 0 ? $number : null;
    }

    private function numericValue(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $string = str_replace(["\xc2\xa0", ' '], '', trim((string) $value));
        if (preg_match('/-?\d+(?:[.,]\d+)?/u', $string, $matches) !== 1) {
            return 0.0;
        }
        return (float) str_replace(',', '.', $matches[0]);
    }

    private function metricValue(mixed $value): float
    {
        $string = trim((string) $value);
        if (preg_match_all('/(-?\d+(?:[.,]\d+)?)\s*m\b/ui', $string, $matches) && !empty($matches[1])) {
            return (float) str_replace(',', '.', (string) end($matches[1]));
        }
        return $this->numericValue($value);
    }

    private function normalNumber(float|int $value): float|int
    {
        $rounded = round((float) $value, 2);
        return abs($rounded - round($rounded)) < 0.00001 ? (int) round($rounded) : $rounded;
    }

    private function valueOrDefault(mixed $value, int|float|string $default): mixed
    {
        return $value === null || trim((string) $value) === '' ? $default : $value;
    }

    private function imageDataUri(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $mimeType = mime_content_type($path) ?: 'image/png';
        $contents = file_get_contents($path);
        return $contents === false ? null : 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }
}
