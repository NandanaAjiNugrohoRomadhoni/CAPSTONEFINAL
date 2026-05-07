<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\SpkCalculationModel;
use App\Models\SpkRecommendationModel;
use App\Services\MenuScheduleManagementService;
use App\Services\SpkKeringPengemasGenerationService;
use App\Services\SpkOverrideService;
use App\Services\SpkStockPostingService;
use CodeIgniter\HTTP\ResponseInterface;

class SpkKeringPengemas extends BaseController
{
    protected MenuScheduleManagementService $menuScheduleService;
    protected SpkKeringPengemasGenerationService $spkKeringPengemasGenerationService;
    protected SpkOverrideService $spkOverrideService;
    protected SpkCalculationModel $spkCalculationModel;
    protected SpkRecommendationModel $spkRecommendationModel;
    protected SpkStockPostingService $spkStockPostingService;

    public function __construct()
    {
        $this->menuScheduleService               = new MenuScheduleManagementService();
        $this->spkKeringPengemasGenerationService = new SpkKeringPengemasGenerationService();
        $this->spkOverrideService                = new SpkOverrideService();
        $this->spkCalculationModel               = new SpkCalculationModel();
        $this->spkRecommendationModel            = new SpkRecommendationModel();
        $this->spkStockPostingService            = new SpkStockPostingService();
    }

