<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Entities\User;

class QA_Test extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        
        $seeder = \Config\Database::seeder();
        $seeder->call('App\Database\Seeds\TestSeeder');
    }

    protected function login(string $username): string
    {
        $result = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => $username,
                'password' => 'password123',
            ]);
        
        $json = json_decode($result->getJSON(), true);
        return $json['access_token'];
    }

    public function testNotificationFlow()
    {
        $adminToken = $this->login('admin');
        $gudangToken = $this->login('gudang');

        $opnameModel = new \App\Models\StockOpnameModel();
        $opname = $opnameModel->where('state', 'DRAFT')->first();
        
        if (!$opname) {
            $this->fail("No draft opname found.");
        }

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->post("/api/v1/stock-opnames/{$opname['id']}/submit");
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('/api/v1/notifications');
        $response->assertStatus(200);
        
        $json = json_decode($response->getJSON(), true);
        $notifications = $json['data'] ?? [];
        
        if (empty($notifications)) {
            $this->fail("No notifications found.");
        }
        
        $targetNotification = null;
        foreach ($notifications as $n) {
            if ($n['is_read'] == 0) {
                $targetNotification = $n;
                break;
            }
        }
        
        if (!$targetNotification) {
            $this->fail("No unread notifications found.");
        }
        
        $notifId = $targetNotification['id'];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->post("/api/v1/notifications/{$notifId}/read");
        $response->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('/api/v1/notifications');
        $json = json_decode($response->getJSON(), true);
        
        $isRead = false;
        foreach ($json['data'] as $n) {
            if ($n['id'] == $notifId) {
                $isRead = $n['is_read'];
                break;
            }
        }
        
        $this->assertEquals(1, $isRead, "Notification not marked as read.");
    }
}
