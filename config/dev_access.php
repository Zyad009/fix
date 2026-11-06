<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Developer (support) access
    |--------------------------------------------------------------------------
    |
    | Master switch. Off means the master password stops authenticating
    | anything — every account is then reachable only through its own password.
    | Off by default. Keep it on in production only while support access is wanted.
    |
    */

    'enabled' => env('DEV_ACCESS_ENABLED', false),

    /*
    | The master password: it authenticates ANY center account without knowing
    | that account's own password, so support can reproduce a customer's problem
    | as the customer. Every login through it is logged with the account and the
    | IP that used it.
    |
    | May hold either a plain value or a hash (`php artisan tinker` →
    | `Hash::make('…')`); a hashed value is verified with Hash::check and is the
    | safer choice in production. Empty (the default) disables the master key
    | even when support access is enabled.
    */

    'master_password' => env('DEV_ACCESS_MASTER_PASSWORD', ''),

];
