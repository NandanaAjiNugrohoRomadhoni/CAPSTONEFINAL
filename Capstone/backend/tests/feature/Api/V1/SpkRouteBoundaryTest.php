<?php

namespace Tests\Feature\Api\V1;

use CodeIgniter\Test\CIUnitTestCase;

class SpkRouteBoundaryTest extends CIUnitTestCase
{
    private function routesSource(): string
    {
        $routesPath = APPPATH . 'Config/Routes.php';
        $this->assertFileExists($routesPath);

        $contents = (string) file_get_contents($routesPath);
        $this->assertNotSame('', $contents);

        return $contents;
    }

    public function testMenuCalendarProjectionBoundaryIsPresentAndStandalone(): void
    {
        $routes = $this->routesSource();

        $this->assertStringContainsString('$routes->get("menu-calendar", "MenuSchedules::calendarProjection")', $routes);
        $this->assertStringNotContainsString('"menu-calendar/history"', $routes);
        $this->assertStringNotContainsString('"menu-calendar/post-stock"', $routes);
    }

    public function testSpkGenerateAndHistoryAreNotNestedUnderMenuCalendar(): void
    {
        $routes = $this->routesSource();

        $this->assertStringNotContainsString('"menu-calendar/generate"', $routes);
        $this->assertStringNotContainsString('"menu-calendar/history"', $routes);
        $this->assertStringNotContainsString('"menu-calendar/daily-patients"', $routes);
    }

    public function testSpkPostStockEndpointsAreSeparateWorkflowActions(): void
    {
        $routes = $this->routesSource();

        $this->assertStringContainsString('"spk/basah/history/(:num)/post-stock"', $routes);
        $this->assertStringContainsString('"spk/kering-pengemas/history/(:num)/post-stock"', $routes);
        $this->assertStringNotContainsString('"spk/basah/generate/post-stock"', $routes);
        $this->assertStringNotContainsString('"spk/kering-pengemas/generate/post-stock"', $routes);
    }

    public function testSpkFamiliesRemainDistinctByPathNaming(): void
    {
        $routes = $this->routesSource();

        $this->assertStringContainsString('"spk/basah/history"', $routes);
        $this->assertStringContainsString('"spk/kering-pengemas/history"', $routes);
        $this->assertStringNotContainsString('"spk/basah-kering-pengemas/history"', $routes);
    }
}
