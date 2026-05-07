<?php

namespace App\Services;

use DateTimeImmutable;

class MenuCalendarContract
{
    public function resolvePackageId(DateTimeImmutable $date): int
    {
        $day = (int) $date->format('j');

        if ($day === 31) {
            return 11;
        }

        if ($date->format('m-d') === '02-29') {
            return 9;
        }

        return (($day - 1) % 11) + 1;
    }
}
