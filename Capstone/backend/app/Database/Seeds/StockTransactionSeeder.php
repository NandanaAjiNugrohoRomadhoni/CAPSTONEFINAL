<?php

namespace App\Database\Seeds;

use App\Models\ItemCategoryModel;
use App\Models\RoleModel;
use App\Models\TransactionTypeModel;
use App\Models\UserModel;
use CodeIgniter\Database\Seeder;
use RuntimeException;

class StockTransactionSeeder extends Seeder
{
    private const IN_QTY_FACTOR = 10; // multiplier above current stock for IN restock

    public function run(): void
    {
        $typeModel = new TransactionTypeModel();
        $itemCategoryModel = new ItemCategoryModel();
        $userModel = new UserModel();
        $roleModel = new RoleModel();

        $inTypeId = $typeModel->getIdByName(TransactionTypeModel::NAME_IN);
        $outTypeId = $typeModel->getIdByName(TransactionTypeModel::NAME_OUT);
        if ($inTypeId === null || $outTypeId === null) {
            throw new RuntimeException('StockTransactionSeeder requires IN and OUT transaction types to be seeded.');
        }

        $gudangUser = $this->resolveUserByRole($roleModel, $userModel, 'gudang');
        $adminUser = $this->resolveUserByRole($roleModel, $userModel, 'admin');

        $keringCatId = $itemCategoryModel->getIdByName(ItemCategoryModel::NAME_KERING);
        if ($keringCatId === null) {
            throw new RuntimeException('StockTransactionSeeder requires KERING item category to be seeded.');
        }

        $keringItems = $this->db
            ->table('items')
            ->select('id, name, qty')
            ->where('item_category_id', $keringCatId)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if ($keringItems === []) {
            throw new RuntimeException('StockTransactionSeeder requires KERING items to be seeded.');
        }

        $today = new \DateTimeImmutable('now');
        $monthStart = $today->modify('first day of this month');
        $daysInMonth = (int) $today->format('j');
        $monthEnd = $today;

        // --- 1 IN transaction at start of month: restock all kering items ---
        $inInserted = $this->db->table('stock_transactions')->insert([
            'type_id' => $inTypeId,
            'transaction_date' => $monthStart->format('Y-m-d'),
            'is_revision' => false,
            'parent_transaction_id' => null,
            'approval_status_id' => 1,
            'approved_by' => (int) $adminUser['id'],
            'user_id' => (int) $gudangUser['id'],
            'spk_id' => null,
            'reason' => 'Monthly kering restock — ' . $monthStart->format('Y-m'),
        ]);
        if ($inInserted === false) {
            throw new RuntimeException('StockTransactionSeeder failed to insert IN transaction header.');
        }
        $inTransactionId = (int) $this->db->insertID();

        // --- OUT transactions: one per kering item spread across month ---
        $itemCount = count($keringItems);
        $details = [];

        foreach ($keringItems as $index => $item) {
            $itemId = (int) $item['id'];
            $currentQty = (float) ($item['qty'] ?? 0);

            // IN detail: restock to qty * factor
            $inQty = $currentQty * self::IN_QTY_FACTOR;
            $details[] = [
                'transaction_id' => (int) $inTransactionId,
                'item_id' => $itemId,
                'qty' => $inQty,
                'input_qty' => $inQty,
                'input_unit' => 'base',
            ];

            // OUT detail: spread across days, consume a portion of stock
            $outDay = ($index % $daysInMonth) + 1;
            $outDate = $monthStart->modify(($outDay - 1) . ' days');

            // Skip if outDate is in the future (shouldn't happen since daysInMonth = today's day)
            if ($outDate > $monthEnd) {
                continue;
            }

            $outQty = max(1, round($currentQty * 0.3, 2));

            $outInserted = $this->db->table('stock_transactions')->insert([
                'type_id' => $outTypeId,
                'transaction_date' => $outDate->format('Y-m-d'),
                'is_revision' => false,
                'parent_transaction_id' => null,
                'approval_status_id' => 1,
                'approved_by' => (int) $adminUser['id'],
                'user_id' => (int) $gudangUser['id'],
                'spk_id' => null,
                'reason' => 'Kering usage: ' . $item['name'],
            ]);
            if ($outInserted === false) {
                throw new RuntimeException('StockTransactionSeeder failed to insert OUT transaction header for item ' . $item['name']);
            }
            $outTransactionId = (int) $this->db->insertID();

            $details[] = [
                'transaction_id' => (int) $outTransactionId,
                'item_id' => $itemId,
                'qty' => $outQty,
                'input_qty' => $outQty,
                'input_unit' => 'base',
            ];
        }

        if ($details !== []) {
            $inserted = $this->db->table('stock_transaction_details')->insertBatch($details);
            if ($inserted === false) {
                throw new RuntimeException('StockTransactionSeeder failed to insert stock transaction detail rows.');
            }
        }
    }

    private function resolveUserByRole(RoleModel $roleModel, UserModel $userModel, string $roleName): array
    {
        $role = $roleModel->findByName($roleName);
        if ($role === null) {
            throw new RuntimeException("StockTransactionSeeder requires '{$roleName}' role to be seeded.");
        }

        $user = $userModel
            ->where('role_id', $role['id'])
            ->where('deleted_at', null)
            ->first();

        if ($user === null) {
            throw new RuntimeException("StockTransactionSeeder requires an active user for role '{$roleName}'.");
        }

        return $user;
    }
}
