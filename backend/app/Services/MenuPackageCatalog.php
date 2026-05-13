<?php

namespace App\Services;

class MenuPackageCatalog
{
    /**
     * @return array<int, string>
     */
    public function packageMap(): array
    {
        $packages = [];

        for ($id = 1; $id <= 11; $id++) {
            $packages[$id] = 'Paket ' . $id;
        }

        return $packages;
    }

    /**
     * @return array<int, string>
     */
    public function mealTimeMap(): array
    {
        return [
            1 => 'Pagi',
            2 => 'Siang',
            3 => 'Sore',
        ];
    }
}
