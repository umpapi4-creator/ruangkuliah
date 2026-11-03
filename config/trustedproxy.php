<?php

return [
    // Enable only when the application is reached through a trusted ingress.
    'proxies' => env('TRUSTED_PROXIES'),
];
