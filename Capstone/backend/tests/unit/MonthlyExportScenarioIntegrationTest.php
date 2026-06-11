<?php

namespace Tests\Unit;

use App\Models\DailyPatientModel;
use App\Models\SpkCalculationModel;
use App\Models\StockTransactionModel;
use App\Services\ReportingService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

class MonthlyExportScenarioIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $refresh     = true;
    protected $seed        = 'App\Database\Seeds\TestSeeder';
    protected $namespace   = 'App';

    public function testMonthlyExportScenarioSeededCorrectly()
    {
        $db = Database::connect();

        // 1. Verify Stock Snapshots
        $snapshots = $db->table('monthly_stock_snapshots')->orderBy('period_month', 'ASC')->get()->getResultArray();
        $this->assertNotEmpty($snapshots);
        
        $months = array_unique(array_column($snapshots, 'period_month'));
        sort($months);
        $this->assertContains('2026-04-01', $months);
        $this->assertContains('2026-05-01', $months);

        // 2. Verify Daily Patients
        $patientModel = new DailyPatientModel();
        $aprilPatients = $patientModel->where('service_date >=', '2026-04-01')->where('service_date <=', '2026-04-30')->countAllResults();
        $this->assertEquals(30, $aprilPatients, 'April should have 30 daily patient records');

        $mayPatients = $patientModel->where('service_date >=', '2026-05-01')->where('service_date <=', '2026-05-31')->countAllResults();
        $this->assertEquals(31, $mayPatients, 'May should have 31 daily patient records');

        // 3. Verify SPK Calculations
        $spkModel = new SpkCalculationModel();
        $aprilSpks = $spkModel->where('calculation_date >=', '2026-04-01')->where('calculation_date <=', '2026-04-30')->countAllResults();
        $this->assertGreaterThan(0, $aprilSpks, 'There should be SPKs generated for April');

        // 4. Verify Stock Transactions (IN from SPK posting and OUT from manual simulation)
        $transactionModel = new StockTransactionModel();
        $aprilTransactions = $transactionModel->where('transaction_date >=', '2026-04-01')->where('transaction_date <=', '2026-04-30')->countAllResults();
        $this->assertGreaterThan(0, $aprilTransactions, 'There should be transactions recorded for April');
    }

    public function testReportingServiceUtilizesScenarioData()
    {
        $reportingService = new ReportingService();

        // Test for April 2026
        $query = [
            'period_start' => '2026-04-01',
            'period_end'   => '2026-04-30',
        ];

        $result = $reportingService->getMonthlyStockExport($query);

        $this->assertTrue($result['success']);
        $data = $result['data'];
        
        $this->assertNotEmpty($data['rows']);
        
        foreach ($data['rows'] as $row) {
            // Every item should have a starting stock from the snapshot
            $this->assertNotNull($row['stok_awal'], "Item {$row['nama_bahan_makanan']} should have stok_awal");
            
            // Check if there are movements
            if ($row['nama_bahan_makanan'] === 'Daging Ayam') {
                $this->assertNotEmpty($row['harian'], "Basah item Daging Ayam should have harian movements");
                
                // Verify sisa calculation
                foreach ($row['harian'] as $harian) {
                    $this->assertNotNull($harian['sisa'], "Running sisa should be calculated");
                }
            }
        }
    }

    public function testSnapshotServiceIdempotency()
    {
        $db = Database::connect();
        $snapshotService = new \App\Services\StockSnapshotService();
        
        // Month that was already seeded
        $month = '2026-04';
        
        $initialCount = $db->table('monthly_stock_snapshots')->where('period_month', '2026-04-01')->countAllResults();
        
        $result = $snapshotService->takeOpeningSnapshot($month);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['count'], 'Should not insert new rows for already snapped month');
        
        $finalCount = $db->table('monthly_stock_snapshots')->where('period_month', '2026-04-01')->countAllResults();
        $this->assertEquals($initialCount, $finalCount);
    }
}
