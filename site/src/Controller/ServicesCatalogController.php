<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\TicketService\TicketServiceRegistry;

final class ServicesCatalogController extends AbstractController
{
    #[Route('/services', name: 'services_catalog')]
    public function index(
        TicketServiceRegistry $registry,
        #[Autowire(param: 'ticket_services_catalog')]
        array $catalog,
    ): Response {
        $items = [];
        foreach ($registry->all() as $service) {
            $key = $service->getKey();
            $meta = $catalog[$key] ?? [];
            $items[] = [
                'key' => $key,
                'label' => $service->getLabel(),
                'description' => $service->getDescription(),
                'category' => $service->getCategory(),
                'relaunch_ia' => $service->supportsRelaunch(),
                'params' => $meta['params'] ?? '—',
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp($a['category'].$a['key'], $b['category'].$b['key']));

        return $this->render('admin/services.html.twig', [
            'services' => $items,
        ]);
    }
}
