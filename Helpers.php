<?php

if (!function_exists('now')) {
    function now($tz = null)
    {
        return new \DateTime('now', $tz ? new \DateTimeZone($tz) : null);
    }
}
