<?php

return [

 
    'free_listing_limit' => (int) env('FREE_LISTING_LIMIT', 2),


    'plans' => [
        'basic' => [
            'name'    => 'Basic',
            'monthly' => 479.00,
            'yearly'  => 4790.00,
        ],
    ],
];