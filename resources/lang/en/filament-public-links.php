<?php

declare(strict_types=1);

return [

    'status' => [
        'active' => 'Active',
        'expired' => 'Expired',
        'revoked' => 'Revoked',
        'exhausted' => 'Used up',
    ],

    'layout' => [
        'powered_by' => 'Powered by Victorio Public Links',
    ],

    'errors' => [
        'not_found' => 'This link does not exist.',
        'expired' => 'This link has expired.',
        'revoked' => 'This link is no longer available.',
        'exhausted' => 'This link has already been used.',
        'contact' => 'Need a new link? Write to :email.',
        'throttled' => 'Too many requests. Please try again later.',
    ],

];
