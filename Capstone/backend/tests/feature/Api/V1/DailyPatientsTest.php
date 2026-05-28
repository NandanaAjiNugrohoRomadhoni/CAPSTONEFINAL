<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\RoleModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class DailyPatientsTest extends CIUnitTestCase
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

    public function testCreateListShowAndDuplicateCanonicalRejection(): void
    {
        $dapurToken = $this->login('dapur');

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $dapurToken])
            ->withBodyFormat('json')
            ->post('api/v1/daily-patients', [
                'service_date'   => '2026-05-01',
                'total_patients' => 120,
                'notes'          => 'Morning shift',
            ]);

        $createResult->assertStatus(201);
        $createJson = json_decode($createResult->getJSON(), true);
        $this->assertIsArray($createJson);
        $this->assertSame('Daily patient created successfully.', $createJson['message']);
        $this->assertArrayHasKey('data', $createJson);
        $this->assertSame('2026-05-01', $createJson['data']['service_date']);
        $this->assertSame(120, $createJson['data']['total_patients']);
        $this->assertSame('Morning shift', $createJson['data']['notes']);

        $duplicateResult = $this->withHeaders(['Authorization' => 'Bearer ' . $dapurToken])
            ->withBodyFormat('json')
            ->post('api/v1/daily-patients', [
                'service_date'   => '2026-05-01',
                'total_patients' => 130,
            ]);

        $duplicateResult->assertStatus(400);
        $duplicateJson = json_decode($duplicateResult->getJSON(), true);
        $this->assertSame('Validation failed.', $duplicateJson['message']);
        $this->assertSame(
            'A daily patient input for this service_date already exists. Use PUT /api/v1/daily-patients/{id} to update the existing row.',
            $duplicateJson['errors']['service_date']
        );
        $this->assertSame('1', $duplicateJson['errors']['existing_id']);

        $gudangToken = $this->login('gudang');

        $listResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->get('api/v1/daily-patients');

        $listResult->assertStatus(200);
        $listJson = json_decode($listResult->getJSON(), true);

        $this->assertArrayHasKey('data', $listJson);
        $this->assertArrayHasKey('meta', $listJson);
        $this->assertArrayHasKey('links', $listJson);
        $this->assertCount(1, $listJson['data']);
        $this->assertSame('2026-05-01', $listJson['data'][0]['service_date']);

        $id = (int) $listJson['data'][0]['id'];
        $serviceDate = $listJson['data'][0]['service_date'];

        $showResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->get('api/v1/daily-patients/' . $serviceDate);

        $showResult->assertStatus(200);
        $showJson = json_decode($showResult->getJSON(), true);
        $this->assertIsArray($showJson);
        $this->assertArrayHasKey('data', $showJson);
        $this->assertSame($id, (int) $showJson['data']['id']);
        $this->assertSame('2026-05-01', $showJson['data']['service_date']);
        $this->assertSame(120, $showJson['data']['total_patients']);

        $malformedDateResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->get('api/v1/daily-patients/not-a-date');

        $malformedDateResult->assertStatus(400);
        $malformedDateJson = json_decode($malformedDateResult->getJSON(), true);
        $this->assertSame('Validation failed.', $malformedDateJson['message']);
        $this->assertArrayHasKey('service_date', $malformedDateJson['errors']);

        $invalidCalendarDateResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->get('api/v1/daily-patients/2026-02-31');

        $invalidCalendarDateResult->assertStatus(400);
        $invalidCalendarDateJson = json_decode($invalidCalendarDateResult->getJSON(), true);
        $this->assertSame('Validation failed.', $invalidCalendarDateJson['message']);
        $this->assertSame(
            'The service_date field must be a valid date in Y-m-d format.',
            $invalidCalendarDateJson['errors']['service_date']
        );

        $missingDateResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->get('api/v1/daily-patients/2026-05-31');

        $missingDateResult->assertStatus(404);
        $missingDateJson = json_decode($missingDateResult->getJSON(), true);
        $this->assertSame('Daily patient not found.', $missingDateJson['message']);
    }

    public function testUpdateAllowsChangingDailyPatientByIdAndRejectsDuplicateServiceDate(): void
    {
        $dapurToken = $this->login('dapur');

        $firstCreate = $this->withHeaders(['Authorization' => 'Bearer ' . $dapurToken])
            ->withBodyFormat('json')
            ->post('api/v1/daily-patients', [
                'service_date'   => '2026-05-01',
                'total_patients' => 120,
            ]);
        $firstCreate->assertStatus(201);
        $firstId = (int) json_decode($firstCreate->getJSON(), true)['data']['id'];

        $secondCreate = $this->withHeaders(['Authorization' => 'Bearer ' . $dapurToken])
            ->withBodyFormat('json')
            ->post('api/v1/daily-patients', [
                'service_date'   => '2026-05-02',
                'total_patients' => 90,
            ]);
        $secondCreate->assertStatus(201);
        $secondId = (int) json_decode($secondCreate->getJSON(), true)['data']['id'];

        $updateResult = $this->withHeaders(['Authorization' => 'Bearer ' . $dapurToken])
            ->withBodyFormat('json')
            ->put('api/v1/daily-patients/' . $firstId, [
                'service_date'   => '2026-05-03',
                'total_patients' => 140,
                'notes'          => 'Adjusted census',
            ]);

        $updateResult->assertStatus(200);
        $updateJson = json_decode($updateResult->getJSON(), true);
        $this->assertSame('Daily patient updated successfully.', $updateJson['message']);
        $this->assertSame('2026-05-03', $updateJson['data']['service_date']);
        $this->assertSame(140, $updateJson['data']['total_patients']);
        $this->assertSame('Adjusted census', $updateJson['data']['notes']);

        $duplicateUpdate = $this->withHeaders(['Authorization' => 'Bearer ' . $dapurToken])
            ->withBodyFormat('json')
            ->put('api/v1/daily-patients/' . $firstId, [
                'service_date' => '2026-05-02',
            ]);

        $duplicateUpdate->assertStatus(400);
        $duplicateJson = json_decode($duplicateUpdate->getJSON(), true);
        $this->assertSame('Validation failed.', $duplicateJson['message']);
        $this->assertSame('A daily patient input for this service_date already exists.', $duplicateJson['errors']['service_date']);
        $this->assertSame((string) $secondId, $duplicateJson['errors']['existing_id']);
    }
}
