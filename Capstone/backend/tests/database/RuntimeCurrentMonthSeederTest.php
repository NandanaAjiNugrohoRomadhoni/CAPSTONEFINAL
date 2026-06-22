<?php

namespace Tests\Database;

use App\Database\Seeds\RuntimeCurrentMonthSeeder;
use App\Models\ItemCategoryModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use DateTimeImmutable;

class RuntimeCurrentMonthSeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = RuntimeCurrentMonthSeeder::class;

    public function testRuntimeSeederGeneratesCurrentMonthBasahSpksEveryTwoDays(): void
    {
        $expectedDates = $this->resolveExpectedGenerationDates();

        $dailyPatients = $this->db->table('daily_patients')
            ->select('service_date')
            ->orderBy('service_date', 'ASC')
            ->get()
            ->getResultArray();

        $seededDates = array_map(static fn(array $row): string => (string) $row['service_date'], $dailyPatients);

        $this->assertSame($expectedDates, $seededDates, 'runtime seeder should create basah prerequisites only for the every-2-days generation cadence');

        $basahRows = $this->db->table('spk_calculations')
            ->where('spk_type', 'basah')
            ->orderBy('target_date_start', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(count($expectedDates), $basahRows, 'runtime seeder should generate one basah SPK per current-month 2-day step');

        foreach ($basahRows as $index => $row) {
            $expectedStart = new DateTimeImmutable($expectedDates[$index]);
            $expectedEnd   = $expectedStart->modify('+1 day');

            if ($expectedStart->format('Y-m') !== $expectedEnd->format('Y-m')) {
                $expectedEnd = $expectedStart;
            }

            $this->assertSame('combined_window', $row['calculation_scope']);
            $this->assertSame($expectedStart->format('Y-m-d'), $row['calculation_date']);
            $this->assertSame($expectedStart->format('Y-m-d'), $row['target_date_start']);
            $this->assertSame($expectedEnd->format('Y-m-d'), $row['target_date_end']);
            $this->assertSame(0, (int) $row['is_finish']);
            $this->assertSame(1, (int) $row['version']);
            $this->assertSame(1, (int) $row['is_latest']);
            $this->assertNotNull($row['daily_patient_id']);
        }
    }

    public function testRuntimeSeederSeedsPreviousMonthDemandAndOneCurrentMonthMonthlySpk(): void
    {
        $anchor             = new DateTimeImmutable('today');
        $currentMonthStart  = $anchor->modify('first day of this month');
        $currentMonthEnd    = $anchor->modify('last day of this month');
        $previousMonthStart = $currentMonthStart->modify('-1 month');
        $previousMonthEnd   = $previousMonthStart->modify('last day of this month');
        $currentMonth       = $currentMonthStart->format('Y-m');

        $stockTransactionCount = $this->db->table('stock_transactions')->countAllResults();
        $this->assertSame(3, $stockTransactionCount, 'runtime seeder should only create the three previous-month demand input transactions and no stock-posting side effects');

        $outTypeId = $this->db->table('transaction_types')
            ->select('id')
            ->where('name', 'OUT')
            ->get()
            ->getRow('id');

        $approvedStatusId = $this->db->table('approval_statuses')
            ->select('id')
            ->where('name', 'APPROVED')
            ->get()
            ->getRow('id');

        $this->assertNotNull($outTypeId, 'runtime seeder expectation requires OUT transaction type lookup');
        $this->assertNotNull($approvedStatusId, 'runtime seeder expectation requires APPROVED approval status lookup');

        $previousMonthApprovedOutCount = $this->db->table('stock_transactions')
            ->where('transaction_date >=', $previousMonthStart->format('Y-m-d'))
            ->where('transaction_date <=', $previousMonthEnd->format('Y-m-d'))
            ->where('is_revision', 0)
            ->where('type_id', (int) $outTypeId)
            ->where('approval_status_id', (int) $approvedStatusId)
            ->where('spk_id', null)
            ->countAllResults();

        $this->assertSame(3, $previousMonthApprovedOutCount, 'runtime seeder should provide previous-month approved OUT demand history for monthly generation');

        $monthlyRows = $this->db->table('spk_calculations')
            ->where('spk_type', 'kering_pengemas')
            ->get()
            ->getResultArray();

        $this->assertCount(1, $monthlyRows, 'runtime seeder should create exactly one current-month kering/pengemas SPK');

        $monthlyRow = $monthlyRows[0];
        $this->assertSame('monthly', $monthlyRow['calculation_scope']);
        $this->assertSame($currentMonth, $monthlyRow['target_month']);
        $this->assertSame($currentMonthStart->format('Y-m-d'), $monthlyRow['target_date_start']);
        $this->assertSame($currentMonthEnd->format('Y-m-d'), $monthlyRow['target_date_end']);
        $this->assertSame(0, (int) $monthlyRow['is_finish']);
        $this->assertSame(1, (int) $monthlyRow['version']);
        $this->assertSame(1, (int) $monthlyRow['is_latest']);

        $keringCategoryId   = (new ItemCategoryModel())->getIdByName(ItemCategoryModel::NAME_KERING);
        $pengemasCategoryId = (new ItemCategoryModel())->getIdByName('PENGEMAS');

        $this->assertNotNull($keringCategoryId);
        $this->assertNotNull($pengemasCategoryId);

        $expectedRecommendationCount = $this->db->table('items')
            ->where('deleted_at', null)
            ->whereIn('item_category_id', [(int) $keringCategoryId, (int) $pengemasCategoryId])
            ->countAllResults();

        $recommendations = $this->db->table('spk_recommendations')
            ->where('spk_id', (int) $monthlyRow['id'])
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount($expectedRecommendationCount, $recommendations, 'monthly SPK should snapshot every active KERING/PENGEMAS item');
        $this->assertTrue(
            array_reduce(
                $recommendations,
                static fn(bool $carry, array $row): bool => $carry || (float) $row['required_qty'] > 0.0,
                false
            ),
            'monthly SPK should reflect non-zero previous-month demand input'
        );

        $expectedBasahCount = count($this->resolveExpectedGenerationDates());
        $totalSpkCount      = $this->db->table('spk_calculations')->countAllResults();

        $this->assertSame($expectedBasahCount + 1, $totalSpkCount, 'runtime seeder should create only the current-month basah cadence plus one monthly kering/pengemas SPK');
    }

    /**
     * @return list<string>
     */
    private function resolveExpectedGenerationDates(): array
    {
        $dates = [];
        $cursor = new DateTimeImmutable('first day of this month');
        $today = new DateTimeImmutable('today');

        while ($cursor->format('Y-m-d') <= $today->format('Y-m-d')) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+2 days');
        }

        return $dates;
    }
}
