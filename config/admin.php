<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Access Control
    |--------------------------------------------------------------------------
    |
    | The emails listed here are allowed to access internal admin tooling such
    | as the admin dashboard, Horizon, and Telescope in non-local environments.
    | Multiple emails can be provided via the ADMIN_ALLOWED_EMAILS env value.
    |
    */
    'allowed_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('ADMIN_ALLOWED_EMAILS', 'patricksamuelguevara@gmail.com'))
    ))),
];
