<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EverlinkEventlogsController extends AbstractController
{
    private const ITEMS_PER_PAGE = 25;

    #[Route('/everlink/eventlog', name: 'app_everlink_eventlog')]
    public function index(
        Request $request,
        #[Autowire(service: 'doctrine.dbal.everlink_connection')]
        Connection $everlinkConnection
    ): Response {
        $page = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * self::ITEMS_PER_PAGE;

		$qb = $everlinkConnection->createQueryBuilder();

		$qb
			->from('eventlog', 'e')
			->andWhere('e.time > :date')
			->setParameter('date', '2026-06-01');

		$totalRecords = (int) (clone $qb)
			->select('COUNT(*)')
			->fetchOne();

		$totalPages = max(1, (int) ceil($totalRecords / self::ITEMS_PER_PAGE));

		if ($page > $totalPages) {
			return $this->redirectToRoute('app_everlink_eventlog', [
				'page' => $totalPages,
			]);
		}

		$records = $qb
			->select('e.*')
			->orderBy('e.time', 'DESC')
			->setFirstResult($offset)
			->setMaxResults(self::ITEMS_PER_PAGE)
			->fetchAllAssociative();
			
/*
        $countQb = $everlinkConnection->createQueryBuilder();
        $totalRecords = (int) $countQb
            ->select('COUNT(*)')
            ->from('eventlog', 'e')
            ->fetchOne();

        $totalPages = max(1, (int) ceil($totalRecords / self::ITEMS_PER_PAGE));

        if ($page > $totalPages) {
            return $this->redirectToRoute('app_everlink_eventlog', [
                'page' => $totalPages,
            ]);
        }

        $qb = $everlinkConnection->createQueryBuilder();
		$records = $qb
			->select('e.*')
			->from('eventlog', 'e')
			->andWhere('e.time > :date')
			->setParameter('date', '2026-01-01')
			->orderBy('e.time', 'DESC')
			->setFirstResult($offset)
			->setMaxResults(self::ITEMS_PER_PAGE)
			->fetchAllAssociative();
	*/
        return $this->render('everlink/eventlog.html.twig', [
            'records' => $records,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'itemsPerPage' => self::ITEMS_PER_PAGE,
        ]);
    }
}
