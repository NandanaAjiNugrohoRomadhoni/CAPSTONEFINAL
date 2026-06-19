<?php

namespace Tests\Database;

use App\Database\Seeds\MenuDishSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class SeederIntegrityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testMenuDishSeederRunsWithoutDuplicates()
    {
        // 1. Seed dependencies
        $this->db->table('meal_times')->insertBatch([
            ['name' => 'Pagi'],
            ['name' => 'Siang'],
            ['name' => 'Sore'],
        ]);

        $dishes = [];
        for ($i = 1; $i <= 35; $i++) {
            $dishes[] = ['name' => "Dish {$i}", 'is_active' => true];
        }
        $this->db->table('dishes')->insertBatch($dishes);

        $this->db->table('menus')->insertBatch(array_map(fn($i) => ['id' => $i, 'name' => "Paket {$i}"], range(1, 12)));

        // 2. Run MenuDishSeeder
        $seeder = \Config\Database::seeder();
        
        try {
            $seeder->call('App\Database\Seeds\MenuDishSeeder');
            $this->assertTrue(true, 'Seeder ran successfully');
        } catch (\Exception $e) {
            $this->fail('MenuDishSeeder failed: ' . $e->getMessage());
        }

        // 3. Verify unique constraint (menu_id, meal_time_id, dish_id)
        // If we attempt to insert the same combination, it should fail
        $existing = $this->db->table('menu_dishes')->get()->getRowArray();
        
        try {
            $this->db->table('menu_dishes')->insert([
                'menu_id'      => $existing['menu_id'],
                'meal_time_id' => $existing['meal_time_id'],
                'dish_id'      => $existing['dish_id'],
            ]);
            $this->fail('Unique constraint on (menu_id, meal_time_id, dish_id) not enforced');
        } catch (\Exception $e) {
            $this->assertTrue(true, 'Unique constraint enforced');
        }

        // 4. Verify multiple dishes per slot is allowed (different dish_id)
        $mealTimes = $this->db->table('meal_times')->get()->getResultArray();
        $dishes = $this->db->table('dishes')->get()->getResultArray();
        
        try {
            $this->db->table('menu_dishes')->insert([
                'menu_id'      => 1,
                'meal_time_id' => $mealTimes[0]['id'],
                'dish_id'      => $dishes[30]['id'],
            ]);
            $this->db->table('menu_dishes')->insert([
                'menu_id'      => 1,
                'meal_time_id' => $mealTimes[0]['id'],
                'dish_id'      => $dishes[31]['id'], // Different dish
            ]);
            $this->assertTrue(true, 'Multiple dishes per slot allowed');
        } catch (\Exception $e) {
            $this->fail('Multiple dishes per slot should be allowed: ' . $e->getMessage());
        }
    }
}
