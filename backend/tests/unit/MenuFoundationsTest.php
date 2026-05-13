<?php

namespace Tests\Unit;

use App\Services\MenuCalendarContract;
use App\Services\MenuPackageCatalog;
use CodeIgniter\Test\CIUnitTestCase;
use DateTimeImmutable;

class MenuFoundationsTest extends CIUnitTestCase
{
    public function testMenuPackagesAreStableFromOneToEleven(): void
    {
        $catalog  = new MenuPackageCatalog();
        $packages = $catalog->packageMap();

        $this->assertCount(11, $packages);

        for ($id = 1; $id <= 11; $id++) {
            $this->assertArrayHasKey($id, $packages);
            $this->assertSame('Paket ' . $id, $packages[$id]);
        }
    }

    public function testMealTimesUseStableBusinessIds(): void
    {
        $catalog = new MenuPackageCatalog();

        $this->assertSame(
            [
                1 => 'Pagi',
                2 => 'Siang',
                3 => 'Sore',
            ],
            $catalog->mealTimeMap(),
        );
    }

    public function testCalendarContractMatchesExplicitBusinessMappings(): void
    {
        $resolver = new MenuCalendarContract();

        $this->assertSame(1, $resolver->resolvePackageId(new DateTimeImmutable('2026-03-01')));
        $this->assertSame(1, $resolver->resolvePackageId(new DateTimeImmutable('2026-03-11')));
        $this->assertSame(1, $resolver->resolvePackageId(new DateTimeImmutable('2026-03-21')));

        $this->assertSame(10, $resolver->resolvePackageId(new DateTimeImmutable('2026-03-10')));
        $this->assertSame(10, $resolver->resolvePackageId(new DateTimeImmutable('2026-03-20')));
        $this->assertSame(10, $resolver->resolvePackageId(new DateTimeImmutable('2026-03-30')));

        $this->assertSame(11, $resolver->resolvePackageId(new DateTimeImmutable('2026-03-31')));
        $this->assertSame(9, $resolver->resolvePackageId(new DateTimeImmutable('2024-02-29')));
    }
}
