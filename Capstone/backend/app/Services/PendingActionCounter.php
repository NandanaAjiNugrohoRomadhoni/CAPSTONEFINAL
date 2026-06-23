<?php

namespace App\Services;

use App\Models\ApprovalStatusModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * PendingActionCounter — cross-table pending action counts for dashboard.
 *
 * Kept as a dedicated class to avoid bloating DashboardAggregateService.
 * Each build method returns a flat `pending_actions` array keyed by role.
 * Unread notification count is delegated to NotificationService::countUnread()
 * rather than embedding raw SQL here.
 */
class PendingActionCounter
{
    protected BaseConnection $db;
    protected NotificationService $notificationService;
    protected ApprovalStatusModel $approvalStatusModel;

    public function __construct()
    {
        $this->db                  = Database::connect();
        $this->notificationService = new NotificationService();
        $this->approvalStatusModel = new ApprovalStatusModel();
    }

    /**
     * Pending actions for admin role.
     *
     * Counts:
     *  - stock_opnames pending approval (state = SUBMITTED)
     *  - transaction revisions pending approval (is_revision=1, approval_status = PENDING)
     *  - SPKs generated but not yet posted (is_finish=0, is_latest=1)
     *  - unread notifications
     */
    public function buildForAdmin(int $userId): array
    {
        $pendingStatusId = $this->approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_PENDING);

        $opnamesPending = (int) $this->db
            ->table('stock_opnames')
            ->where('state', 'SUBMITTED')
            ->where('deleted_at', null)
            ->countAllResults();

        $revisionsPending = 0;
        if ($pendingStatusId !== null) {
            $revisionsPending = (int) $this->db
                ->table('stock_transactions st')
                ->join('approval_statuses ast', 'ast.id = st.approval_status_id', 'inner')
                ->where('st.is_revision', 1)
                ->where('ast.name', ApprovalStatusModel::NAME_PENDING)
                ->where('st.deleted_at', null)
                ->countAllResults();
        }

        $spksReady = (int) $this->db
            ->table('spk_calculations')
            ->where('is_finish', 0)
            ->where('is_latest', 1)
            ->countAllResults();

        $unread = $this->notificationService->countUnread($userId);

        $total = $opnamesPending + $revisionsPending + $spksReady + $unread;

        return [
            'total'                                  => $total,
            'stock_opnames_pending_approval'         => $opnamesPending,
            'transaction_revisions_pending_approval' => $revisionsPending,
            'spks_ready_to_post'                     => $spksReady,
            'unread_notifications'                   => $unread,
        ];
    }

    /**
     * Pending actions for gudang role.
     *
     * Counts:
     *  - stock_opnames in DRAFT created by this user (pending submit)
     *  - SPKs generated but not yet posted (is_finish=0, is_latest=1)
     *  - unread notifications
     */
    public function buildForGudang(int $userId): array
    {
        $opnamesDraft = (int) $this->db
            ->table('stock_opnames')
            ->where('state', 'DRAFT')
            ->where('created_by', $userId)
            ->where('deleted_at', null)
            ->countAllResults();

        $spksReady = (int) $this->db
            ->table('spk_calculations')
            ->where('is_finish', 0)
            ->where('is_latest', 1)
            ->countAllResults();

        $unread = $this->notificationService->countUnread($userId);

        $total = $opnamesDraft + $spksReady + $unread;

        return [
            'total'                        => $total,
            'stock_opnames_pending_submit' => $opnamesDraft,
            'spks_ready_to_post'           => $spksReady,
            'unread_notifications'         => $unread,
        ];
    }

    /**
     * Pending actions for dapur role.
     *
     * Counts:
     *  - SPKs basah that are ready to generate (no is_latest=1 exists yet for today)
     *  - unread notifications
     *
     * "Ready to generate" = no current-period SPK with is_latest=1 exists for today's date window.
     * Approximation: count SPKs where is_latest=1 and is_finish=0 and spk_type='basah'.
     * If none exist → 1 SPK is ready to generate, else 0.
     */
    public function buildForDapur(int $userId): array
    {
        $pendingSpkBasah = (int) $this->db
            ->table('spk_calculations')
            ->where('spk_type', 'basah')
            ->where('is_finish', 0)
            ->where('is_latest', 1)
            ->countAllResults();

        // "ready to generate" means there is no latest unposted basah SPK
        $spksReadyToGenerate = $pendingSpkBasah === 0 ? 1 : 0;

        $unread = $this->notificationService->countUnread($userId);

        $total = $spksReadyToGenerate + $unread;

        return [
            'total'                  => $total,
            'spks_ready_to_generate' => $spksReadyToGenerate,
            'unread_notifications'   => $unread,
        ];
    }
}
