<?php

namespace App\Controller;

use App\Entity\CompareMachinesQuote;
use App\Repository\CompareMachinesQuoteRepository;
use App\Service\CompareMachinesCatalog;
use App\Service\CompareMachinesQuoteMailer;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CompareMachinesQuoteController extends AbstractController
{
    #[Route('/compare-machines/quote', name: 'compare_machines_quote_save', methods: ['POST'])]
    public function save(
        Request $request,
        CompareMachinesQuoteRepository $quoteRepository,
        CompareMachinesCatalog $catalog,
        CompareMachinesQuoteMailer $quoteMailer,
        LoggerInterface $logger,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('compare_machines_quote', $request->request->getString('_token'))) {
            return $this->json([
                'success' => false,
                'message' => 'A munkamenet lejárt. Frissítse az oldalt, majd próbálja újra.',
            ], Response::HTTP_FORBIDDEN);
        }

        $selectedMachine = trim($request->request->getString('selected_machine'));
        if ($selectedMachine === '') {
            $selectedMachine = trim($request->request->getString('machine_pair'));
        }

        $data = [
            'name' => trim($request->request->getString('name')),
            'company' => trim($request->request->getString('company')),
            'email' => trim($request->request->getString('email')),
            'phone' => trim($request->request->getString('phone')),
            'selected_machine' => $selectedMachine,
            'pair_id' => trim($request->request->getString('pair_id')),
            'months' => trim($request->request->getString('months')),
            'message' => trim($request->request->getString('message')),
            'privacy_policy' => $request->request->getString('privacy_policy'),
        ];

        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'Név megadása kötelező.';
        } elseif (mb_strlen($data['name']) > 150) {
            $errors[] = 'A név legfeljebb 150 karakter lehet.';
        }
        if ($data['company'] === '') {
            $errors[] = 'Cégnév megadása kötelező.';
        } elseif (mb_strlen($data['company']) > 200) {
            $errors[] = 'A cégnév legfeljebb 200 karakter lehet.';
        }
        if ($data['email'] === '') {
            $errors[] = 'E-mail megadása kötelező.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Érvényes e-mail címet adjon meg.';
        } elseif (mb_strlen($data['email']) > 254) {
            $errors[] = 'Az e-mail-cím túl hosszú.';
        }
        if ($data['phone'] === '') {
            $errors[] = 'Telefonszám megadása kötelező.';
        } elseif (mb_strlen($data['phone']) > 50) {
            $errors[] = 'A telefonszám legfeljebb 50 karakter lehet.';
        }
        if ($data['selected_machine'] === '') {
            $errors[] = 'Kiválasztott modell megadása kötelező.';
        }
        if (
            $data['months'] === ''
            || filter_var($data['months'], FILTER_VALIDATE_INT) === false
            || (int) $data['months'] < 1
            || (int) $data['months'] > 12
        ) {
            $errors[] = 'A futamidőnek 1 és 12 hónap közé kell esnie.';
        }
        if ($data['message'] === '') {
            $errors[] = 'Üzenet megadása kötelező.';
        } elseif (mb_strlen($data['message']) > 5000) {
            $errors[] = 'Az üzenet legfeljebb 5000 karakter lehet.';
        }
        if ($data['privacy_policy'] !== '1') {
            $errors[] = 'Az Adatkezelési nyilatkozat elfogadása kötelező.';
        }

        if ($errors !== []) {
            return $this->json([
                'success' => false,
                'message' => 'Kérem javítsa az alábbi adatokat:<br>' . implode('<br>', $errors),
                'errors' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        $selection = $catalog->resolveSelection($data['selected_machine'], $data['pair_id']);
        if ($selection === null) {
            return $this->json([
                'success' => false,
                'message' => 'A kiválasztott modellhez tartozó darupár nem található.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $months = (int) $data['months'];
        $tower = $catalog->buildMachineView(
            $selection['tower'],
            $months,
            $selection['selectedType'] === 'tower',
        );
        $mobile = $catalog->buildMachineView(
            $selection['mobile'],
            $months,
            $selection['selectedType'] === 'mobile',
        );
        $selectedMachineView = $selection['selectedType'] === 'tower' ? $tower : $mobile;
        $otherMachineView = $selection['otherType'] === 'tower' ? $tower : $mobile;
        $difference = abs((float) $tower['total'] - (float) $mobile['total']);
        $code = $this->generateQuoteCode();
        $storedData = [
            'Név' => $data['name'],
            'Cégnév' => $data['company'],
            'E-mail' => $data['email'],
            'Telefonszám' => $data['phone'],
            'Darupár' => $tower['name'] . ' / ' . $mobile['name'],
            'Kiválasztott modell' => $selectedMachineView['name'],
            'Kiválasztott típus' => $selectedMachineView['type'],
            'Összehasonlított modell' => $otherMachineView['name'],
            'Munkaidő hónapban' => $months,
            'Üzenet' => $data['message'],
            'Adatkezelési nyilatkozat' => 'Elfogadva',
            'Becsült különbség' => $catalog->formatMoney($difference),
            'Toronydaru adatai' => $tower,
            'Mobil toronydaru adatai' => $mobile,
        ];
        $quote = (new CompareMachinesQuote())
            ->setTitle('Darupár ajánlatkérés: ' . $selectedMachineView['name'] . ' - ' . $data['name'])
            ->setCode($code)
            ->setData($storedData);

        $quoteRepository->save($quote);

        $mailContext = [
            'quoteCode' => $code,
            'customer' => [
                'name' => $data['name'],
                'company' => $data['company'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'message' => $data['message'],
            ],
            'pairId' => $selection['pairId'],
            'months' => $months,
            'tower' => $tower,
            'mobile' => $mobile,
            'selectedMachine' => $selectedMachineView,
            'otherMachine' => $otherMachineView,
            'differenceFormatted' => $catalog->formatMoney($difference),
        ];

        try {
            $quoteMailer->send($mailContext);
        } catch (\Throwable $exception) {
            $logger->error('A darupár-ajánlatkérés visszaigazoló e-mailje nem küldhető el.', [
                'quote_id' => $quote->getId(),
                'quote_code' => $code,
                'recipient_email' => $data['email'],
                'exception' => $exception,
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Az ajánlatkérést rögzítettük, de az e-mailt nem sikerült elküldeni. Kérjük, vegye fel velünk a kapcsolatot.',
                'code' => $code,
                'id' => $quote->getId(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json([
            'success' => true,
            'message' => 'Az ajánlatkérést rögzítettük, az összehasonlítást elküldtük a megadott e-mail-címre.',
            'code' => $code,
            'id' => $quote->getId(),
            'emailSent' => true,
        ]);
    }

    private function generateQuoteCode(): string
    {
        return 'AJ' . date('Ym') . '-' . random_int(10000000, 99999999);
    }
}
