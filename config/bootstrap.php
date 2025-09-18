<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (!class_exists(Dotenv::class)) {
    throw new RuntimeException('Install the "symfony/dotenv" component to load environment variables.');
}

(new Dotenv())->usePutenv()->bootEnv(dirname(__DIR__).'/.env');
