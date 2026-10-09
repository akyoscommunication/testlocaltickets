<?php

namespace App\Controller;

use App\FileStorage\TicketFileStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class TicketFileController extends AbstractController
{
    public function __construct(
        private readonly TicketFileStorage $files,
    ) {
    }

    #[Route('/tickets/{ticketNumber}/files/{path}', name: 'ticket_file', requirements: ['path' => '.+'])]
    public function show(string $ticketNumber, string $path): Response
    {
        $relativePath = ltrim($path, '/');
        if (!$this->files->exists($ticketNumber, $relativePath)) {
            throw new NotFoundHttpException('Fichier introuvable.');
        }

        $content = $this->files->read($ticketNumber, $relativePath);
        $mime = $this->guessMime($relativePath);

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function guessMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'json' => 'application/json',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }
}
