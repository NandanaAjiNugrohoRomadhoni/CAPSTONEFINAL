<?php

namespace App\Models;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

class MenuScheduleModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $migrate   = true;
    protected $namespace = 'App';

    public function testGetDayToMenuMapReturnsMultiMap()
    {
        $db = Database::connect();
        $db->table('menus')->insertBatch([
            ['id' => 1, 'name' => 'Menu 1'],
            ['id' => 2, 'name' => 'Menu 2'],
            ['id' => 3, 'name' => 'Menu 3'],
        ]);

        $model = new MenuScheduleModel();
        
        // Insert dummy data
        $model->insert(['day_of_month' => 1, 'menu_id' => 1]);
        $model->insert(['day_of_month' => 1, 'menu_id' => 2]);
        $model->insert(['day_of_month' => 2, 'menu_id' => 3]);

        $map = $model->getDayToMenuMap();

        $this->assertIsArray($map[1]);
        $this->assertCount(2, $map[1]);
        $this->assertEquals(1, $map[1][0]);
        $this->assertEquals(2, $map[1][1]);
        $this->assertIsArray($map[2]);
        $this->assertCount(1, $map[2]);
        $this->assertEquals(3, $map[2][0]);
    }
}
