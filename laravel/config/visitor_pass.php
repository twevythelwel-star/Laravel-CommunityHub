<?php

return [

    /*
    |--------------------------------------------------------------------------
    | What a guest pass tells the visitor
    |--------------------------------------------------------------------------
    |
    | Shown on every guest pass when a visitor's own entry does not say
    | otherwise. Nothing here has a default: an unset item is simply not shown.
    | The pass used to fall back to invented details — a 555 gatehouse number,
    | "911" for ambulance and fire (Jamaica's is 110), an AED location, an
    | assembly point and estate rules — which a guest in trouble would act on.
    |
    */

    'parking_instructions' => env('VISITOR_PASS_PARKING_INSTRUCTIONS'),

    // A list of ['title' => ..., 'rule' => ...]. Set here, not in .env.
    'community_rules' => [],

    'emergency_info' => [
        'security_phone' => env('VISITOR_PASS_SECURITY_PHONE'),
        'emergency_services' => env('VISITOR_PASS_EMERGENCY_SERVICES'), // e.g. "119 (Police) / 110 (Fire & Ambulance)"
        'aed_location' => env('VISITOR_PASS_AED_LOCATION'),
        'assembly_point' => env('OCCUPANCY_ASSEMBLY_POINT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Entry windows
    |--------------------------------------------------------------------------
    |
    | The clearance windows the calendar offers when planning a visit. They
    | describe the estate's gate hours, so they are configuration rather than
    | code.
    |
    */

    'entry_slots' => [
        [
            'id' => 'slot-morning',
            'name' => 'Morning Clearance',
            'timeRange' => '06:00 AM – 12:00 PM',
            'status' => 'Available',
            'description' => 'Optimal for standard deliveries, service contractors, and day visitors.',
        ],
        [
            'id' => 'slot-afternoon',
            'name' => 'Afternoon Clearance',
            'timeRange' => '12:00 PM – 06:00 PM',
            'status' => 'Available',
            'description' => 'Peak visitor hours, guest arrivals, and package drop-offs.',
        ],
        [
            'id' => 'slot-evening',
            'name' => 'Evening Clearance',
            'timeRange' => '06:00 PM – 11:00 PM',
            'status' => 'Available',
            'description' => 'Dinner guests, social visits, and evening service arrivals.',
        ],
        [
            'id' => 'slot-overnight',
            'name' => 'Overnight Pass',
            'timeRange' => '11:00 PM – 06:00 AM',
            'status' => 'Available on Request',
            'description' => 'Special overnight guest passes with automatic security check.',
        ],
    ],

];
