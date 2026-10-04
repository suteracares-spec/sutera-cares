<?php

return [
    // Office staff (administrators and coordinators) can read every client
    // record, so their accounts need a second factor. Set
    // PORTAL_REQUIRE_2FA=false in .env only to get back in after losing
    // both a phone and its recovery codes, and turn it back on after.
    'require_two_factor' => (bool) env('PORTAL_REQUIRE_2FA', true),
];
