<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\NotificationModel;
use App\Models\RoleModel;
use App\Services\NotificationService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class NotificationApiTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private User $user1;
    private User $user2;
    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seedUsers();

        $this->notificationService = new NotificationService();
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

        $adminRoleId = $roleModel->where('name', 'admin')->first()['id'];

        $this->user1 = new User([
            'username'  => 'user1',
            'email'     => 'user1@example.com',
            'role_id'   => $adminRoleId,
            'name'      => 'User One',
            'is_active' => true,
            'active'    => true,
        ]);
        $this->user1->fill(['password' => 'password123']);
        $userProvider->insert($this->user1, true);
        $this->user1 = $userProvider->findById($userProvider->getInsertID());

        $this->user2 = new User([
            'username'  => 'user2',
            'email'     => 'user2@example.com',
            'role_id'   => $adminRoleId,
            'name'      => 'User Two',
            'is_active' => true,
            'active'    => true,
        ]);
        $this->user2->fill(['password' => 'password123']);
        $userProvider->insert($this->user2, true);
        $this->user2 = $userProvider->findById($userProvider->getInsertID());
    }

    protected function loginAsUser1(): string
    {
        $result = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'user1',
                'password' => 'password123',
            ]);
        
        $json = json_decode($result->getJSON(), true);
        return $json['access_token'];
    }

    public function testGetNotifications(): void
    {
        $token = $this->loginAsUser1();

        $this->notificationService->sendToUser($this->user1->id, 'Title 1', 'Message 1', 'INFO');
        $id2 = $this->notificationService->sendToUser($this->user1->id, 'Title 2', 'Message 2', 'WARNING');
        $this->notificationService->sendToUser($this->user2->id, 'Title 3', 'Message 3', 'ERROR');
        
        $this->notificationService->markAsRead($id2, $this->user1->id);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get('api/v1/notifications');

        $response->assertStatus(200);
        
        $json = json_decode($response->getJSON(), true);
        
        $this->assertArrayHasKey('data', $json);
        $this->assertCount(2, $json['data']);
        $this->assertEquals('Title 2', $json['data'][0]['title']);
        $this->assertEquals('Title 1', $json['data'][1]['title']);

        // Test filtering by is_read
        $responseRead = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get('api/v1/notifications?is_read=1');
        
        $jsonRead = json_decode($responseRead->getJSON(), true);
        $this->assertCount(1, $jsonRead['data']);
        $this->assertEquals('Title 2', $jsonRead['data'][0]['title']);

        // Test searching by q
        $responseSearch = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get('api/v1/notifications?q=Title 1');
        
        $jsonSearch = json_decode($responseSearch->getJSON(), true);
        $this->assertCount(1, $jsonSearch['data']);
        $this->assertEquals('Title 1', $jsonSearch['data'][0]['title']);

        // Test pagination
        $responsePage = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get('api/v1/notifications?page=1&perPage=1');

        $jsonPage = json_decode($responsePage->getJSON(), true);
        $this->assertCount(1, $jsonPage['data']);
        $this->assertEquals(2, $jsonPage['meta']['total']);
        $this->assertEquals(2, $jsonPage['meta']['totalPages']);
    }

    public function testMarkAsRead(): void
    {
        $token = $this->loginAsUser1();

        $id1 = $this->notificationService->sendToUser($this->user1->id, 'Title 1', 'Message 1', 'INFO');
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post("api/v1/notifications/{$id1}/read");
        
        $response->assertStatus(200);
        $response->assertJSONExact(['message' => 'Notification marked as read.']);

        $model = new NotificationModel();
        $notif = $model->find($id1);
        $this->assertEquals(1, $notif['is_read']);
    }

    public function testMarkAsReadFailsForOtherUser(): void
    {
        $token = $this->loginAsUser1();

        $id2 = $this->notificationService->sendToUser($this->user2->id, 'Title 2', 'Message 2', 'INFO');
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post("api/v1/notifications/{$id2}/read");
        
        $response->assertStatus(404);
        $response->assertJSONExact(['message' => 'Notification not found or access denied.', 'errors' => []]);

        $model = new NotificationModel();
        $notif = $model->find($id2);
        $this->assertEquals(0, $notif['is_read']);
    }

    public function testMarkAllAsRead(): void
    {
        $token = $this->loginAsUser1();

        $id1 = $this->notificationService->sendToUser($this->user1->id, 'Title 1', 'Message 1', 'INFO');
        $id2 = $this->notificationService->sendToUser($this->user1->id, 'Title 2', 'Message 2', 'WARNING');
        $id3 = $this->notificationService->sendToUser($this->user2->id, 'Title 3', 'Message 3', 'ERROR');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('api/v1/notifications/read-all');
        
        $response->assertStatus(200);
        $response->assertJSONExact(['message' => 'All notifications marked as read.']);

        $model = new NotificationModel();
        
        $notif1 = $model->find($id1);
        $this->assertEquals(1, $notif1['is_read']);
        
        $notif2 = $model->find($id2);
        $this->assertEquals(1, $notif2['is_read']);
        
        $notif3 = $model->find($id3);
        $this->assertEquals(0, $notif3['is_read']);
    }

    public function testDeleteNotification(): void
    {
        $token = $this->loginAsUser1();

        $id1 = $this->notificationService->sendToUser($this->user1->id, 'Title 1', 'Message 1', 'INFO');
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->delete("api/v1/notifications/{$id1}");
        
        $response->assertStatus(200);
        $response->assertJSONExact(['message' => 'Notification deleted.']);

        $model = new NotificationModel();
        $this->assertEmpty($model->find($id1));
    }

    public function testDeleteNotificationFailsForOtherUser(): void
    {
        $token = $this->loginAsUser1();

        $id2 = $this->notificationService->sendToUser($this->user2->id, 'Title 2', 'Message 2', 'INFO');
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->delete("api/v1/notifications/{$id2}");
        
        $response->assertStatus(404);
        $response->assertJSONExact(['message' => 'Notification not found or access denied.', 'errors' => []]);

        $model = new NotificationModel();
        $this->assertNotEmpty($model->find($id2));
    }

    public function testDeleteAllNotifications(): void
    {
        $token = $this->loginAsUser1();

        $this->notificationService->sendToUser($this->user1->id, 'Title 1', 'Message 1', 'INFO');
        $this->notificationService->sendToUser($this->user1->id, 'Title 2', 'Message 2', 'INFO');
        $id3 = $this->notificationService->sendToUser($this->user2->id, 'Title 3', 'Message 3', 'INFO');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->delete('api/v1/notifications');
        
        $response->assertStatus(200);
        $response->assertJSONExact(['message' => 'All notifications deleted.']);

        $model = new NotificationModel();
        $this->assertCount(0, $model->where('user_id', $this->user1->id)->findAll());
        $this->assertNotEmpty($model->find($id3));
    }
}

