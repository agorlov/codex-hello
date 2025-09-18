<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HelloController
{
    #[Route('/', name: 'app_home')]
    public function __invoke(): Response
    {
        return new Response('Hello codex!');
    }
}
