<?php

namespace App\Controller;

use App\Repository\TicketProcessingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tickets')]
final class TicketController extends AbstractController
{
    #[Route('/{ticketNumber}', name: 'ticket_show', methods: ['GET'])]
    public function show(string $ticketNumber, TicketProcessingRepository $tickets): Response
    {
        if (null === $tickets->findOneBy(['ticketNumber' => $ticketNumber])) {
            throw $this->createNotFoundException();
        }

        return $this->render('ticket/show.html.twig', [
            'ticketNumber' => $ticketNumber,
        ]);
    }
}
