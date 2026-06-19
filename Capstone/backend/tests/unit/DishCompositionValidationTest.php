<?php

namespace Tests\Unit;

use App\Services\DishCompositionManagementService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

class DishCompositionValidationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private int $dishId;
    private int $gramItemId;
    private int $pcsItemId;
    private int $packItemId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseline();
    }

    private function seedBaseline(): void
    {
        $db = Database::connect();
        
        $db->table('dishes')->insert(['name' => 'Test Dish']);
        $this->dishId = $db->insertID();

        $db->table('item_categories')->insert(['name' => 'Test Category']);
        $catId = $db->insertID();

        $db->table('item_units')->insertBatch([
            ['name' => 'gram'],
            ['name' => 'pcs'],
            ['name' => 'pack'],
        ]);

        $db->table('items')->insert([
            'item_category_id' => $catId,
            'name' => 'Gram Item',
            'unit_base' => 'gram',
            'unit_convert' => 'gram',
            'item_unit_base_id' => 1,
            'item_unit_convert_id' => 1,
            'conversion_base' => 1,
            'is_active' => true,
        ]);
        $this->gramItemId = $db->insertID();

        $db->table('items')->insert([
            'item_category_id' => $catId,
            'name' => 'Pcs Item',
            'unit_base' => 'pcs',
            'unit_convert' => 'pcs',
            'item_unit_base_id' => 2,
            'item_unit_convert_id' => 2,
            'conversion_base' => 1,
            'is_active' => true,
        ]);
        $this->pcsItemId = $db->insertID();

        $db->table('items')->insert([
            'item_category_id' => $catId,
            'name' => 'Pack Item',
            'unit_base' => 'pack',
            'unit_convert' => 'pack',
            'item_unit_base_id' => 3,
            'item_unit_convert_id' => 3,
            'conversion_base' => 1,
            'is_active' => true,
        ]);
        $this->packItemId = $db->insertID();
    }

    public function testValidationFailsForExtremelyLargePortion(): void
    {
        $service = new DishCompositionManagementService();
        $result = $service->createComposition([
            'dish_id' => $this->dishId,
            'item_id' => $this->gramItemId,
            'qty_per_patient' => '2001',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Max is 2000', $result['errors']['qty_per_patient']);
    }

    public function testValidationFailsForUnrealisticGramPortion(): void
    {
        $service = new DishCompositionManagementService();
        $result = $service->createComposition([
            'dish_id' => $this->dishId,
            'item_id' => $this->gramItemId,
            'qty_per_patient' => '2001',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Max is 2000', $result['errors']['qty_per_patient']);
    }

    public function testValidationFailsForUnrealisticPcsPortion(): void
    {
        $service = new DishCompositionManagementService();
        $result = $service->createComposition([
            'dish_id' => $this->dishId,
            'item_id' => $this->pcsItemId,
            'qty_per_patient' => '51',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Max is 50', $result['errors']['qty_per_patient']);
    }

    public function testValidationFailsForUnrealisticPackPortion(): void
    {
        $service = new DishCompositionManagementService();
        $result = $service->createComposition([
            'dish_id' => $this->dishId,
            'item_id' => $this->packItemId,
            'qty_per_patient' => '11',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Max is 10', $result['errors']['qty_per_patient']);
    }

    public function testValidationSucceedsForRealisticPortion(): void
    {
        $service = new DishCompositionManagementService();
        $result = $service->createComposition([
            'dish_id' => $this->dishId,
            'item_id' => $this->gramItemId,
            'qty_per_patient' => '100',
        ]);

        $this->assertTrue($result['success'], isset($result['errors']) ? json_encode($result['errors']) : '');
    }
}
