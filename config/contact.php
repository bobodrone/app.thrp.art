<?php

/*
|--------------------------------------------------------------------------
| Public contact form
|--------------------------------------------------------------------------
|
| Delivery and spam settings for the /contact page. Everything is driven from
| .env so the shared inbox and the throttle can be changed without a deploy.
|
| The spam guards are deliberately free and self-contained — no CAPTCHA
| account, no third-party script, no cost. They are layered: a bot has to beat
| the honeypot, the timer and the rate limit to get a message through.
|
*/

$csv = static fn (string $value): array => array_values(array_filter(array_map(
    static fn (string $item): string => trim($item),
    explode(',', $value)
)));

return [

    /*
     * Where contact messages are delivered. A comma-separated list is allowed.
     * Leave CONTACT_TO empty and every admin account receives them instead, so
     * a missing env var never silently drops mail on the floor.
     */
    'to' => $csv((string) env('CONTACT_TO', '')),

    'spam' => [
        /*
         * Name of the hidden honeypot input. Bots fill in every field they
         * find; a human never sees this one. Rename it if spam adapts.
         */
        'honeypot' => (string) env('CONTACT_HONEYPOT_FIELD', 'website'),

        /*
         * Seconds a real person needs to fill the form in. Anything faster was
         * not typed by hand. Kept low enough not to catch a fast paster.
         */
        'min_seconds' => (int) env('CONTACT_MIN_SECONDS', 3),

        // Successful submissions allowed per IP, per window.
        'max_per_hour' => (int) env('CONTACT_MAX_PER_HOUR', 3),

        'max_per_day' => (int) env('CONTACT_MAX_PER_DAY', 10),
    ],

];
