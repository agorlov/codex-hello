# codex-hello

This repository contains a minimal [Symfony](https://symfony.com/) application that responds with **"Hello codex!"** on the root route.

## Getting started

1. Install the PHP dependencies (Composer is already bundled with this project):
   ```bash
   composer install
   ```
2. Start a local development server from the project root:
   ```bash
   php -S 0.0.0.0:8000 -t public
   ```
3. Open http://localhost:8000 in your browser. You should see `Hello codex!` rendered by the Symfony application.

## Project structure

- `src/Controller/HelloController.php` contains the controller that serves the greeting.
- `config/routes.yaml` configures attribute-based routing for the controllers in `src/Controller`.

Feel free to extend this starter application with additional routes, templates, or services as needed.
