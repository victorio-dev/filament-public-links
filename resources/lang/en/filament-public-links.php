<?php

declare(strict_types=1);

return [

    'status' => [
        'active' => 'Active',
        'expired' => 'Expired',
        'revoked' => 'Revoked',
        'exhausted' => 'Used up',
    ],

    'errors' => [
        'not_found' => 'This link does not exist.',
        'expired' => 'This link has expired.',
        'revoked' => 'This link is no longer available.',
        'exhausted' => 'This link has already been used.',
        'throttled' => 'Too many requests. Please try again later.',
    ],

];
