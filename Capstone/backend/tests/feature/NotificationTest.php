<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\NotificationService;
use App\Models\UserModel;
use App\Models\RoleModel;
use App\Models\NotificationModel;

class NotificationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';

    public function testNotificationService()
    {
        $roleModel = new RoleModel();
        $userModel = new UserModel();

        $roleId = $roleModel->insert(['name' => 'testrole', 'description' => 'desc'], true);
        $userId = $userModel->insert([
            'role_id' => $roleId,
            'name' => 'test',
            'username' => 'test',
            'email' => 'test@test.com',
            'password' => 'secret123',
            'is_active' => true
        ], true);

        $service = new NotificationService();
        $id = $service->sendToUser($userId, 'Title', 'Message', 'INFO');
        if (!$id) {
            $notifModel = new NotificationModel();
            print_r($notifModel->errors());
        }

        $this->assertIsInt($id);

        $notifs = $service->getUserNotifications($userId);
        $this->assertCount(1, $notifs['data']);

        $notifModel = new NotificationModel();
        $this->assertEquals('Title', $notifModel->find($id)['title']);

        // Test delete single
        $this->assertTrue($service->deleteNotification($id, $userId));
        $this->assertEmpty($notifModel->find($id));

        // Test delete fails for non-owner
        $id2 = $service->sendToUser($userId, 'Title 2', 'Message 2', 'INFO');
        $this->assertFalse($service->deleteNotification($id2, $userId + 1));

        // Test delete all
        $service->sendToUser($userId, 'Title 3', 'Message 3', 'INFO');
        $service->sendToUser($userId, 'Title 4', 'Message 4', 'INFO');
        $this->assertTrue($service->deleteAllNotifications($userId));
        $this->assertEmpty($service->getUserNotifications($userId)['data']);

        // Test filtering, sorting, pagination
        $service->sendToUser($userId, 'A Title', 'A Message', 'INFO');
        $idRead = $service->sendToUser($userId, 'B Title', 'B Message', 'WARNING');
        $service->markAsRead($idRead, $userId);
        $service->sendToUser($userId, 'C Title', 'C Message', 'ERROR');

        // filter by is_read
        $readNotifs = $service->getUserNotifications($userId, ['is_read' => 1]);
        $this->assertCount(1, $readNotifs['data']);
        $this->assertEquals($idRead, $readNotifs['data'][0]['id']);

        // filter by q (search)
        $searchNotifs = $service->getUserNotifications($userId, ['q' => 'B Title']);
        $this->assertCount(1, $searchNotifs['data']);
        $this->assertEquals($idRead, $searchNotifs['data'][0]['id']);

        // sort by title ? (only allowed sort is id, created_at, updated_at, is_read, type)
        $sortNotifs = $service->getUserNotifications($userId, [], 1, 10, true, 'type', 'ASC');
        $this->assertCount(3, $sortNotifs['data']);
        $this->assertEquals('ERROR', $sortNotifs['data'][0]['type']);

        // pagination
        $pageNotifs = $service->getUserNotifications($userId, [], 1, 2, true);
        $this->assertCount(2, $pageNotifs['data']);
        $this->assertEquals(3, $pageNotifs['total']);

        file_put_contents('../.sisyphus/evidence/task-1-schema.txt', "Notification created with ID: {$id}\n", FILE_APPEND);
    }
}
