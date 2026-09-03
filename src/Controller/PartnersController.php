<?php

namespace App\Controller;

use App\Entity\Partner;
use App\Entity\PartnerContact;
use App\Repository\PartnerContactRepository;
use App\Repository\PartnerRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PartnersController extends BaseController
{
    private const ITEMS_PER_PAGE = 10;

    #[Route('/partners', name: 'index_partners')]
    public function index(): Response
    {
        return $this->render('partners/index.html.twig');
    }

    #[Route('/partners/add', name: 'index_partners_add')]
    public function indexAdd(Request $request, PartnerRepository $partnerRepository): Response
    {
        $idRaw = $request->query->get('id');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        $item = $id > 0 ? $partnerRepository->find($id) : null;

        $countries = [
            'HU' => 'Magyarország',
            'AT' => 'Ausztria',
            'DE' => 'Németország',
            'SK' => 'Szlovákia',
            'RO' => 'Románia',
            'HR' => 'Horvátország',
            'SI' => 'Szlovénia',
            'RS' => 'Szerbia',
            'UA' => 'Ukrajna',
            'PL' => 'Lengyelország',
            'CZ' => 'Csehország',
            'IT' => 'Olaszország',
            'FR' => 'Franciaország',
            'ES' => 'Spanyolország',
            'PT' => 'Portugália',
            'NL' => 'Hollandia',
            'BE' => 'Belgium',
            'LU' => 'Luxemburg',
            'CH' => 'Svájc',
            'GB' => 'Egyesült Királyság',
            'IE' => 'Írország',
            'DK' => 'Dánia',
            'SE' => 'Svédország',
            'NO' => 'Norvégia',
            'FI' => 'Finnország',
            'EE' => 'Észtország',
            'LV' => 'Lettország',
            'LT' => 'Litvánia',
            'GR' => 'Görögország',
            'BG' => 'Bulgária',
            'AL' => 'Albánia',
            'BA' => 'Bosznia-Hercegovina',
            'ME' => 'Montenegró',
            'MK' => 'Észak-Macedónia',
            'MD' => 'Moldova',
            'TR' => 'Törökország',
            'US' => 'Egyesült Államok',
            'CA' => 'Kanada',
            'MX' => 'Mexikó',
            'BR' => 'Brazília',
            'AR' => 'Argentína',
            'CN' => 'Kína',
            'JP' => 'Japán',
            'KR' => 'Dél-Korea',
            'IN' => 'India',
            'AU' => 'Ausztrália',
            'NZ' => 'Új-Zéland',
            'RU' => 'Oroszország',
        ];

        return $this->response(true, [
            'template' => 'partners/index_add.html.twig',
            'templateData' => [
                'item' => $item,
                'countries' => $countries,
            ],
            'data' => [],
        ]);
    }

    #[Route('/partners/save-partners', name: 'save_partners', methods: ['POST'])]
    public function savePartners(
        Request $request,
        PartnerRepository $partnerRepository,
        PartnerContactRepository $contactRepository,
    ): Response
    {
        $postData = [];
        foreach ($request->request->all() as $key => $value) {
            $postData[$key] = is_string($value) ? trim($value) : $value;
        }

        $id = (int) ($postData['id'] ?? 0);
        $partner = $id > 0 ? $partnerRepository->find($id) : null;
        $isNew = $partner === null;
        $taxNumber = $this->nullableString($postData['tax_number'] ?? null);

        if ($taxNumber !== null) {
            $taxNumberOwner = $partnerRepository->findOneBy(['taxNumber' => $taxNumber]);
            if ($taxNumberOwner && $taxNumberOwner->getId() !== $partner?->getId()) {
                return $this->response(false, [
                    'data' => ['error' => 'Ezzel az adószámmal már létezik partner.'],
                ], 'json', Response::HTTP_CONFLICT);
            }
        }

        $contactName = (string) ($postData['contact_name'] ?? '');
        $contactTitle = $this->nullableString($postData['contact_title'] ?? null);
        $contactPhone = $this->nullableString($postData['contact_phone'] ?? null);
        $contactEmail = $this->nullableString($postData['contact_email'] ?? null);
        $contactDescription = $this->nullableString($postData['contact_description'] ?? null);
        $hasContactData = $contactName !== '' || $contactTitle !== null || $contactPhone !== null
            || $contactEmail !== null || $contactDescription !== null;

        if ($isNew && $hasContactData && $contactName === '') {
            return $this->response(false, [
                'data' => ['error' => 'Az alapértelmezett kapcsolattartó nevét add meg.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }
        if ($isNew && $contactEmail !== null && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen kapcsolattartói e-mail-cím.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        if ($isNew) {
            $partner = new Partner();
            $partner->setDatetimeAdd(new \DateTimeImmutable());
            $partner->setUidAdd($this->getUser()?->getId());
        }

        $name = (string) ($postData['name'] ?? '');
        $code = (string) ($postData['code'] ?? '');
        if ($code === '') {
            $code = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
        }

        $partner
            ->setCode($code)
            ->setName($name)
            ->setType($this->nullableString($postData['type'] ?? null))
            ->setTaxNumber($taxNumber)
            ->setEmail($this->nullableString($postData['email'] ?? null))
            ->setPhone($this->nullableString($postData['phone'] ?? null))
            ->setWebsite($this->nullableString($postData['website'] ?? null))
            ->setCountry($this->nullableString($postData['country'] ?? null))
            ->setZip($this->nullableString($postData['zip'] ?? null))
            ->setCity($this->nullableString($postData['city'] ?? null))
            ->setAddress($this->nullableString($postData['address'] ?? null))
            ->setDescription($this->nullableString($postData['description'] ?? null))
            ->setStatus((string) ($postData['status'] ?? '1'));

        $partner->setDatetimeLast(new \DateTimeImmutable());
        $partner->setUidLast($this->getUser()?->getId());

        try {
            if ($isNew && $hasContactData) {
                $now = new \DateTimeImmutable();
                $userId = $this->getUser()?->getId();
                $contact = (new PartnerContact())
                    ->setPartner($partner)
                    ->setName($contactName)
                    ->setTitle($contactTitle)
                    ->setPhone($contactPhone)
                    ->setEmail($contactEmail)
                    ->setDescription($contactDescription)
                    ->setDefaultContact(true)
                    ->setDatetimeAdd($now)
                    ->setDatetimeLast($now)
                    ->setUidAdd($userId)
                    ->setUidLast($userId);

                $partnerRepository->save($partner, false);
                $contactRepository->save($contact);
            } else {
                $partnerRepository->save($partner);
            }
        } catch (UniqueConstraintViolationException) {
            return $this->response(false, [
                'data' => ['error' => 'Ezzel az adószámmal már létezik partner.'],
            ], 'json', Response::HTTP_CONFLICT);
        }

        return $this->response(true, [
            'template' => null,
            'templateData' => [],
            'data' => [
                'id' => $partner->getId(),
                'mode' => $isNew ? 'insert' : 'update',
                'post' => $postData,
            ],
        ]);
    }

    #[Route('/partners/delete-partner', name: 'delete_partner', methods: ['DELETE'])]
    public function deletePartner(Request $request, PartnerRepository $partnerRepository): Response
    {
        $id = $request->request->getInt('id');
        if ($id <= 0) {
            return $this->response(false, [
                'data' => [
                    'error' => 'Missing id.',
                ],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $partner = $partnerRepository->find($id);
        if (!$partner) {
            return $this->response(false, [
                'data' => [
                    'id' => $id,
                    'error' => 'Partner not found.',
                ],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $partnerRepository->delete($partner);

        return $this->response(true, [
            'data' => [
                'id' => $id,
                'deleted' => true,
            ],
        ]);
    }

    #[Route('/partners/list', name: 'list_partners', methods: ['GET', 'POST'])]
    public function listPartners(Request $request, PartnerRepository $partnerRepository): Response
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
        $list = $partnerRepository->findList($filters, $page, self::ITEMS_PER_PAGE);

        $content = $this->renderView('partners/list_partners.html.twig', [
            'records' => $list['records'],
            'filters' => $filters,
            'page' => $list['page'],
            'totalPages' => $list['totalPages'],
            'totalRecords' => $list['totalRecords'],
            'itemsPerPage' => $list['itemsPerPage'],
        ]);

        return $this->response(true, [
            'content' => $content,
            'data' => [
                'filters' => $filters,
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
                'itemsPerPage' => $list['itemsPerPage'],
            ],
        ]);
    }


    #[Route('/partners/contacts', name: 'index_partner_contacts_main')]
    public function index_partner_contacts_main(Request $request, PartnerRepository $partnerRepository): Response
    {
        $partnerId = $request->request->getInt('id', $request->query->getInt('id'));
        $partner = $partnerId > 0 ? $partnerRepository->find($partnerId) : null;

        if (!$partner) {
            return $this->response(false, [
                'data' => ['error' => 'Partner not found.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        return $this->response(true, [
            'template' =>'partners/contacts/main.html.twig',
            'templateData' => [
                'partner' => $partner,
            ],
            'data' => [],
        ]);
    }

    #[Route('/partners/contacts/add', name: 'index_partner_contacts_add', methods: ['GET'])]
    public function indexPartnerContactsAdd(
        Request $request,
        PartnerRepository $partnerRepository,
        PartnerContactRepository $contactRepository,
    ): Response {
        $partnerId = $request->query->getInt('partner_id');
        $contactId = $request->query->getInt('id');
        $partner = $partnerId > 0 ? $partnerRepository->find($partnerId) : null;
        $item = $contactId > 0 ? $contactRepository->find($contactId) : null;

        if (!$partner || ($contactId > 0 && (!$item || $item->getPartner()?->getId() !== $partner->getId()))) {
            return $this->response(false, [
                'data' => ['error' => 'Partner contact not found.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        return $this->response(true, [
            'template' => 'partners/contacts/index_add.html.twig',
            'templateData' => [
                'partner' => $partner,
                'item' => $item,
            ],
        ]);
    }

    #[Route('/partners/contacts/save', name: 'save_partner_contact', methods: ['POST'])]
    public function savePartnerContact(
        Request $request,
        PartnerRepository $partnerRepository,
        PartnerContactRepository $contactRepository,
    ): Response {
        $partnerId = $request->request->getInt('partner_id');
        $contactIdRaw = $request->request->get('id');
        $contactId = is_numeric($contactIdRaw) ? (int) $contactIdRaw : 0;
        $partner = $partnerId > 0 ? $partnerRepository->find($partnerId) : null;
        $contact = $contactId > 0 ? $contactRepository->find($contactId) : null;

        if (!$partner || ($contactId > 0 && (!$contact || $contact->getPartner()?->getId() !== $partner->getId()))) {
            return $this->response(false, [
                'data' => ['error' => 'Partner contact not found.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $name = trim((string) $request->request->get('name', ''));
        $email = $this->nullableString($request->request->get('email'));
        if ($name === '') {
            return $this->response(false, [
                'data' => ['error' => 'A név megadása kötelező.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response(false, [
                'data' => ['error' => 'Érvénytelen e-mail-cím.'],
            ], 'json', Response::HTTP_BAD_REQUEST);
        }

        $isNew = $contact === null;
        if ($isNew) {
            $contact = (new PartnerContact())
                ->setPartner($partner)
                ->setDatetimeAdd(new \DateTimeImmutable())
                ->setUidAdd($this->getUser()?->getId());
        }

        $contact
            ->setName($name)
            ->setTitle($this->nullableString($request->request->get('title')))
            ->setEmail($email)
            ->setPhone($this->nullableString($request->request->get('phone')))
            ->setDescription($this->nullableString($request->request->get('description')))
            ->setDefaultContact($request->request->getBoolean('default_contact'))
            ->setDatetimeLast(new \DateTimeImmutable())
            ->setUidLast($this->getUser()?->getId());

        $contactRepository->save($contact);

        return $this->response(true, [
            'data' => [
                'id' => $contact->getId(),
                'mode' => $isNew ? 'insert' : 'update',
            ],
        ]);
    }

    #[Route('/partners/contacts/list', name: 'list_partner_contacts', methods: ['GET', 'POST'])]
    public function listPartnerContacts(
        Request $request,
        PartnerRepository $partnerRepository,
        PartnerContactRepository $contactRepository,
    ): Response {
        $partnerId = $request->request->getInt('partner_id', $request->query->getInt('partner_id'));
        $partner = $partnerId > 0 ? $partnerRepository->find($partnerId) : null;
        if (!$partner) {
            return $this->response(false, [
                'data' => ['error' => 'Partner not found.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $filters = json_decode((string) $request->request->get('filters', '{}'), true);
        if (!is_array($filters)) {
            $filters = [];
        }
        $page = max(1, $request->request->getInt('page', $request->query->getInt('page', 1)));
        $list = $contactRepository->findList($partner, $filters, $page, self::ITEMS_PER_PAGE);

        return $this->response(true, [
            'content' => $this->renderView('partners/contacts/list_partner_contacts.html.twig', [
                ...$list,
                'partner' => $partner,
                'filters' => $filters,
            ]),
            'data' => [
                'page' => $list['page'],
                'totalPages' => $list['totalPages'],
                'totalRecords' => $list['totalRecords'],
            ],
        ]);
    }

    #[Route('/partners/contacts/delete', name: 'delete_partner_contact', methods: ['DELETE'])]
    public function deletePartnerContact(Request $request, PartnerContactRepository $contactRepository): Response
    {
        $contact = $contactRepository->find($request->request->getInt('id'));
        if (!$contact) {
            return $this->response(false, [
                'data' => ['error' => 'Partner contact not found.'],
            ], 'json', Response::HTTP_NOT_FOUND);
        }

        $contactRepository->delete($contact);

        return $this->response(true, ['data' => ['deleted' => true]]);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
