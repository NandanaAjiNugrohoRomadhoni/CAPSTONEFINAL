<?php

namespace App\Database\Seeds;

use App\Models\RoleModel;
use App\Models\UserModel;
use CodeIgniter\Database\Seeder;
use RuntimeException;

class StockOpnameSeeder extends Seeder
{
    public function run(): void
    {
        $roleModel = new RoleModel();
        $userModel = new UserModel();

        $gudangRole = $roleModel->findByName('gudang');
        $adminRole  = $roleModel->findByName('admin');

        if ($gudangRole === null || $adminRole === null) {
            throw new RuntimeException('StockOpnameSeeder requires admin and gudang roles.');
        }

        $gudangUser = $userModel
            ->where('role_id', $gudangRole['id'])
            ->where('deleted_at', null)
            ->first();

        $adminUser = $userModel
            ->where('role_id', $adminRole['id'])
            ->where('deleted_at', null)
            ->first();

        if ($gudangUser === null || $adminUser === null) {
            throw new RuntimeException('StockOpnameSeeder requires active admin and gudang users.');
        }

        $items = $this->db->table('items')
            ->select('id, qty')
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if (count($items) < 2) {
            throw new RuntimeException('StockOpnameSeeder requires at least two seeded items.');
        }

        $opnameInserted = $this->db->table('stock_opnames')->insert([
            'opname_date'   => '2026-04-17',
            'state'         => 'POSTED',
            'notes'         => 'Baseline posted stock opname for end-to-end flow coverage.',
            'created_by'    => (int) $gudangUser['id'],
            'submitted_by'  => (int) $gudangUser['id'],
            'submitted_at'  => '2026-04-17 09:00:00',
            'approved_by'   => (int) $adminUser['id'],
            'approved_at'   => '2026-04-17 09:20:00',
            'rejected_by'   => null,
            'rejected_at'   => null,
            'rejection_reason' => null,
            'posted_by'     => (int) $adminUser['id'],
            'posted_at'     => '2026-04-17 09:35:00',
            'created_at'    => '2026-04-17 08:45:00',
            'updated_at'    => '2026-04-17 09:35:00',
            'deleted_at'    => null,
        ]);

        if ($opnameInserted === false) {
            throw new RuntimeException('StockOpnameSeeder failed to insert stock opname header.');
        }

        $stockOpnameId = (int) $this->db->insertID();
        if ($stockOpnameId <= 0) {
            throw new RuntimeException('StockOpnameSeeder failed to resolve stock opname ID.');
        }

        $draftInserted = $this->db->table('stock_opnames')->insert([
            'opname_date'   => '2026-04-18',
            'state'         => 'DRAFT',
            'notes'         => 'Draft stock opname baseline.',
            'created_by'    => (int) $gudangUser['id'],
            'submitted_by'  => null,
            'submitted_at'  => null,
            'approved_by'   => null,
            'approved_at'   => null,
            'rejected_by'   => null,
            'rejected_at'   => null,
            'rejection_reason' => null,
            'posted_by'     => null,
            'posted_at'     => null,
            'created_at'    => '2026-04-18 08:45:00',
            'updated_at'    => '2026-04-18 08:45:00',
            'deleted_at'    => null,
        ]);

        if ($draftInserted === false) {
            throw new RuntimeException('StockOpnameSeeder failed to insert DRAFT stock opname header.');
        }

        $draftOpnameId = (int) $this->db->insertID();

        $submittedInserted = $this->db->table('stock_opnames')->insert([
            'opname_date'   => '2026-04-19',
            'state'         => 'SUBMITTED',
            'notes'         => 'Submitted stock opname baseline.',
            'created_by'    => (int) $gudangUser['id'],
            'submitted_by'  => (int) $gudangUser['id'],
            'submitted_at'  => '2026-04-19 09:00:00',
            'approved_by'   => null,
            'approved_at'   => null,
            'rejected_by'   => null,
            'rejected_at'   => null,
            'rejection_reason' => null,
            'posted_by'     => null,
            'posted_at'     => null,
            'created_at'    => '2026-04-19 08:40:00',
            'updated_at'    => '2026-04-19 09:00:00',
            'deleted_at'    => null,
        ]);

        if ($submittedInserted === false) {
            throw new RuntimeException('StockOpnameSeeder failed to insert SUBMITTED stock opname header.');
        }

        $submittedOpnameId = (int) $this->db->insertID();

        $approvedInserted = $this->db->table('stock_opnames')->insert([
            'opname_date'   => '2026-04-20',
            'state'         => 'APPROVED',
            'notes'         => 'Approved stock opname baseline.',
            'created_by'    => (int) $gudangUser['id'],
            'submitted_by'  => (int) $gudangUser['id'],
            'submitted_at'  => '2026-04-20 09:00:00',
            'approved_by'   => (int) $adminUser['id'],
            'approved_at'   => '2026-04-20 09:30:00',
            'rejected_by'   => null,
            'rejected_at'   => null,
            'rejection_reason' => null,
            'posted_by'     => null,
            'posted_at'     => null,
            'created_at'    => '2026-04-20 08:35:00',
            'updated_at'    => '2026-04-20 09:30:00',
            'deleted_at'    => null,
        ]);

        if ($approvedInserted === false) {
            throw new RuntimeException('StockOpnameSeeder failed to insert APPROVED stock opname header.');
        }

        $approvedOpnameId = (int) $this->db->insertID();

        $rejectedInserted = $this->db->table('stock_opnames')->insert([
            'opname_date'   => '2026-04-21',
            'state'         => 'REJECTED',
            'notes'         => 'Rejected stock opname baseline.',
            'created_by'    => (int) $gudangUser['id'],
            'submitted_by'  => (int) $gudangUser['id'],
            'submitted_at'  => '2026-04-21 09:00:00',
            'approved_by'   => null,
            'approved_at'   => null,
            'rejected_by'   => (int) $adminUser['id'],
            'rejected_at'   => '2026-04-21 09:25:00',
            'rejection_reason' => 'Count variance requires recount confirmation.',
            'posted_by'     => null,
            'posted_at'     => null,
            'created_at'    => '2026-04-21 08:32:00',
            'updated_at'    => '2026-04-21 09:25:00',
            'deleted_at'    => null,
        ]);

        if ($rejectedInserted === false) {
            throw new RuntimeException('StockOpnameSeeder failed to insert REJECTED stock opname header.');
        }

        $rejectedOpnameId = (int) $this->db->insertID();

        $firstItem = $items[0];
        $secondItem = $items[1];

        // Keep deterministic non-zero variance fixtures for POSTED rows.
        // Baseline item qty can be 0.00 in fresh seeds, so we enforce a stable
        // minimum system snapshot to preserve negative/positive delta coverage
        // required by unified OPNAME_ADJUSTMENT + historical backfill semantics.
        $firstSystemQty = max(round((float) $firstItem['qty'], 2), 150.00);
        $secondSystemQty = max(round((float) $secondItem['qty'], 2), 200.00);

        $firstVariance = -120.0;
        $secondVariance = 80.0;

        $firstCounted = $firstSystemQty + $firstVariance;
        $secondCounted = $secondSystemQty + $secondVariance;

        $this->db->table('stock_opname_details')->insertBatch([
            [
                'stock_opname_id' => $stockOpnameId,
                'item_id'         => (int) $firstItem['id'],
                'system_qty'      => number_format($firstSystemQty, 2, '.', ''),
                'counted_qty'     => number_format($firstCounted, 2, '.', ''),
                'variance_qty'    => number_format($firstCounted - $firstSystemQty, 2, '.', ''),
            ],
            [
                'stock_opname_id' => $stockOpnameId,
                'item_id'         => (int) $secondItem['id'],
                'system_qty'      => number_format($secondSystemQty, 2, '.', ''),
                'counted_qty'     => number_format($secondCounted, 2, '.', ''),
                'variance_qty'    => number_format($secondCounted - $secondSystemQty, 2, '.', ''),
            ],
            [
                'stock_opname_id' => $draftOpnameId,
                'item_id'         => (int) $firstItem['id'],
                'system_qty'      => number_format($firstSystemQty, 2, '.', ''),
                'counted_qty'     => number_format($firstSystemQty - 30.00, 2, '.', ''),
                'variance_qty'    => number_format(-30.00, 2, '.', ''),
            ],
            [
                'stock_opname_id' => $submittedOpnameId,
                'item_id'         => (int) $secondItem['id'],
                'system_qty'      => number_format($secondSystemQty, 2, '.', ''),
                'counted_qty'     => number_format($secondSystemQty + 40.00, 2, '.', ''),
                'variance_qty'    => number_format(40.00, 2, '.', ''),
            ],
            [
                'stock_opname_id' => $approvedOpnameId,
                'item_id'         => (int) $firstItem['id'],
                'system_qty'      => number_format($firstSystemQty, 2, '.', ''),
                'counted_qty'     => number_format($firstSystemQty + 15.00, 2, '.', ''),
                'variance_qty'    => number_format(15.00, 2, '.', ''),
            ],
            [
                'stock_opname_id' => $rejectedOpnameId,
                'item_id'         => (int) $secondItem['id'],
                'system_qty'      => number_format($secondSystemQty, 2, '.', ''),
                'counted_qty'     => number_format($secondSystemQty - 25.00, 2, '.', ''),
                'variance_qty'    => number_format(-25.00, 2, '.', ''),
            ],
        ]);
    }
}
