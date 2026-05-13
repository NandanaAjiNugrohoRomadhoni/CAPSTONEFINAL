<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\RoleModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class MenuSchedulesTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seedUsers();
        $this->seedMenus();
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
        $roleModel    = new RoleModel();
        $userProvider = new AppUserProvider();

        foreach ([
            ['role' => 'admin', 'name' => 'Admin User', 'username' => 'admin', 'email' => 'admin@example.com'],
            ['role' => 'dapur', 'name' => 'Dapur User', 'username' => 'dapur', 'email' => 'dapur@example.com'],
            ['role' => 'gudang', 'name' => 'Gudang User', 'username' => 'gudang', 'email' => 'gudang@example.com'],
        ] as $userData) {
            $role = $roleModel->findByName($userData['role']);

            $user = new User([
                'role_id'   => $role['id'],
                'name'      => $userData['name'],
                'username'  => $userData['username'],
                'email'     => $userData['email'],
                'is_active' => true,
                'active'    => true,
            ]);
            $user->fill(['password' => 'password123']);
            $userProvider->insert($user, true);
        }
    }

    protected function seedMenus(): void
    {
        $db = Database::connect();

        $rows = [];
        for ($id = 1; $id <= 11; $id++) {
            $rows[] = ['id' => $id, 'name' => 'Paket ' . $id];
        }

        $db->table('menus')->insertBatch($rows);
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

    public function testScheduleCrudFlowAndRoleAccess(): void
    {
        $dapurToken = $this->login('dapur');

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $dapurToken])
            ->withBodyFormat('json')
            ->post('api/v1/menu-schedules', [
                'day_of_month' => 15,
                'menu_id'      => 5,
            ]);

        $createResult->assertStatus(201);
        $createResult->assertJSONFragment(['message' => 'Menu schedule created successfully.']);

        $duplicateResult = $this->withHeaders(['Authorization' => 'Bearer ' . $dapurToken])
            ->withBodyFormat('json')
            ->post('api/v1/menu-schedules', [
                'day_of_month' => 15,
                'menu_id'      => 6,
            ]);
        $duplicateResult->assertStatus(400);

        $gudangToken = $this->login('gudang');
        $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/menu-schedules', [
                'day_of_month' => 16,
                'menu_id'      => 6,
            ])
            ->assertStatus(403);

        $listResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->get('api/v1/menu-schedules');
        $listResult->assertStatus(200);
        $listJson = json_decode($listResult->getJSON(), true);
        $this->assertCount(1, $listJson['data']);
        $this->assertSame(15, $listJson['data'][0]['day_of_month']);
        $this->assertSame(5, $listJson['data'][0]['menu_id']);

        $scheduleId = (int) $listJson['data'][0]['id'];

        $showResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->get('api/v1/menu-schedules/' . $scheduleId);
        $showResult->assertStatus(200);
        $showJson = json_decode($showResult->getJSON(), true);
        $this->assertSame(15, (int) ($showJson['data']['day_of_month'] ?? 0));

        $updateResult = $this->withHeaders(['Authorization' => 'Bearer ' . $dapurToken])
            ->withBodyFormat('json')
            ->put('api/v1/menu-schedules/' . $scheduleId, [
                'menu_id' => 7,
            ]);
        $updateResult->assertStatus(200);
        $updateResult->assertJSONFragment(['message' => 'Menu schedule updated successfully.']);
        $updateJson = json_decode($updateResult->getJSON(), true);
        $this->assertSame(7, (int) ($updateJson['data']['menu_id'] ?? 0));
    }

    public function testUpdateScheduleRejectsDuplicateDayOfMonth(): void
    {
        $adminToken = $this->login('admin');
        $db         = Database::connect();

        $db->table('menu_schedules')->insertBatch([
            ['day_of_month' => 15, 'menu_id' => 5],
            ['day_of_month' => 16, 'menu_id' => 6],
        ]);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->put('api/v1/menu-schedules/2', [
                'day_of_month' => 15,
            ]);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Validation failed.', $json['message']);
        $this->assertSame('The day_of_month has already been taken.', $json['errors']['day_of_month']);
    }

    public function testCalendarProjectionCoversLeapAndMonthRules(): void
    {
        $adminToken = $this->login('admin');

        foreach ([
            '2026-03-01' => 1,
            '2026-03-11' => 11,
            '2026-03-12' => 1,
            '2026-03-22' => 11,
        ] as $date => $expectedMenuId) {
            $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
                ->get('api/v1/menu-calendar?date=' . $date);
            $result->assertStatus(200);

            $json = json_decode($result->getJSON(), true);
            $this->assertSame($date, $json['data']['date'] ?? null);
            $this->assertSame($expectedMenuId, (int) ($json['data']['menu_id'] ?? 0));
            $this->assertSame('Paket ' . $expectedMenuId, $json['data']['menu_name'] ?? null);
        }

        $leapResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/menu-calendar?date=2024-02-29');
        $leapResult->assertStatus(200);
        $leapJson = json_decode($leapResult->getJSON(), true);
        $this->assertSame('2024-02-29', $leapJson['data']['date'] ?? null);
        $this->assertSame(9, (int) ($leapJson['data']['menu_id'] ?? 0));
        $this->assertSame('Paket 9', $leapJson['data']['menu_name'] ?? null);

        $invalidLeapResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/menu-calendar?date=2025-02-29');
        $invalidLeapResult->assertStatus(400);
        $invalidLeapResult->assertJSONFragment(['message' => 'Validation failed.']);

        $malformedDateResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/menu-calendar?date=2026/03/01');
        $malformedDateResult->assertStatus(400);
        $malformedDateJson = json_decode($malformedDateResult->getJSON(), true);
        $this->assertSame('Validation failed.', $malformedDateJson['message'] ?? null);
        $this->assertSame('The date field must be a valid date in Y-m-d format.', $malformedDateJson['errors']['date'] ?? null);

        if (! function_exists('cal_days_in_month')) {
            $this->markTestSkipped('calendar extension is unavailable in this runtime.');
        }

        $marchResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/menu-calendar?month=2026-03');
        $marchResult->assertStatus(200);
        $marchJson = json_decode($marchResult->getJSON(), true);
        $this->assertCount(31, $marchJson['data']);
        $this->assertSame(1, (int) ($marchJson['data'][0]['menu_id'] ?? 0));
        $this->assertSame(11, (int) ($marchJson['data'][10]['menu_id'] ?? 0));
        $this->assertSame(1, (int) ($marchJson['data'][11]['menu_id'] ?? 0));
        $this->assertSame(11, (int) ($marchJson['data'][21]['menu_id'] ?? 0));
        $this->assertSame('2026-03-31', $marchJson['data'][30]['date']);
        $this->assertSame(11, $marchJson['data'][30]['menu_id']);
        $this->assertSame('Paket 11', $marchJson['data'][30]['menu_name']);

        $aprilResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/menu-calendar?month=2026-04');
        $aprilResult->assertStatus(200);
        $aprilJson = json_decode($aprilResult->getJSON(), true);
        $this->assertCount(30, $aprilJson['data']);
        $dates = array_map(static fn (array $row): string => $row['date'], $aprilJson['data']);
        $this->assertNotContains('2026-04-31', $dates);
    }

    public function testCalendarResolverPrecedenceIsDeterministicAcrossDateMonthAndRangeModes(): void
    {
        $adminToken = $this->login('admin');
        $db         = Database::connect();

        $db->table('menu_schedules')->insert([
            'day_of_month' => 15,
            'menu_id'      => 7,
        ]);

        $dateOverrideResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/menu-calendar?date=2026-03-15');
        $dateOverrideResult->assertStatus(200);
        $dateOverrideJson = json_decode($dateOverrideResult->getJSON(), true);
        $this->assertSame('2026-03-15', $dateOverrideJson['data']['date'] ?? null);
        $this->assertSame(7, (int) ($dateOverrideJson['data']['menu_id'] ?? 0));
        $this->assertSame('Paket 7', $dateOverrideJson['data']['menu_name'] ?? null);

        $dateSpecialResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/menu-calendar?date=2024-02-29');
        $dateSpecialResult->assertStatus(200);
        $dateSpecialJson = json_decode($dateSpecialResult->getJSON(), true);
        $this->assertSame('2024-02-29', $dateSpecialJson['data']['date'] ?? null);
        $this->assertSame(9, (int) ($dateSpecialJson['data']['menu_id'] ?? 0));
        $this->assertSame('Paket 9', $dateSpecialJson['data']['menu_name'] ?? null);

        $dateFallbackResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/menu-calendar?date=2026-03-12');
        $dateFallbackResult->assertStatus(200);
        $dateFallbackJson = json_decode($dateFallbackResult->getJSON(), true);
        $this->assertSame('2026-03-12', $dateFallbackJson['data']['date'] ?? null);
        $this->assertSame(1, (int) ($dateFallbackJson['data']['menu_id'] ?? 0));
        $this->assertSame('Paket 1', $dateFallbackJson['data']['menu_name'] ?? null);

        if (! function_exists('cal_days_in_month')) {
            $this->markTestSkipped('calendar extension is unavailable in this runtime.');
        }

        $monthResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/menu-calendar?month=2026-03');
        $monthResult->assertStatus(200);
        $monthJson = json_decode($monthResult->getJSON(), true);
        $this->assertCount(31, $monthJson['data']);
        $this->assertSame('2026-03-12', $monthJson['data'][11]['date']);
        $this->assertSame(1, (int) ($monthJson['data'][11]['menu_id'] ?? 0));
        $this->assertSame('2026-03-15', $monthJson['data'][14]['date']);
        $this->assertSame(7, (int) ($monthJson['data'][14]['menu_id'] ?? 0));
        $this->assertSame('2026-03-31', $monthJson['data'][30]['date']);
        $this->assertSame(11, (int) ($monthJson['data'][30]['menu_id'] ?? 0));

        $rangeResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/menu-calendar?start_date=2026-03-12&end_date=2026-03-31');
        $rangeResult->assertStatus(200);
        $rangeJson = json_decode($rangeResult->getJSON(), true);
        $this->assertSame('2026-03-12', $rangeJson['data'][0]['date']);
        $this->assertSame(1, (int) ($rangeJson['data'][0]['menu_id'] ?? 0));
        $this->assertSame('2026-03-15', $rangeJson['data'][3]['date']);
        $this->assertSame(7, (int) ($rangeJson['data'][3]['menu_id'] ?? 0));
        $this->assertSame('2026-03-31', $rangeJson['data'][19]['date']);
        $this->assertSame(11, (int) ($rangeJson['data'][19]['menu_id'] ?? 0));
    }

    public function testCalendarResolverRejectsConflictingModeParameters(): void
    {
        $adminToken = $this->login('admin');

        $dateAndMonthResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/menu-calendar?date=2026-03-15&month=2026-03');
        $dateAndMonthResult->assertStatus(400);
        $dateAndMonthJson = json_decode($dateAndMonthResult->getJSON(), true);
        $this->assertSame('Validation failed.', $dateAndMonthJson['message'] ?? null);
        $this->assertSame('Use exactly one resolver mode: month, date, or start_date+end_date.', $dateAndMonthJson['errors']['query'] ?? null);

        $monthAndRangeResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/menu-calendar?month=2026-03&start_date=2026-03-01&end_date=2026-03-03');
        $monthAndRangeResult->assertStatus(400);
        $monthAndRangeJson = json_decode($monthAndRangeResult->getJSON(), true);
        $this->assertSame('Validation failed.', $monthAndRangeJson['message'] ?? null);
        $this->assertSame('Use exactly one resolver mode: month, date, or start_date+end_date.', $monthAndRangeJson['errors']['query'] ?? null);
    }
}
