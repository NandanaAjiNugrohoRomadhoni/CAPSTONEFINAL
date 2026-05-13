<?php

namespace Tests\Feature\Api\V1;

use CodeIgniter\Test\CIUnitTestCase;

class SpkContractTest extends CIUnitTestCase
{
    private function routesSource(): string
    {
        $routesPath = APPPATH . 'Config/Routes.php';
        $this->assertFileExists($routesPath);

        $contents = (string) file_get_contents($routesPath);
        $this->assertNotSame('', $contents);

        return $contents;
    }

    public function testSpkBasahFamilyRoutesAreFrozen(): void
    {
        $routes = $this->routesSource();

        $this->assertStringContainsString('"spk/basah/menu-calendar"', $routes);
        $this->assertStringContainsString('"SpkBasah::menuCalendarProjection"', $routes);
        $this->assertStringContainsString('"spk/basah/generate"', $routes);
        $this->assertStringContainsString('"SpkBasah::generate"', $routes);
        $this->assertStringContainsString('"spk/basah/history"', $routes);
        $this->assertStringContainsString('"SpkBasah::history"', $routes);
        $this->assertStringContainsString('"spk/basah/history/(:num)"', $routes);
        $this->assertStringContainsString('SpkBasah::postStock/$1', $routes);
    }

    public function testSpkKeringPengemasFamilyRoutesAreFrozen(): void
    {
        $routes = $this->routesSource();

        $this->assertStringContainsString('"spk/kering-pengemas/menu-calendar"', $routes);
        $this->assertStringContainsString('"SpkKeringPengemas::menuCalendarProjection"', $routes);
        $this->assertStringContainsString('"spk/kering-pengemas/generate"', $routes);
        $this->assertStringContainsString('"SpkKeringPengemas::generate"', $routes);
        $this->assertStringContainsString('"spk/kering-pengemas/history"', $routes);
        $this->assertStringContainsString('"SpkKeringPengemas::history"', $routes);
        $this->assertStringContainsString('"spk/kering-pengemas/history/(:num)/post-stock"', $routes);
        $this->assertStringContainsString('SpkKeringPengemas::postStock/$1', $routes);
    }

    public function testSpkGenerateRouteHasNoImplicitAutoPostStockSubroute(): void
    {
        $routes = $this->routesSource();

        $this->assertStringNotContainsString('"spk/basah/generate/post-stock"', $routes);
        $this->assertStringNotContainsString('"spk/kering-pengemas/generate/post-stock"', $routes);
        $this->assertStringContainsString('"spk/basah/history/(:num)/post-stock"', $routes);
        $this->assertStringContainsString('"spk/kering-pengemas/history/(:num)/post-stock"', $routes);
    }

    public function testSpkMenuProjectionRouteIsSeparateFromGlobalMenuCalendar(): void
    {
        $routes = $this->routesSource();

        $this->assertStringContainsString('$routes->get("menu-calendar", "MenuSchedules::calendarProjection")', $routes);
        $this->assertStringContainsString('"spk/basah/menu-calendar"', $routes);
        $this->assertStringContainsString('"spk/kering-pengemas/menu-calendar"', $routes);
        $this->assertStringNotContainsString('"menu-calendar/generate"', $routes);
    }
}
