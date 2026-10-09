<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LogsController extends AbstractController
{
    #[Route('/logs', name: 'automation_logs')]
    public function index(): Response
    {
        return $this->render('logs/index.html.twig');
    }
}