    public function menuCalendarProjection(): ResponseInterface
    {
        $result = $this->menuScheduleService->resolveCalendar($this->request->getGet());

        if (! $result['success']) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        $payload = [
            'data' => $result['data'],
        ];

        if (isset($result['meta'])) {
            $payload['meta'] = $result['meta'];
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON($payload);
    }

    public function generate(): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthorized.',
                ]);
        }

        $result = $this->spkKeringPengemasGenerationService->generate(
            $this->request->getJSON(true) ?? [],
            (int) $user->id
        );

        if (! $result['success']) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'message' => 'SPK kering/pengemas generated successfully.',
                'data'    => $result['data'],
            ]);
    }

    public function history(): ResponseInterface
    {
        $rows = $this->spkCalculationModel
            ->select('spk_calculations.id, spk_calculations.version, spk_calculations.scope_key, spk_calculations.is_latest, spk_calculations.calculation_scope, spk_calculations.calculation_date, spk_calculations.target_date_start, spk_calculations.target_date_end, spk_calculations.target_month, spk_calculations.estimated_patients, spk_calculations.is_finish, spk_calculations.created_at, users.id AS user_id, users.name AS user_name, users.username AS user_username, item_categories.id AS category_id, item_categories.name AS category_name')
            ->join('users', 'users.id = spk_calculations.user_id', 'left')
            ->join('item_categories', 'item_categories.id = spk_calculations.category_id', 'left')
            ->where('spk_calculations.spk_type', SpkCalculationModel::TYPE_KERING_PENGEMAS)
            ->orderBy('spk_calculations.calculation_date', 'DESC')
            ->orderBy('spk_calculations.id', 'DESC')
            ->findAll();

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => array_map(static function (array $row): array {
                    return [
                        'id'                 => (int) $row['id'],
                        'version'            => (int) $row['version'],
                        'scope_key'          => (string) $row['scope_key'],
                        'is_latest'          => (bool) $row['is_latest'],
                        'calculation_scope'  => (string) $row['calculation_scope'],
                        'calculation_date'   => $row['calculation_date'],
                        'target_date_start'  => $row['target_date_start'],
                        'target_date_end'    => $row['target_date_end'],
                        'target_month'       => $row['target_month'],
                        'estimated_patients' => (int) $row['estimated_patients'],
                        'is_finish'          => (bool) $row['is_finish'],
                        'created_at'         => $row['created_at'],
                        'user'               => [
                            'id'       => isset($row['user_id']) ? (int) $row['user_id'] : null,
                            'name'     => $row['user_name'] ?? null,
                            'username' => $row['user_username'] ?? null,
                        ],
                        'category'           => [
                            'id'   => isset($row['category_id']) ? (int) $row['category_id'] : null,
                            'name' => $row['category_name'] ?? null,
                        ],
                    ];
                }, $rows),
                'meta' => [
                    'total' => count($rows),
                ],
            ]);
    }

    public function show(int $id): ResponseInterface
    {
        $header = $this->spkCalculationModel
            ->select('spk_calculations.*, users.name AS user_name, users.username AS user_username, item_categories.name AS category_name')
            ->join('users', 'users.id = spk_calculations.user_id', 'left')
            ->join('item_categories', 'item_categories.id = spk_calculations.category_id', 'left')
            ->where('spk_calculations.id', $id)
            ->where('spk_calculations.spk_type', SpkCalculationModel::TYPE_KERING_PENGEMAS)
            ->first();

        if ($header === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'message' => 'SPK kering/pengemas history not found.',
                ]);
        }

        $details = $this->spkRecommendationModel
            ->select('spk_recommendations.*, items.name AS item_name, items.unit_base AS item_unit_base, items.unit_convert AS item_unit_convert')
            ->join('items', 'items.id = spk_recommendations.item_id', 'left')
            ->where('spk_recommendations.spk_id', $id)
            ->orderBy('spk_recommendations.item_id', 'ASC')
            ->findAll();

        $normalizedDetails = array_map(static function (array $row): array {
            return [
                'id'                     => (int) $row['id'],
                'item_id'                => (int) $row['item_id'],
                'item_name'              => $row['item_name'] ?? null,
                'item_unit_base'         => $row['item_unit_base'] ?? null,
                'item_unit_convert'      => $row['item_unit_convert'] ?? null,
                'target_date'            => $row['target_date'],
                'current_stock_qty'      => (float) $row['current_stock_qty'],
                'required_qty'           => (float) $row['required_qty'],
                'system_recommended_qty' => (float) $row['system_recommended_qty'],
                'final_recommended_qty'  => (float) $row['recommended_qty'],
                'override'               => [
                    'is_overridden' => (bool) $row['is_overridden'],
                    'reason'        => $row['override_reason'],
                    'overridden_by' => isset($row['overridden_by']) ? (int) $row['overridden_by'] : null,
                    'overridden_at' => $row['overridden_at'],
                ],
            ];
        }, $details);

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => [
                    'id'                 => (int) $header['id'],
                    'version'            => (int) $header['version'],
                    'scope_key'          => (string) $header['scope_key'],
                    'is_latest'          => (bool) $header['is_latest'],
                    'spk_type'           => (string) $header['spk_type'],
                    'calculation_scope'  => (string) $header['calculation_scope'],
                    'calculation_date'   => $header['calculation_date'],
                    'target_date_start'  => $header['target_date_start'],
                    'target_date_end'    => $header['target_date_end'],
                    'target_month'       => $header['target_month'],
                    'estimated_patients' => (int) $header['estimated_patients'],
                    'is_finish'          => (bool) $header['is_finish'],
                    'created_at'         => $header['created_at'],
                    'updated_at'         => $header['updated_at'],
                    'user'               => [
                        'id'       => (int) $header['user_id'],
                        'name'     => $header['user_name'] ?? null,
                        'username' => $header['user_username'] ?? null,
                    ],
                    'category'           => [
                        'id'   => (int) $header['category_id'],
                        'name' => $header['category_name'] ?? null,
                    ],
                    'items'              => $normalizedDetails,
                    'print_ready'        => [
                        'spk_id'             => (int) $header['id'],
                        'spk_type'           => (string) $header['spk_type'],
                        'version'            => (int) $header['version'],
                        'calculation_date'   => $header['calculation_date'],
                        'target_date_start'  => $header['target_date_start'],
                        'target_date_end'    => $header['target_date_end'],
                        'target_month'       => $header['target_month'],
                        'estimated_patients' => (int) $header['estimated_patients'],
                        'category_name'      => $header['category_name'] ?? null,
                        'generated_by'       => $header['user_name'] ?? $header['user_username'] ?? null,
                        'recommendations'    => $normalizedDetails,
                    ],
                ],
            ]);
    }

    public function postStock(int $id): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthorized.',
                ]);
        }

        $result = $this->spkStockPostingService->post(
            $id,
            SpkCalculationModel::TYPE_KERING_PENGEMAS,
            (int) $user->id,
            $this->request->getIPAddress()
        );

        if (! $result['success']) {
            return $this->response
                ->setStatusCode((int) ($result['status_code'] ?? 400))
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message' => 'SPK posted to stock transaction successfully.',
                'data'    => $result['data'],
            ]);
    }

    public function overrideItem(int $id): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthorized.',
                ]);
        }

        $result = $this->spkOverrideService->overrideItem(
            $id,
            SpkCalculationModel::TYPE_KERING_PENGEMAS,
            $this->request->getJSON(true) ?? [],
            (int) $user->id,
            $this->request->getIPAddress()
        );

        return $this->response
            ->setStatusCode((int) $result['status_code'])
            ->setJSON([
                'message' => $result['message'],
                'errors' => $result['errors'] ?? [],
                'data' => $result['data'] ?? null,
            ]);
    }
}
