<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\RoleModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class SpkOverrideTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $DBGroup     = 'tests';
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seedUsers();
        $this->seedOperationalBaseline();
    }

    public function testOverrideBasahItemBeforeFinalizePersistsSystemFinalAndAudit(): void
    {
        $token = $this->login('dapur');
        $db = Database::connect();

        $spk = $this->createBasahSpk($token, '2026-03-01', 100);
        $spkId = (int) $spk['data']['id'];

        $recommendation = $db->table('spk_recommendations')
            ->where('spk_id', $spkId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($recommendation);
        $recommendationId = (int) $recommendation['id'];
        $systemQtyBefore = (float) $recommendation['system_recommended_qty'];

        $auditCountBefore = $db->table('audit_logs')->countAllResults();

        $overrideResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/history/' . $spkId . '/override', [
                'recommendation_id' => $recommendationId,
                'recommended_qty' => 77.25,
                'reason' => 'Manual safety buffer before finalize.',
            ]);

        $overrideResponse->assertStatus(200);
        $overrideJson = json_decode($overrideResponse->getJSON(), true);
        $this->assertSame('SPK recommendation item overridden successfully.', $overrideJson['message']);
        $this->assertSame($spkId, (int) $overrideJson['data']['spk_id']);
        $this->assertSame($recommendationId, (int) $overrideJson['data']['recommendation_id']);
        $this->assertSame($systemQtyBefore, (float) $overrideJson['data']['system_recommended_qty']);
        $this->assertSame(77.25, (float) $overrideJson['data']['recommended_qty']);
        $this->assertTrue((bool) $overrideJson['data']['override']['is_overridden']);
        $this->assertSame('Manual safety buffer before finalize.', $overrideJson['data']['override']['reason']);

        $after = $db->table('spk_recommendations')->where('id', $recommendationId)->get()->getRowArray();
        $this->assertNotNull($after);
        $this->assertSame(number_format($systemQtyBefore, 2, '.', ''), number_format((float) $after['system_recommended_qty'], 2, '.', ''));
        $this->assertSame('77.25', number_format((float) $after['recommended_qty'], 2, '.', ''));
        $this->assertTrue((bool) $after['is_overridden']);
        $this->assertSame('Manual safety buffer before finalize.', $after['override_reason']);
        $this->assertNotNull($after['overridden_by']);
        $this->assertNotNull($after['overridden_at']);

        $auditCountAfter = $db->table('audit_logs')->countAllResults();
        $this->assertSame($auditCountBefore + 1, $auditCountAfter);

        $latestAudit = $db->table('audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertNotNull($latestAudit);
        $this->assertSame('spk_recommendation_override', $latestAudit['action_type']);
        $this->assertSame('spk_recommendations', $latestAudit['table_name']);
        $this->assertSame($recommendationId, (int) $latestAudit['record_id']);
    }

    public function testOverrideKeringPengemasItemBeforeFinalizeWorks(): void
    {
        $token = $this->login('dapur');
        $db = Database::connect();

        $spk = $this->createKeringSpk($token, '2026-04');
        $spkId = (int) $spk['data']['id'];

        $recommendation = $db->table('spk_recommendations')
            ->where('spk_id', $spkId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();
        $this->assertNotNull($recommendation);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/kering-pengemas/history/' . $spkId . '/override', [
                'recommendation_id' => (int) $recommendation['id'],
                'recommended_qty' => 333,
                'reason' => 'Adjusted prior to finalization.',
            ]);

        $response->assertStatus(200);
        $json = json_decode($response->getJSON(), true);
        $this->assertSame(333.0, (float) $json['data']['recommended_qty']);
        $this->assertTrue((bool) $json['data']['override']['is_overridden']);
    }

    public function testOverrideRequiresNonEmptyReasonAndDoesNotMutateRow(): void
    {
        $token = $this->login('dapur');
        $db = Database::connect();

        $spk = $this->createBasahSpk($token, '2026-03-31', 100);
        $spkId = (int) $spk['data']['id'];
        $recommendation = $db->table('spk_recommendations')->where('spk_id', $spkId)->get()->getRowArray();
        $this->assertNotNull($recommendation);

        $before = $db->table('spk_recommendations')->where('id', (int) $recommendation['id'])->get()->getRowArray();
        $this->assertNotNull($before);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/history/' . $spkId . '/override', [
                'recommendation_id' => (int) $recommendation['id'],
                'recommended_qty' => 12,
                'reason' => '   ',
            ]);

        $response->assertStatus(400);
        $json = json_decode($response->getJSON(), true);
        $this->assertArrayHasKey('reason', $json['errors']);

        $after = $db->table('spk_recommendations')->where('id', (int) $recommendation['id'])->get()->getRowArray();
        $this->assertNotNull($after);
        $this->assertSame((string) $before['recommended_qty'], (string) $after['recommended_qty']);
        $this->assertSame((string) $before['is_overridden'], (string) $after['is_overridden']);
        $this->assertSame($before['override_reason'], $after['override_reason']);
    }

    public function testOverrideRejectsInvalidQuantityAndDoesNotMutateRow(): void
    {
        $token = $this->login('dapur');
        $db = Database::connect();

        $spk = $this->createBasahSpk($token, '2026-03-01', 100);
        $spkId = (int) $spk['data']['id'];
        $recommendation = $db->table('spk_recommendations')->where('spk_id', $spkId)->get()->getRowArray();
        $this->assertNotNull($recommendation);

        $before = $db->table('spk_recommendations')->where('id', (int) $recommendation['id'])->get()->getRowArray();
        $this->assertNotNull($before);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/history/' . $spkId . '/override', [
                'recommendation_id' => (int) $recommendation['id'],
                'recommended_qty' => -1,
                'reason' => 'Negative should fail.',
            ]);

        $response->assertStatus(400);
        $json = json_decode($response->getJSON(), true);
        $this->assertArrayHasKey('recommended_qty', $json['errors']);

        $after = $db->table('spk_recommendations')->where('id', (int) $recommendation['id'])->get()->getRowArray();
        $this->assertNotNull($after);
        $this->assertSame((string) $before['recommended_qty'], (string) $after['recommended_qty']);
    }

    public function testOverrideRejectsWhenSpkIsFinished(): void
    {
        $token = $this->login('dapur');
        $db = Database::connect();

        $spk = $this->createBasahSpk($token, '2026-03-31', 100);
        $spkId = (int) $spk['data']['id'];
        $recommendation = $db->table('spk_recommendations')->where('spk_id', $spkId)->get()->getRowArray();
        $this->assertNotNull($recommendation);

        $db->table('spk_calculations')->where('id', $spkId)->update(['is_finish' => 1]);

        $before = $db->table('spk_recommendations')->where('id', (int) $recommendation['id'])->get()->getRowArray();
        $this->assertNotNull($before);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/history/' . $spkId . '/override', [
                'recommendation_id' => (int) $recommendation['id'],
                'recommended_qty' => 12,
                'reason' => 'Should be blocked after finalize.',
            ]);

        $response->assertStatus(403);
        $response->assertJSONFragment(['message' => 'SPK is already finalized. Overrides are not allowed.']);

        $after = $db->table('spk_recommendations')->where('id', (int) $recommendation['id'])->get()->getRowArray();
        $this->assertNotNull($after);
        $this->assertSame((string) $before['recommended_qty'], (string) $after['recommended_qty']);
        $this->assertSame((string) $before['is_overridden'], (string) $after['is_overridden']);
    }

    public function testOverrideRejectsMismatchedRecommendationIdAndDoesNotMutateTargetSpk(): void
    {
        $token = $this->login('dapur');
        $db = Database::connect();

        $spkA = $this->createBasahSpk($token, '2026-03-01', 100);
        $spkB = $this->createBasahSpk($token, '2026-03-31', 110);

        $spkAId = (int) $spkA['data']['id'];
        $spkBId = (int) $spkB['data']['id'];

        $recommendationA = $db->table('spk_recommendations')->where('spk_id', $spkAId)->orderBy('id', 'ASC')->get()->getRowArray();
        $recommendationB = $db->table('spk_recommendations')->where('spk_id', $spkBId)->orderBy('id', 'ASC')->get()->getRowArray();
        $this->assertNotNull($recommendationA);
        $this->assertNotNull($recommendationB);

        $beforeB = $db->table('spk_recommendations')->where('id', (int) $recommendationB['id'])->get()->getRowArray();
        $this->assertNotNull($beforeB);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/history/' . $spkBId . '/override', [
                'recommendation_id' => (int) $recommendationA['id'],
                'recommended_qty' => 88,
                'reason' => 'Mismatched row should be rejected.',
            ]);

        $response->assertStatus(404);
        $response->assertJSONFragment(['message' => 'SPK recommendation item not found.']);

        $afterB = $db->table('spk_recommendations')->where('id', (int) $recommendationB['id'])->get()->getRowArray();
        $this->assertNotNull($afterB);
        $this->assertSame((string) $beforeB['recommended_qty'], (string) $afterB['recommended_qty']);
    }

    protected function seedRoles(): void
    {
        $roleModel = new RoleModel();
        $roleModel->insertBatch([
            ['name' => 'admin'],
            ['name' => 'dapur'],
            ['name' => 'gudang'],
        ]);
    }

    protected function seedUsers(): void
    {
        $roleModel = new RoleModel();
        $userProvider = new AppUserProvider();

        foreach ([
            ['role' => 'admin', 'name' => 'Admin User', 'username' => 'admin', 'email' => 'admin@example.com'],
            ['role' => 'dapur', 'name' => 'Dapur User', 'username' => 'dapur', 'email' => 'dapur@example.com'],
            ['role' => 'gudang', 'name' => 'Gudang User', 'username' => 'gudang', 'email' => 'gudang@example.com'],
        ] as $userData) {
            $role = $roleModel->findByName($userData['role']);

            $user = new User([
                'role_id' => $role['id'],
                'name' => $userData['name'],
                'username' => $userData['username'],
                'email' => $userData['email'],
                'is_active' => true,
                'active' => true,
            ]);
            $user->fill(['password' => 'password123']);
            $userProvider->insert($user, true);
        }
    }

    protected function seedOperationalBaseline(): void
    {
        $db = Database::connect();

        $db->table('meal_times')->insertBatch([
            ['id' => 1, 'name' => 'Pagi'],
            ['id' => 2, 'name' => 'Siang'],
            ['id' => 3, 'name' => 'Sore'],
        ]);

        $db->table('menus')->insertBatch([
            ['id' => 1, 'name' => 'Paket 1'],
            ['id' => 2, 'name' => 'Paket 2'],
            ['id' => 3, 'name' => 'Paket 3'],
            ['id' => 11, 'name' => 'Paket 11'],
        ]);

        $db->table('item_categories')->insertBatch([
            ['name' => 'BASAH'],
            ['name' => 'KERING'],
            ['name' => 'PENGEMAS'],
        ]);

        $db->table('transaction_types')->insertBatch([
            ['name' => 'IN'],
            ['name' => 'OUT'],
            ['name' => 'RETURN_IN'],
        ]);

        $db->table('approval_statuses')->insertBatch([
            ['name' => 'APPROVED'],
            ['name' => 'PENDING'],
            ['name' => 'REJECTED'],
        ]);

        $db->table('item_units')->insertBatch([
            ['name' => 'gram'],
            ['name' => 'kg'],
            ['name' => 'pack'],
        ]);

        $basahCategoryId = (int) $db->table('item_categories')->where('name', 'BASAH')->get()->getRowArray()['id'];
        $keringCategoryId = (int) $db->table('item_categories')->where('name', 'KERING')->get()->getRowArray()['id'];
        $pengemasCategoryId = (int) $db->table('item_categories')->where('name', 'PENGEMAS')->get()->getRowArray()['id'];

        $gramUnit = (int) $db->table('item_units')->where('name', 'gram')->get()->getRowArray()['id'];
        $kgUnit = (int) $db->table('item_units')->where('name', 'kg')->get()->getRowArray()['id'];
        $packUnit = (int) $db->table('item_units')->where('name', 'pack')->get()->getRowArray()['id'];

        $db->table('items')->insertBatch([
            [
                'item_category_id' => $basahCategoryId,
                'name' => 'Ayam Basah',
                'unit_base' => 'gram',
                'unit_convert' => 'kg',
                'item_unit_base_id' => $gramUnit,
                'item_unit_convert_id' => $kgUnit,
                'conversion_base' => 1000,
                'is_active' => true,
                'qty' => 100,
            ],
            [
                'item_category_id' => $keringCategoryId,
                'name' => 'Beras Kering',
                'unit_base' => 'gram',
                'unit_convert' => 'kg',
                'item_unit_base_id' => $gramUnit,
                'item_unit_convert_id' => $kgUnit,
                'conversion_base' => 1000,
                'is_active' => true,
                'qty' => 80,
            ],
            [
                'item_category_id' => $pengemasCategoryId,
                'name' => 'Plastik Pengemas',
                'unit_base' => 'pack',
                'unit_convert' => 'pack',
                'item_unit_base_id' => $packUnit,
                'item_unit_convert_id' => $packUnit,
                'conversion_base' => 1,
                'is_active' => true,
                'qty' => 200,
            ],
        ]);

        $ayamId = (int) $db->table('items')->where('name', 'Ayam Basah')->get()->getRowArray()['id'];

        $db->table('dishes')->insert([
            'id' => 1,
            'name' => 'Sup Ayam',
        ]);

        $db->table('dish_compositions')->insert([
            'dish_id' => 1,
            'item_id' => $ayamId,
            'qty_per_patient' => 2.00,
        ]);

        $db->table('menu_dishes')->insertBatch([
            ['menu_id' => 1, 'meal_time_id' => 2, 'dish_id' => 1],
            ['menu_id' => 2, 'meal_time_id' => 2, 'dish_id' => 1],
            ['menu_id' => 11, 'meal_time_id' => 2, 'dish_id' => 1],
        ]);

        $approvedStatusId = (int) $db->table('approval_statuses')->where('name', 'APPROVED')->get()->getRowArray()['id'];
        $outTypeId = (int) $db->table('transaction_types')->where('name', 'OUT')->get()->getRowArray()['id'];
        $berasId = (int) $db->table('items')->where('name', 'Beras Kering')->get()->getRowArray()['id'];
        $plastikId = (int) $db->table('items')->where('name', 'Plastik Pengemas')->get()->getRowArray()['id'];

        $db->table('stock_transactions')->insert([
            'type_id' => $outTypeId,
            'transaction_date' => '2026-03-05',
            'is_revision' => false,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedStatusId,
            'approved_by' => null,
            'user_id' => 1,
            'spk_id' => null,
        ]);
        $tx1 = (int) $db->insertID();
        $db->table('stock_transaction_details')->insert([
            'transaction_id' => $tx1,
            'item_id' => $berasId,
            'qty' => 500,
            'input_qty' => 500,
            'input_unit' => 'base',
        ]);

        $db->table('stock_transactions')->insert([
            'type_id' => $outTypeId,
            'transaction_date' => '2026-03-10',
            'is_revision' => false,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedStatusId,
            'approved_by' => null,
            'user_id' => 1,
            'spk_id' => null,
        ]);
        $tx2 = (int) $db->insertID();
        $db->table('stock_transaction_details')->insert([
            'transaction_id' => $tx2,
            'item_id' => $plastikId,
            'qty' => 100,
            'input_qty' => 100,
            'input_unit' => 'base',
        ]);
    }

    protected function login(string $username): string
    {
        $result = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => $username,
                'password' => 'password123',
            ]);

        $json = json_decode($result->getJSON(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('access_token', $json);

        return $json['access_token'];
    }

    private function createDailyPatient(string $token, string $serviceDate, int $totalPatients): void
    {
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/daily-patients', [
                'service_date' => $serviceDate,
                'total_patients' => $totalPatients,
            ])
            ->assertStatus(201);
    }

    private function createBasahSpk(string $token, string $serviceDate, int $totalPatients): array
    {
        $this->createDailyPatient($token, $serviceDate, $totalPatients);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/generate', [
                'service_date' => $serviceDate,
            ]);

        $response->assertStatus(201);

        $json = json_decode($response->getJSON(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);

        return $json;
    }

    private function createKeringSpk(string $token, string $targetMonth): array
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/kering-pengemas/generate', [
                'target_month' => $targetMonth,
            ]);

        $response->assertStatus(201);
        $json = json_decode($response->getJSON(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);

        return $json;
    }
}
