<?php

namespace App\Database\Seeds;

use App\Models\ApprovalStatusModel;
use App\Models\ItemCategoryModel;
use App\Models\RoleModel;
use App\Models\SpkCalculationModel;
use App\Models\TransactionTypeModel;
use App\Models\UserModel;
use App\Services\DailyPatientService;
use App\Services\SpkBasahGenerationService;
use App\Services\SpkKeringPengemasGenerationService;
use App\Services\SpkStockPostingService;
use App\Services\StockSnapshotService;
use App\Services\StockTransactionService;
use CodeIgniter\Database\Seeder;
use CodeIgniter\CLI\CLI;
use DateTimeImmutable;
use RuntimeException;

class MonthlyExportScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $snapshotService = new StockSnapshotService();
        $patientService = new DailyPatientService();
        $basahService = new SpkBasahGenerationService();
        $keringService = new SpkKeringPengemasGenerationService();
        $postingService = new SpkStockPostingService();
        $transactionService = new StockTransactionService();

        $users = $this->resolveRequiredUsers();
        
        $months = ['2026-04', '2026-05'];

        foreach ($months as $month) {
            CLI::write("Seeding scenario for {$month}...");
            $this->seedMonthScenario($month, $users, $snapshotService, $patientService, $basahService, $keringService, $postingService, $transactionService);
        }
    }

    private function seedMonthScenario(
        string $month, 
        array $users,
        StockSnapshotService $snapshotService,
        DailyPatientService $patientService,
        SpkBasahGenerationService $basahService,
        SpkKeringPengemasGenerationService $keringService,
        SpkStockPostingService $postingService,
        StockTransactionService $transactionService
    ): void {
        $snapshotService->takeOpeningSnapshot($month);

        $monthStart = new DateTimeImmutable($month . '-01');
        $monthEnd = $monthStart->modify('last day of this month');

        $cursor = $monthStart;
        while ($cursor <= $monthEnd) {
            $serviceDate = $cursor->format('Y-m-d');
            $existing = $this->db->table('daily_patients')->where('service_date', $serviceDate)->countAllResults();
            if ($existing === 0) {
                $patientService->createDailyPatient([
                    'service_date' => $serviceDate,
                    'total_patients' => rand(100, 150),
                    'notes' => "Scenario patient count for {$month}",
                ]);
            }
            $cursor = $cursor->modify('+1 day');
        }

        $cursor = $monthStart;
        while ($cursor <= $monthEnd) {
            $serviceDate = $cursor->format('Y-m-d');
            $existingSpk = $this->db->table('spk_calculations')
                ->where('spk_type', SpkCalculationModel::TYPE_BASAH)
                ->where('calculation_date', $serviceDate)
                ->countAllResults();

            if ($existingSpk === 0) {
                $result = $basahService->generate(['service_date' => $serviceDate], $users['dapur_id']);
                if ($result['success']) {
                    $spkId = $result['data']['id'];
                    $postingService->post($spkId, SpkCalculationModel::TYPE_BASAH, $users['gudang_id']);
                }
            }
            $cursor = $cursor->modify('+2 days');
        }

        $existingKeringSpk = $this->db->table('spk_calculations')
            ->where('spk_type', SpkCalculationModel::TYPE_KERING_PENGEMAS)
            ->where('target_month', $month)
            ->countAllResults();

        if ($existingKeringSpk === 0) {
            $result = $keringService->generate(['target_month' => $month], $users['dapur_id']);
            if ($result['success']) {
                $spkId = $result['data']['id'];
                $postingService->post($spkId, SpkCalculationModel::TYPE_KERING_PENGEMAS, $users['gudang_id']);
            }
            $this->forceStockInForCategories($monthStart->modify('+2 days'), [ItemCategoryModel::NAME_KERING, 'PENGEMAS'], $users, $transactionService);
        }

        $cursor = $monthStart;
        while ($cursor <= $monthEnd) {
            $dateStr = $cursor->format('Y-m-d');
            
            $existingTx = $this->db->table('stock_transactions')
                ->where('transaction_date', $dateStr)
                ->where('user_id', $users['gudang_id'])
                ->where('type_id', 2) // OUT
                ->countAllResults();

            if ($existingTx === 0) {
                $items = $this->db->table('items')
                    ->select('id, name, qty')
                    ->where('deleted_at', null)
                    ->where('qty >', 0.5)
                    ->orderBy('id', 'RANDOM')
                    ->limit(15)
                    ->get()
                    ->getResultArray();

                if (!empty($items)) {
                    $details = [];
                    foreach ($items as $item) {
                        $qtyToTake = min((float)$item['qty'], (float)rand(1, 5));
                        if ($qtyToTake > 0.01) {
                            $details[] = [
                                'item_id' => (int)$item['id'],
                                'qty' => $qtyToTake,
                                'input_unit' => 'base',
                            ];
                        }
                    }

                    if (!empty($details)) {
                        $res = $transactionService->createTransaction([
                            'type_name' => TransactionTypeModel::NAME_OUT,
                            'transaction_date' => $dateStr,
                            'details' => $details,
                            // Removed 'reason' as it is not allowed by service
                        ], $users['gudang_id']);
                        
                        if (!$res['success']) {
                            CLI::error("OUT transaction failed for {$dateStr}: " . $res['message'] . " " . json_encode($res['errors'] ?? []));
                        }
                    }
                }
            }
            $cursor = $cursor->modify('+3 days');
        }
    }

    private function forceStockInForCategories(DateTimeImmutable $date, array $categoryNames, array $users, StockTransactionService $transactionService): void
    {
        $categoryModel = new ItemCategoryModel();
        $categoryIds = [];
        foreach ($categoryNames as $name) {
            $id = $categoryModel->getIdByName($name);
            if ($id) $categoryIds[] = (int)$id;
        }

        if (empty($categoryIds)) return;

        $items = $this->db->table('items')
            ->select('id')
            ->whereIn('item_category_id', $categoryIds)
            ->where('deleted_at', null)
            ->limit(10)
            ->get()
            ->getResultArray();

        if (empty($items)) return;

        $details = [];
        foreach ($items as $item) {
            $details[] = [
                'item_id' => (int)$item['id'],
                'qty' => (float)rand(100, 500),
                'input_unit' => 'base',
            ];
        }

        $res = $transactionService->createTransaction([
            'type_name' => TransactionTypeModel::NAME_IN,
            'transaction_date' => $date->format('Y-m-d'),
            'details' => $details,
            // Removed 'reason'
        ], $users['gudang_id']);
        
        if (!$res['success']) {
            CLI::error("Force IN failed: " . $res['message'] . " " . json_encode($res['errors'] ?? []));
        }
    }

    private function resolveRequiredUsers(): array
    {
        $roleModel = new RoleModel();
        $userModel = new UserModel();

        return [
            'admin_id'  => $this->resolveActiveUserIdByRole($roleModel, $userModel, RoleModel::NAME_ADMIN),
            'dapur_id'  => $this->resolveActiveUserIdByRole($roleModel, $userModel, RoleModel::NAME_DAPUR),
            'gudang_id' => $this->resolveActiveUserIdByRole($roleModel, $userModel, RoleModel::NAME_GUDANG),
        ];
    }

    private function resolveActiveUserIdByRole(RoleModel $roleModel, UserModel $userModel, string $roleName): int
    {
        $role = $roleModel->findByName($roleName);
        if ($role === null) {
            throw new RuntimeException("MonthlyExportScenarioSeeder requires role '{$roleName}' to be seeded.");
        }

        $user = $userModel
            ->where('role_id', $role['id'])
            ->where('is_active', true)
            ->where('deleted_at', null)
            ->first();

        if ($user === null) {
            throw new RuntimeException("MonthlyExportScenarioSeeder requires an active user for role '{$roleName}'.");
        }

        return (int) $user['id'];
    }
}
