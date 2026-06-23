<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class StockSnapshotCheckCommand extends BaseCommand
{
    protected $group       = 'Inventory';
    protected $name        = 'stock:snapshot-check';
    protected $description = 'List months with stock transactions but no snapshot.';
    protected $usage       = 'stock:snapshot-check';
    protected $arguments   = [];
    protected $options     = [];

    public function run(array $params): int
    {
        $db = Database::connect();

        // Find all distinct months from stock_transactions
        $txMonths = $db->query("
            SELECT DISTINCT DATE_FORMAT(transaction_date, '%Y-%m') as month
            FROM stock_transactions
            WHERE deleted_at IS NULL
            ORDER BY month
        ")->getResultArray();

        $gaps = [];
        foreach ($txMonths as $row) {
            $periodMonth = $row['month'] . '-01';
            $count = $db->table('monthly_stock_snapshots')
                ->where('period_month', $periodMonth)
                ->countAllResults();
            if ($count === 0) {
                $gaps[] = $row['month'];
            }
        }

        if (empty($gaps)) {
            CLI::write('All months with transactions have snapshots.', 'green');
            return EXIT_SUCCESS;
        }

        CLI::error('Missing snapshots for ' . count($gaps) . ' month(s):');
        foreach ($gaps as $month) {
            CLI::write('  - ' . $month, 'yellow');
        }
        return EXIT_ERROR;
    }
}
