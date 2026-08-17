<?php

use Carbon\Carbon;

if (! function_exists('format_date')) {
    /**
     * Format a Carbon date as "YYYY-MM-DD HH:mm" (matches the SvelteKit formatDate util).
     */
    function format_date(Illuminate\Support\Carbon|Carbon|DateTimeInterface|string|null $date): string
    {
        if ($date === null) {
            return '';
        }

        return Illuminate\Support\Carbon::parse($date)->format('Y-m-d H:i');
    }
}
