<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PromptCatalogController extends AbstractController
{
    #[Route('/admin/prompts', name: 'prompt_catalog')]
    public function index(#[Autowire(param: 'prompts')] array $prompts): Response
    {
        $catalog = [];
        foreach ($prompts as $key => $config) {
            $systemPath = $config['system'] ?? null;
            $userPath = $config['user'] ?? null;
            $catalog[] = [
                'key' => $key,
                'version' => $config['version'] ?? 1,
                'system' => is_string($systemPath) && is_readable($systemPath) ? file_get_contents($systemPath) : null,
                'user' => is_string($userPath) && is_readable($userPath) ? file_get_contents($userPath) : null,
                'system_path' => $systemPath,
                'user_path' => $userPath,
            ];
        }

        return $this->render('admin/prompts.html.twig', [
            'catalog' => $catalog,
        ]);
    }
}
