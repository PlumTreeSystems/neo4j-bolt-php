<?php

namespace PTS\Bolt\Misc;

trait DateHelperTrait
{
    private function zoneFromOffset(int $seconds): \DateTimeZone
    {
        $prefix = $seconds > 0 ? '+' : '-';
        $minutes = abs($seconds) / 60;
        $hours = (int)($minutes / 60);
        $minutes = (int)($minutes - $hours * 60);
        $minutes = $minutes < 10 ? '0'.$minutes : $minutes;
        $offset = $hours < 10 ? $prefix.'0'.$hours.$minutes : $prefix.$hours.$minutes;
        return new \DateTimeZone($offset);
    }
}
