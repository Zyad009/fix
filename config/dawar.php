<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial platform admin
    |--------------------------------------------------------------------------
    |
    | Created by `php artisan db:seed` when missing. Change the password right
    | after the first login in any shared environment.
    |
    */

    'admin' => [
        'email' => env('DAWAR_ADMIN_EMAIL', 'admin@dawar.test'),
        'password' => env('DAWAR_ADMIN_PASSWORD', 'password'),
    ],

];
