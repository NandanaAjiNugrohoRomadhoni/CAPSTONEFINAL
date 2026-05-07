<?php

namespace App\Commands;

use App\Services\HistoricalOpnameBackfillService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class HistoricalOpnameBackfillCommand extends BaseCommand
{
    protected $group       = 'Inventory';
    protected $name        = 'opname:backfill-historical-ledger';
    protected $description = 'Backfill historical POSTED stock opname variances into ledger adjustments idempotently.';
    protected $usage       = 'opname:backfill-historical-ledger [--from YYYY-MM-DD] [--to YYYY-MM-DD]';
    protected $options     = [
        '--from' => 'Lower bound opname_date (inclusive).',
        '--to'   => 'Upper bound opname_date (inclusive).',
    ];

    public function run(array $params)
    {
        $fromDate = $this->resolveOption($params, 'from');
        $toDate   = $this->resolveOption($params, 'to');

        $service = new HistoricalOpnameBackfillService();
        $result  = $service->backfill($fromDate !== null ? (string) $fromDate : null, $toDate !== null ? (string) $toDate : null);

        if (! $result['success']) {
            CLI::error((string) $result['message']);

            $errors = $result['errors'] ?? [];
            if ($errors !== []) {
                CLI::write(json_encode($errors, JSON_UNESCAPED_SLASHES), 'yellow');
            }

            return EXIT_ERROR;
        }

        CLI::write((string) $result['message'], 'green');
        CLI::write(json_encode($result['data'] ?? [], JSON_UNESCAPED_SLASHES), 'green');

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
