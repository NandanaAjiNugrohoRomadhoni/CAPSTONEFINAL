<?php

namespace App\Database\Seeds;

use App\Models\ApprovalStatusModel;
use App\Models\ItemCategoryModel;
use App\Models\RoleModel;
use App\Models\TransactionTypeModel;
use App\Models\UserModel;
use App\Services\SpkBasahGenerationService;
use App\Services\SpkKeringPengemasGenerationService;
use CodeIgniter\Database\Seeder;
use DateTimeImmutable;
use RuntimeException;

class RuntimeCurrentMonthSpkScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $runtimeAnchor = new DateTimeImmutable('today');
        $bounds        = $this->resolveMonthBounds($runtimeAnchor);
        $users         = $this->resolveRequiredUsers();

        $generationDates = $this->seedCurrentMonthDailyPatients($runtimeAnchor, $bounds['current_month_start']);

        $this->seedPreviousMonthApprovedOutTransactions(
            $bounds['previous_month_start'],
            $users['admin_id'],
            $users['gudang_id']
        );

        $this->generateCurrentMonthBasahSpks($generationDates, $users['dapur_id']);
        $this->generateCurrentMonthKeringPengemasSpk($bounds['current_month'], $users['dapur_id']);
    }

    /**
     * @return array{current_month:string,current_month_start:DateTimeImmutable,current_month_end:DateTimeImmutable,previous_month_start:DateTimeImmutable,previous_month_end:DateTimeImmutable}
     */
    private function resolveMonthBounds(DateTimeImmutable $runtimeAnchor): array
    {
        $currentMonthStart  = $runtimeAnchor->modify('first day of this month');
        $currentMonthEnd    = $runtimeAnchor->modify('last day of this month');
        $previousMonthStart = $currentMonthStart->modify('-1 month');
        $previousMonthEnd   = $previousMonthStart->modify('last day of this month');

        return [
            'current_month'       => $currentMonthStart->format('Y-m'),
            'current_month_start' => $currentMonthStart,
            'current_month_end'   => $currentMonthEnd,
            'previous_month_start' => $previousMonthStart,
            'previous_month_end'   => $previousMonthEnd,
        ];
    }

    /**
     * @return array{admin_id:int,dapur_id:int,gudang_id:int}
     */
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
            throw new RuntimeException("RuntimeCurrentMonthSpkScenarioSeeder requires role '{$roleName}' to be seeded.");
        }

        $user = $userModel
            ->where('role_id', $role['id'])
            ->where('is_active', true)
            ->where('deleted_at', null)
            ->first();

        if ($user === null) {
            throw new RuntimeException("RuntimeCurrentMonthSpkScenarioSeeder requires an active user for role '{$roleName}'.");
        }

        return (int) $user['id'];
    }

    /**
     * Seed runtime daily patient prerequisites only up to today.
     *
     * Daily patient input is an operational prerequisite for the requested SPK
     * service date. Seeding future rows makes dashboards and "latest" patient
     * lookups prefer non-operational dates, so keep this runtime scenario bounded
     * by the current day instead of the end of the current month.
     *
     * @return list<string>
     */
    private function seedCurrentMonthDailyPatients(
        DateTimeImmutable $runtimeAnchor,
        DateTimeImmutable $currentMonthStart
    ): array {
        $rows            = [];
        $generationDates = [];
        $cursor          = $currentMonthStart;
        $today           = $runtimeAnchor;

        while ($cursor <= $today) {
            $serviceDate = $cursor->format('Y-m-d');

            $existing = $this->db->table('daily_patients')
                ->where('service_date', $serviceDate)
                ->countAllResults();

            if ($existing > 0) {
                $generationDates[] = $serviceDate;
                $cursor            = $cursor->modify('+2 days');
                continue;
            }

            $rows[] = [
                'service_date'   => $serviceDate,
                'total_patients' => 90 + (int) $runtimeAnchor->format('n') + (((int) $cursor->format('j') - 1) % 5) * 7,
                'notes'          => 'Runtime current-month prerequisite for basah SPK generation.',
            ];

            $generationDates[] = $serviceDate;
            $cursor            = $cursor->modify('+2 days');
        }

        if ($rows !== []) {
            $inserted = $this->db->table('daily_patients')->insertBatch($rows);
            if ($inserted === false) {
                throw new RuntimeException('RuntimeCurrentMonthSpkScenarioSeeder failed to insert current-month daily patient prerequisites.');
            }
        }

        return $generationDates;
    }

    private function seedPreviousMonthApprovedOutTransactions(
        DateTimeImmutable $previousMonthStart,
        int $adminUserId,
        int $gudangUserId
    ): void {
        $approvalStatusModel = new ApprovalStatusModel();
        $transactionTypeModel = new TransactionTypeModel();
        $categoryModel        = new ItemCategoryModel();

        $approvedStatusId = $approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_APPROVED);
        $outTypeId        = $transactionTypeModel->getIdByName(TransactionTypeModel::NAME_OUT);
        $keringCategoryId = $categoryModel->getIdByName(ItemCategoryModel::NAME_KERING);
        $pengemasCategoryId = $categoryModel->getIdByName('PENGEMAS');

        if ($approvedStatusId === null) {
            throw new RuntimeException('RuntimeCurrentMonthSpkScenarioSeeder requires APPROVED approval status to be seeded.');
        }

        if ($outTypeId === null) {
            throw new RuntimeException('RuntimeCurrentMonthSpkScenarioSeeder requires OUT transaction type to be seeded.');
        }

        if ($keringCategoryId === null || $pengemasCategoryId === null) {
            throw new RuntimeException('RuntimeCurrentMonthSpkScenarioSeeder requires KERING and PENGEMAS item categories.');
        }

        $items = $this->db->table('items')
            ->select('id, item_category_id')
            ->where('deleted_at', null)
            ->whereIn('item_category_id', [(int) $keringCategoryId, (int) $pengemasCategoryId])
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if ($items === []) {
            throw new RuntimeException('RuntimeCurrentMonthSpkScenarioSeeder requires active KERING/PENGEMAS items for monthly SPK demand input.');
        }

        $transactionDates = [
            $previousMonthStart->modify('+4 days'),
            $previousMonthStart->modify('+14 days'),
            $previousMonthStart->modify('+24 days'),
        ];

        foreach ($transactionDates as $transactionIndex => $transactionDate) {
            $inserted = $this->db->table('stock_transactions')->insert([
                'type_id'             => (int) $outTypeId,
                'transaction_date'    => $transactionDate->format('Y-m-d'),
                'is_revision'         => false,
                'parent_transaction_id' => null,
                'approval_status_id'  => (int) $approvedStatusId,
                'approved_by'         => $adminUserId,
                'user_id'             => $gudangUserId,
                'spk_id'              => null,
                'reason'              => 'Runtime previous-month OUT demand baseline for monthly SPK generation.',
            ]);

            if ($inserted === false) {
                throw new RuntimeException('RuntimeCurrentMonthSpkScenarioSeeder failed to insert previous-month approved OUT transaction header.');
            }

            $transactionId = (int) $this->db->insertID();
            if ($transactionId <= 0) {
                throw new RuntimeException('RuntimeCurrentMonthSpkScenarioSeeder failed to resolve previous-month approved OUT transaction ID.');
            }

            $detailRows = [];

            foreach ($items as $itemIndex => $item) {
                $categoryMultiplier = (int) $item['item_category_id'] === (int) $keringCategoryId ? 12.0 : 4.0;
                $qty                = $categoryMultiplier + (($itemIndex + $transactionIndex) % 5) * 2.0;

                $detailRows[] = [
                    'transaction_id' => $transactionId,
                    'item_id'        => (int) $item['id'],
                    'qty'            => $qty,
                    'input_qty'      => $qty,
                    'input_unit'     => 'base',
                ];
            }

            $detailsInserted = $this->db->table('stock_transaction_details')->insertBatch($detailRows);
            if ($detailsInserted === false) {
                throw new RuntimeException('RuntimeCurrentMonthSpkScenarioSeeder failed to insert previous-month approved OUT transaction details.');
            }
        }
    }

    /**
     * @param list<string> $generationDates
     */
    private function generateCurrentMonthBasahSpks(array $generationDates, int $dapurUserId): void
    {
        $service = new SpkBasahGenerationService();

        foreach ($generationDates as $serviceDate) {
            $result = $service->generate(['service_date' => $serviceDate], $dapurUserId);

            if (($result['success'] ?? false) !== true) {
                throw new RuntimeException(
                    'RuntimeCurrentMonthSpkScenarioSeeder failed to generate basah SPK for ' . $serviceDate . ': ' . $this->formatFailure($result)
                );
            }
        }
    }

    private function generateCurrentMonthKeringPengemasSpk(string $currentMonth, int $dapurUserId): void
    {
        $service = new SpkKeringPengemasGenerationService();
        $result  = $service->generate(['target_month' => $currentMonth], $dapurUserId);

        if (($result['success'] ?? false) !== true) {
            throw new RuntimeException(
                'RuntimeCurrentMonthSpkScenarioSeeder failed to generate current-month kering/pengemas SPK: ' . $this->formatFailure($result)
            );
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function formatFailure(array $result): string
    {
        $message = (string) ($result['message'] ?? 'Unknown error.');
        $errors  = $result['errors'] ?? [];

        if (! is_array($errors) || $errors === []) {
            return $message;
        }

        $encodedErrors = json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encodedErrors === false ? $message : $message . ' ' . $encodedErrors;
    }
}
