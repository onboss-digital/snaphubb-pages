<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Analytics Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for analytics services like
    | Google Analytics and Facebook Pixel. Using a dedicated config file
    | allows Laravel to cache the values for better performance.
    |
    */

    'ga_measurement_id' => env('GA_MEASUREMENT_ID'),

    // Support both a single FB_PIXEL_ID or multiple IDs in FB_PIXEL_IDS (comma-separated).
    // Priority: FB_PIXEL_IDS (if non-empty) -> FB_PIXEL_ID (single) -> empty array
    'fb_pixel_ids' => (function () {
        $multi = env('FB_PIXEL_IDS');
        $single = env('FB_PIXEL_ID');

        if (!is_null($multi) && trim($multi) !== '') {
            return array_filter(array_map('trim', explode(',', $multi)));
        }

        if (!is_null($single) && trim($single) !== '') {
            return [$single];
        }

        return [];
    })(),
];
