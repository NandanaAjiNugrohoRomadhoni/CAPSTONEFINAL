<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\DailyPatientModel;
use App\Models\ItemCategoryModel;
use App\Models\ItemModel;
use App\Models\ItemUnitModel;
use App\Models\RoleModel;
use App\Models\SpkCalculationModel;
use App\Models\SpkRecommendationModel;
use App\Models\UserModel;
use App\Services\SpkPersistenceService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

class SpkHistoryPersistenceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseline();
    }

    public function testBasahCombinedWindowPersistsReconstructibleTargetDateSnapshots(): void
    {
        $service = new SpkPersistenceService();
        $userId = $this->getUserId('spkgizi');
        $basahCategoryId = $this->getCategoryId('BASAH');
        $dailyPatientId = $this->createDailyPatient('2026-04-14', 120);

        $ayam = $this->getItemByName('Ayam');
        $serviceResult = $service->createVersionedSpk([
            'spk_type' => SpkCalculationModel::TYPE_BASAH,
            'calculation_scope' => SpkCalculationModel::SCOPE_COMBINED_WINDOW,
            'calculation_date' => '2026-04-14',
            'target_date_start' => '2026-04-14',
            'target_date_end' => '2026-04-15',
            'daily_patient_id' => $dailyPatientId,
            'user_id' => $userId,
            'category_id' => $basahCategoryId,
            'estimated_patients' => 126,
            'is_finish' => false,
        ], [
            [
                'item_id' => (int) $ayam['id'],
                'target_date' => '2026-04-14',
                'current_stock_qty' => 3000,
                'required_qty' => 2800,
                'system_recommended_qty' => 0,
                'recommended_qty' => 0,
            ],
            [
                'item_id' => (int) $ayam['id'],
                'target_date' => '2026-04-15',
                'current_stock_qty' => 3000,
                'required_qty' => 3100,
                'system_recommended_qty' => 100,
                'recommended_qty' => 120,
                'is_overridden' => true,
                'override_reason' => 'Tambah buffer pengiriman.',
            ],
        ]);

        $this->assertTrue($serviceResult['success']);
        $this->assertSame(1, $serviceResult['data']['version']);

        $spkModel = new SpkCalculationModel();
        $recommendationModel = new SpkRecommendationModel();

        $spk = $spkModel->find($serviceResult['data']['id']);
        $this->assertNotNull($spk);
        $this->assertSame('basah', $spk['spk_type']);
        $this->assertSame('combined_window', $spk['calculation_scope']);
        $this->assertSame('2026-04-14', $spk['target_date_start']);
        $this->assertSame('2026-04-15', $spk['target_date_end']);
        $this->assertNull($spk['target_month']);

        $details = $recommendationModel->getBySpkId((int) $spk['id']);
        $this->assertCount(2, $details);
        $this->assertSame('2026-04-14', $details[0]['target_date']);
        $this->assertSame('2026-04-15', $details[1]['target_date']);
        $this->assertSame('3000.00', number_format((float) $details[1]['current_stock_qty'], 2, '.', ''));
        $this->assertSame('3100.00', number_format((float) $details[1]['required_qty'], 2, '.', ''));
        $this->assertSame('100.00', number_format((float) $details[1]['system_recommended_qty'], 2, '.', ''));
        $this->assertSame('120.00', number_format((float) $details[1]['recommended_qty'], 2, '.', ''));
        $this->assertTrue((bool) $details[1]['is_overridden']);
        $this->assertSame('Tambah buffer pengiriman.', $details[1]['override_reason']);
    }

    public function testKeringPengemasMonthlyRegenerationCreatesNewImmutableVersion(): void
    {
        $service = new SpkPersistenceService();
        $userId = $this->getUserId('spkgizi');
        $keringCategoryId = $this->getCategoryId('KERING');

        $beras = $this->getItemByName('Beras');

        $first = $service->createVersionedSpk([
            'spk_type' => SpkCalculationModel::TYPE_KERING_PENGEMAS,
            'calculation_scope' => SpkCalculationModel::SCOPE_MONTHLY,
            'calculation_date' => '2026-04-14',
            'target_date_start' => '2026-04-01',
            'target_date_end' => '2026-04-30',
            'target_month' => '2026-04',
            'daily_patient_id' => null,
            'user_id' => $userId,
            'category_id' => $keringCategoryId,
            'estimated_patients' => 0,
            'is_finish' => false,
        ], [
            [
                'item_id' => (int) $beras['id'],
                'target_date' => null,
                'current_stock_qty' => 5000,
                'required_qty' => 7000,
                'system_recommended_qty' => 2000,
                'recommended_qty' => 2000,
            ],
        ]);

        $second = $service->createVersionedSpk([
            'spk_type' => SpkCalculationModel::TYPE_KERING_PENGEMAS,
            'calculation_scope' => SpkCalculationModel::SCOPE_MONTHLY,
            'calculation_date' => '2026-04-14',
            'target_date_start' => '2026-04-01',
            'target_date_end' => '2026-04-30',
            'target_month' => '2026-04',
            'daily_patient_id' => null,
            'user_id' => $userId,
            'category_id' => $keringCategoryId,
            'estimated_patients' => 0,
            'is_finish' => false,
        ], [
            [
                'item_id' => (int) $beras['id'],
                'target_date' => null,
                'current_stock_qty' => 5000,
                'required_qty' => 8000,
                'system_recommended_qty' => 3000,
                'recommended_qty' => 2800,
                'is_overridden' => true,
                'override_reason' => 'Sesuaikan budget bulan ini.',
            ],
        ]);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertSame(1, $first['data']['version']);
        $this->assertSame(2, $second['data']['version']);

        $spkModel = new SpkCalculationModel();
        $firstRow = $spkModel->find($first['data']['id']);
        $secondRow = $spkModel->find($second['data']['id']);

        $this->assertNotNull($firstRow);
        $this->assertNotNull($secondRow);
        $this->assertSame('kering_pengemas', $firstRow['spk_type']);
        $this->assertSame('monthly', $firstRow['calculation_scope']);
        $this->assertSame('2026-04', $firstRow['target_month']);
        $this->assertFalse((bool) $firstRow['is_latest']);
        $this->assertTrue((bool) $secondRow['is_latest']);
        $this->assertSame($firstRow['scope_key'], $secondRow['scope_key']);

        $historyRows = $spkModel
            ->where('scope_key', $secondRow['scope_key'])
            ->orderBy('version', 'ASC')
            ->findAll();

        $this->assertCount(2, $historyRows);
        $this->assertSame(1, (int) $historyRows[0]['version']);
        $this->assertSame(2, (int) $historyRows[1]['version']);

        $recommendationModel = new SpkRecommendationModel();
        $oldVersionRec = $recommendationModel->getBySpkId((int) $firstRow['id']);
        $newVersionRec = $recommendationModel->getBySpkId((int) $secondRow['id']);

        $this->assertCount(1, $oldVersionRec);
        $this->assertCount(1, $newVersionRec);
        $this->assertSame('2000.00', number_format((float) $oldVersionRec[0]['recommended_qty'], 2, '.', ''));
        $this->assertSame('2800.00', number_format((float) $newVersionRec[0]['recommended_qty'], 2, '.', ''));
        $this->assertSame('3000.00', number_format((float) $newVersionRec[0]['system_recommended_qty'], 2, '.', ''));
        $this->assertTrue((bool) $newVersionRec[0]['is_overridden']);
        $this->assertSame('Sesuaikan budget bulan ini.', $newVersionRec[0]['override_reason']);
    }

    public function testOverriddenSpkRecommendationRequiresOverrideReason(): void
    {
        $service = new SpkPersistenceService();
        $userId = $this->getUserId('spkgizi');
        $keringCategoryId = $this->getCategoryId('KERING');
        $beras = $this->getItemByName('Beras');

        $result = $service->createVersionedSpk([
            'spk_type' => SpkCalculationModel::TYPE_KERING_PENGEMAS,
            'calculation_scope' => SpkCalculationModel::SCOPE_MONTHLY,
            'calculation_date' => '2026-04-15',
            'target_date_start' => '2026-04-01',
            'target_date_end' => '2026-04-30',
            'target_month' => '2026-04',
            'daily_patient_id' => null,
            'user_id' => $userId,
            'category_id' => $keringCategoryId,
            'estimated_patients' => 0,
            'is_finish' => false,
        ], [
            [
                'item_id' => (int) $beras['id'],
                'target_date' => null,
                'current_stock_qty' => 5000,
                'required_qty' => 8000,
                'system_recommended_qty' => 3000,
                'recommended_qty' => 2800,
                'is_overridden' => true,
            ],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Validation failed.', $result['message']);
        $this->assertArrayHasKey('recommendations.0.override_reason', $result['errors']);
    }

    private function seedBaseline(): void
    {
        $roleModel = new RoleModel();
        $roleModel->insertBatch([
            ['name' => 'admin'],
            ['name' => 'dapur'],
            ['name' => 'gudang'],
        ]);

        $userProvider = new AppUserProvider();
        $adminRole = $roleModel->findByName('admin');
        $dapurRole = $roleModel->findByName('dapur');

        $users = [
            [
                'role_id' => $adminRole['id'],
                'name' => 'Admin User',
                'username' => 'admin',
                'email' => 'admin@example.com',
            ],
            [
                'role_id' => $dapurRole['id'],
                'name' => 'Dapur User',
                'username' => 'spkgizi',
                'email' => 'spkgizi@example.com',
            ],
        ];

        foreach ($users as $row) {
            $user = new User([
                'role_id' => $row['role_id'],
                'name' => $row['name'],
                'username' => $row['username'],
                'email' => $row['email'],
                'is_active' => true,
                'active' => true,
            ]);
            $user->fill(['password' => 'password123']);
            $userProvider->insert($user, true);
        }

        $categoryModel = new ItemCategoryModel();
        $categoryModel->insertBatch([
            ['name' => 'BASAH'],
            ['name' => 'KERING'],
            ['name' => 'PENGEMAS'],
        ]);

        $itemUnitModel = new ItemUnitModel();
        $itemUnitModel->insertBatch([
            ['name' => 'gram'],
            ['name' => 'kg'],
            ['name' => 'pack'],
        ]);

        $gramId = $itemUnitModel->getIdByName('gram');
        $kgId = $itemUnitModel->getIdByName('kg');
        $packId = $itemUnitModel->getIdByName('pack');
        $basahId = $this->getCategoryId('BASAH');
        $keringId = $this->getCategoryId('KERING');
        $pengemasId = $this->getCategoryId('PENGEMAS');

        $db = Database::connect();
        $db->table('items')->insertBatch([
            [
                'item_category_id' => $basahId,
                'name' => 'Ayam',
                'unit_base' => 'gram',
                'unit_convert' => 'kg',
                'item_unit_base_id' => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base' => 1000,
                'is_active' => true,
                'qty' => 3000,
            ],
            [
                'item_category_id' => $keringId,
                'name' => 'Beras',
                'unit_base' => 'gram',
                'unit_convert' => 'kg',
                'item_unit_base_id' => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base' => 1000,
                'is_active' => true,
                'qty' => 5000,
            ],
            [
                'item_category_id' => $pengemasId,
                'name' => 'Plastik Makanan',
                'unit_base' => 'pack',
                'unit_convert' => 'pack',
                'item_unit_base_id' => $packId,
                'item_unit_convert_id' => $packId,
                'conversion_base' => 1,
                'is_active' => true,
                'qty' => 100,
            ],
        ]);
    }

    private function createDailyPatient(string $serviceDate, int $totalPatients): int
    {
        $model = new DailyPatientModel();

        return (int) $model->insert([
            'service_date' => $serviceDate,
            'total_patients' => $totalPatients,
            'notes' => null,
        ], true);
    }

    private function getCategoryId(string $name): int
    {
        $model = new ItemCategoryModel();

        return (int) $model->getIdByName($name);
    }

    private function getItemByName(string $name): array
    {
        $model = new ItemModel();
        $row = $model->where('name', $name)->first();
        $this->assertNotNull($row);

        return $row;
    }

    private function getUserId(string $username): int
    {
        $userModel = new UserModel();
        $row = $userModel->where('username', $username)->first();
        $this->assertNotNull($row);

        return (int) $row['id'];
    }
}
