<?php

namespace App\Database\Seeds;

use App\Models\ApprovalStatusModel;
use App\Models\ItemModel;
use App\Models\RoleModel;
use App\Models\TransactionTypeModel;
use App\Models\UserModel;
use CodeIgniter\Database\Seeder;
use RuntimeException;

class StockTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $typeModel = new TransactionTypeModel();
        $statusModel = new ApprovalStatusModel();
        $itemModel = new ItemModel();
        $userModel = new UserModel();
        $roleModel = new RoleModel();

        $requiredTypeIds = [
            TransactionTypeModel::NAME_IN => $typeModel->getIdByName(TransactionTypeModel::NAME_IN),
            TransactionTypeModel::NAME_OUT => $typeModel->getIdByName(TransactionTypeModel::NAME_OUT),
            TransactionTypeModel::NAME_RETURN_IN => $typeModel->getIdByName(TransactionTypeModel::NAME_RETURN_IN),
            TransactionTypeModel::NAME_OPNAME_ADJUSTMENT => $typeModel->getIdByName(TransactionTypeModel::NAME_OPNAME_ADJUSTMENT),
        ];

        foreach ($requiredTypeIds as $name => $id) {
            if ($id === null) {
                throw new RuntimeException("StockTransactionSeeder requires transaction type '{$name}' to be seeded and active.");
            }
        }

        $inTypeId = (int) $requiredTypeIds[TransactionTypeModel::NAME_IN];
        $outTypeId = (int) $requiredTypeIds[TransactionTypeModel::NAME_OUT];
        $returnInTypeId = (int) $requiredTypeIds[TransactionTypeModel::NAME_RETURN_IN];
        $opnameAdjustmentTypeId = (int) $requiredTypeIds[TransactionTypeModel::NAME_OPNAME_ADJUSTMENT];

        $requiredStatusIds = [
            ApprovalStatusModel::NAME_APPROVED => $statusModel->getIdByName(ApprovalStatusModel::NAME_APPROVED),
            ApprovalStatusModel::NAME_PENDING => $statusModel->getIdByName(ApprovalStatusModel::NAME_PENDING),
            ApprovalStatusModel::NAME_REJECTED => $statusModel->getIdByName(ApprovalStatusModel::NAME_REJECTED),
        ];

        foreach ($requiredStatusIds as $name => $id) {
            if ($id === null) {
                throw new RuntimeException("StockTransactionSeeder requires approval status '{$name}' to be seeded and active.");
            }
        }

        $approvedStatusId = (int) $requiredStatusIds[ApprovalStatusModel::NAME_APPROVED];
        $pendingStatusId = (int) $requiredStatusIds[ApprovalStatusModel::NAME_PENDING];
        $rejectedStatusId = (int) $requiredStatusIds[ApprovalStatusModel::NAME_REJECTED];

        $gudangRole = $roleModel->findByName('gudang');
        if ($gudangRole === null) {
            throw new RuntimeException('StockTransactionSeeder requires gudang role to be seeded.');
        }

        $gudangUser = $userModel
            ->where('role_id', $gudangRole['id'])
            ->where('deleted_at', null)
            ->first();

        $adminRole = $roleModel->findByName('admin');
        if ($adminRole === null) {
            throw new RuntimeException('StockTransactionSeeder requires admin role to be seeded.');
        }

        $adminUser = $userModel
            ->where('role_id', $adminRole['id'])
            ->where('deleted_at', null)
            ->first();

        if ($gudangUser === null || $adminUser === null) {
            throw new RuntimeException('StockTransactionSeeder requires active admin and gudang users.');
        }

        $rows = $itemModel
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->findAll();

        if ($rows === []) {
            throw new RuntimeException('StockTransactionSeeder requires seeded items.');
        }

        $inserted = $this->db->table('stock_transactions')->insert([
            'type_id' => $outTypeId,
            'transaction_date' => '2026-03-20',
            'is_revision' => false,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedStatusId,
            'approved_by' => (int) $adminUser['id'],
            'user_id' => (int) $gudangUser['id'],
            'spk_id' => null,
            'reason' => 'Baseline OUT usage for monthly SPK kering/pengemas reference.',
        ]);

        if ($inserted === false) {
            throw new RuntimeException('StockTransactionSeeder failed to insert stock transaction header.');
        }

        $transactionId = (int) $this->db->insertID();
        if ($transactionId <= 0) {
            throw new RuntimeException('StockTransactionSeeder failed to resolve inserted transaction ID.');
        }

        $inInserted = $this->db->table('stock_transactions')->insert([
            'type_id' => $inTypeId,
            'transaction_date' => '2026-03-22',
            'is_revision' => false,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedStatusId,
            'approved_by' => (int) $adminUser['id'],
            'user_id' => (int) $gudangUser['id'],
            'spk_id' => null,
            'reason' => 'Baseline IN replenishment.',
        ]);
        if ($inInserted === false) {
            throw new RuntimeException('StockTransactionSeeder failed to insert IN transaction header.');
        }
        $inTransactionId = (int) $this->db->insertID();

        $returnInInserted = $this->db->table('stock_transactions')->insert([
            'type_id' => $returnInTypeId,
            'transaction_date' => '2026-03-23',
            'is_revision' => false,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedStatusId,
            'approved_by' => (int) $adminUser['id'],
            'user_id' => (int) $gudangUser['id'],
            'spk_id' => null,
            'reason' => 'Baseline RETURN_IN from kitchen return flow.',
        ]);
        if ($returnInInserted === false) {
            throw new RuntimeException('StockTransactionSeeder failed to insert RETURN_IN transaction header.');
        }
        $returnInTransactionId = (int) $this->db->insertID();

        $opnameAdjustmentInserted = $this->db->table('stock_transactions')->insert([
            'type_id' => $opnameAdjustmentTypeId,
            'transaction_date' => '2026-03-27',
            'is_revision' => false,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedStatusId,
            'approved_by' => (int) $adminUser['id'],
            'user_id' => (int) $gudangUser['id'],
            'spk_id' => null,
            'reason' => 'Baseline OPNAME_ADJUSTMENT sample for unified opname posting compatibility.',
        ]);
        if ($opnameAdjustmentInserted === false) {
            throw new RuntimeException('StockTransactionSeeder failed to insert OPNAME_ADJUSTMENT transaction header.');
        }
        $opnameAdjustmentTransactionId = (int) $this->db->insertID();

        $pendingRevisionInserted = $this->db->table('stock_transactions')->insert([
            'type_id' => $outTypeId,
            'transaction_date' => '2026-03-24',
            'is_revision' => true,
            'parent_transaction_id' => $transactionId,
            'approval_status_id' => $pendingStatusId,
            'approved_by' => null,
            'user_id' => (int) $gudangUser['id'],
            'spk_id' => null,
            'reason' => 'Pending revision sample for approval workflow baseline.',
        ]);
        if ($pendingRevisionInserted === false) {
            throw new RuntimeException('StockTransactionSeeder failed to insert pending revision transaction header.');
        }
        $pendingRevisionId = (int) $this->db->insertID();

        $rejectedRevisionInserted = $this->db->table('stock_transactions')->insert([
            'type_id' => $outTypeId,
            'transaction_date' => '2026-03-25',
            'is_revision' => true,
            'parent_transaction_id' => $transactionId,
            'approval_status_id' => $rejectedStatusId,
            'approved_by' => (int) $adminUser['id'],
            'user_id' => (int) $gudangUser['id'],
            'spk_id' => null,
            'reason' => 'Rejected revision sample for approval workflow baseline.',
        ]);
        if ($rejectedRevisionInserted === false) {
            throw new RuntimeException('StockTransactionSeeder failed to insert rejected revision transaction header.');
        }
        $rejectedRevisionId = (int) $this->db->insertID();

        $directCorrectionInserted = $this->db->table('stock_transactions')->insert([
            'type_id' => $inTypeId,
            'transaction_date' => '2026-03-26',
            'is_revision' => false,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedStatusId,
            'approved_by' => (int) $adminUser['id'],
            'user_id' => (int) $adminUser['id'],
            'spk_id' => null,
            'reason' => 'Admin direct correction baseline row.',
        ]);
        if ($directCorrectionInserted === false) {
            throw new RuntimeException('StockTransactionSeeder failed to insert direct correction transaction header.');
        }
        $directCorrectionId = (int) $this->db->insertID();

        $details = [];
        foreach ($rows as $row) {
            $itemId = (int) $row['id'];
            $qty = match ((string) $row['name']) {
                'Beras' => 6000.00,
                'Ayam' => 4500.00,
                'Minyak Goreng' => 3000.00,
                'Telur' => 90.00,
                default => 250.00,
            };

            $details[] = [
                'transaction_id' => (int) $transactionId,
                'item_id' => $itemId,
                'qty' => $qty,
                'input_qty' => $qty,
                'input_unit' => 'base',
            ];

            if ($itemId === (int) $rows[0]['id']) {
                $details[] = [
                    'transaction_id' => (int) $inTransactionId,
                    'item_id' => $itemId,
                    'qty' => 1200.00,
                    'input_qty' => 1200.00,
                    'input_unit' => 'base',
                ];

                $details[] = [
                    'transaction_id' => (int) $returnInTransactionId,
                    'item_id' => $itemId,
                    'qty' => 400.00,
                    'input_qty' => 400.00,
                    'input_unit' => 'base',
                ];

                $details[] = [
                    'transaction_id' => (int) $opnameAdjustmentTransactionId,
                    'item_id' => $itemId,
                    'qty' => 150.00,
                    'input_qty' => 150.00,
                    'input_unit' => 'base',
                ];

                $details[] = [
                    'transaction_id' => (int) $pendingRevisionId,
                    'item_id' => $itemId,
                    'qty' => 5000.00,
                    'input_qty' => 5000.00,
                    'input_unit' => 'base',
                ];

                $details[] = [
                    'transaction_id' => (int) $rejectedRevisionId,
                    'item_id' => $itemId,
                    'qty' => 5200.00,
                    'input_qty' => 5200.00,
                    'input_unit' => 'base',
                ];

                $details[] = [
                    'transaction_id' => (int) $directCorrectionId,
                    'item_id' => $itemId,
                    'qty' => 300.00,
                    'input_qty' => 300.00,
                    'input_unit' => 'base',
                ];
            }
        }

        $detailsInserted = $this->db->table('stock_transaction_details')->insertBatch($details);
        if ($detailsInserted === false) {
            throw new RuntimeException('StockTransactionSeeder failed to insert stock transaction detail rows.');
        }
    }
}
