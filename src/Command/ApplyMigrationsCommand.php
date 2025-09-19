<?php

namespace App\Command;

use App\Database\SqliteMigrations;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда консоли, применяющая все доступные SQLite-миграции.
 */
#[AsCommand(
    name: 'app:sqlite:migrate',
    description: 'Применяет все SQL-миграции SQLite.'
)]
final class ApplyMigrationsCommand extends Command
{
    /**
     * Получает сервис миграций и каталог с SQL-файлами.
     */
    public function __construct(
        private readonly SqliteMigrations $sqliteMigrations,
        private readonly string $migrationsDirectory,
    ) {
        parent::__construct();
    }

    /**
     * Запускает применение миграций и сообщает о результате в консоль.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->sqliteMigrations->applyMigrations($this->migrationsDirectory);
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success('Миграции успешно применены.');

        return Command::SUCCESS;
    }
}
