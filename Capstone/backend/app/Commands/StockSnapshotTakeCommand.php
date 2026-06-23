<?php

namespace App\Commands;

use App\Services\StockSnapshotService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class StockSnapshotTakeCommand extends BaseCommand
{
    protected $group       = 'Inventory';
    protected $name        = 'stock:snapshot-take';
    protected $description = 'Take opening stock snapshot for a month. Idempotent.';
    protected $usage       = 'stock:snapshot-take [--month YYYY-MM] [--force]';
    protected $arguments   = [];
    protected $options     = [
        '--month' => 'Target month in YYYY-MM format. Defaults to current month.',
        '--force' => 'Delete existing snapshot and re-take.',
    ];

    public function run(array $params): int
    {
        $month = $this->resolveOption($params, 'month') ?? date('Y-m');
        $force = $this->resolveOption($params, 'force') !== null;

        $service = new StockSnapshotService();

        $result = $force
            ? $service->retakeOpeningSnapshot($month)
            : $service->takeOpeningSnapshot($month);

        if (!$result['success']) {
            CLI::error($result['message']);
            return EXIT_ERROR;
        }

        CLI::write($result['message'], 'green');
        CLI::write('Items captured: ' . $result['count'], 'green');
        return EXIT_SUCCESS;
    }

    private function resolveOption(array $params, string $name): ?string
    {
        $fromParams = $params[$name]
            ?? $params['--' . $name]
            ?? null;

        if ($fromParams !== null && $fromParams !== '') {
            return (string) $fromParams;
        }

        $fromCli = CLI::getOption($name);
        if ($fromCli === null || $fromCli === '') {
            return null;
        }

        return (string) $fromCli;
    }
}
