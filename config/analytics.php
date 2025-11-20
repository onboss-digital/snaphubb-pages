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

    'fb_pixel_ids' => array_filter(array_map('trim', explode(',', env('FB_PIXEL_IDS', '')))),
];
