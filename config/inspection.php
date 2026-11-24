<?php

/*
|--------------------------------------------------------------------------
| Technical inspection checklist
|--------------------------------------------------------------------------
|
| One fixed checklist for every maintenance center: section => item keys,
| in display order. Labels live in lang/{ar,en}/messages.php under
| `inspection_sections` / `inspection_items`. An item key must be unique
| across sections. Saved inspections keep a snapshot of each item's label,
| so renaming an item here never rewrites past reports.
|
*/

return [

    'sections' => [
        'engine' => [
            'engine_condition', 'engine_oil', 'oil_leak', 'engine_belt', 'belt_bearing', 'spark_plugs',
            'air_filter', 'engine_mounts', 'radiator_coolant', 'cooling_fan', 'exhaust_smoke',
        ],
        'transmission' => [
            'transmission_condition', 'transmission_oil', 'gear_shifting', 'clutch', 'transmission_mount', 'driveshaft',
        ],
        'brakes' => [
            'brake_fluid', 'front_pads', 'rear_pads', 'front_discs', 'rear_discs', 'handbrake', 'abs',
        ],
        'suspension' => [
            'front_shocks', 'rear_shocks', 'control_arms', 'wheel_bearings', 'front_suspension', 'rear_suspension',
            'steering_rack', 'steering_wheel', 'wheel_alignment',
        ],
        'electrical' => [
            'battery', 'alternator', 'starter', 'headlights', 'rear_lights_signals', 'dashboard_warnings',
            'air_conditioning', 'power_windows_mirrors',
        ],
        'tires' => [
            'tire_front_right', 'tire_front_left', 'tire_rear_right', 'tire_rear_left', 'spare_tire',
        ],
        'road_test' => [
            'engine_pull', 'front_gears', 'rear_gears', 'steering_noise', 'braking_stability', 'vehicle_pull',
            'abnormal_vibration', 'suspension_noise',
        ],
        'body' => [
            'chassis', 'accident_signs', 'windshield', 'doors_handles',
        ],
    ],

];
