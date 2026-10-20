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

    'purposes' => [
        'view' => 'View',
        'accept' => 'Accept',
        'download' => 'Download',
    ],

    'filament' => [
        // PHP date() format of every time the package shows; PublicLinksPlugin::dateTimeFormat()
        // overrides it.
        'date_time_format' => 'M j, Y H:i',

        'relation_manager' => [
            'title' => 'Public links',
            'empty' => 'No public links yet.',
        ],
        'columns' => [
            'purpose' => 'Purpose',
            'status' => 'Status',
            'expires_at' => 'Expires',
            'uses' => 'Uses',
            'opens' => 'Opens',
            'last_opened_at' => 'Last opened',
            'created_at' => 'Created',
            'never' => 'Never',
            'unlimited' => 'unlimited',
        ],
        'filters' => [
            'status' => 'Status',
        ],
        'form' => [
            'purpose' => 'Purpose',
            'expiry' => 'Expires in',
            'expiry_days' => '{1} :count day|[2,*] :count days',
            'expiry_custom' => 'On a date…',
            'expires_on' => 'Expiry date',
            'max_uses' => 'Usage limit',
            'max_uses_help' => 'Leave empty for no limit.',
            'permanent' => 'Never expires',
            'label' => 'Note',
            'label_help' => 'Only visible to admins, e.g. who the link was sent to.',
        ],
        'actions' => [
            'issue' => [
                'label' => 'New public link',
                'heading' => 'New public link',
                'submit' => 'Create link',
                'created' => 'Public link created',
            ],
            'url' => [
                'heading' => 'Public link',
                'description' => 'Anyone with this link can open it without logging in.',
                'field' => 'Link',
                'copy' => 'Copy',
                'copied' => 'Copied',
                'manual' => 'Press Ctrl+C (Cmd+C on a Mac) to copy the selected link.',
                'unavailable' => 'This link can no longer be shown: the application key changed. Revoke it and create a new one if needed.',
                'expires' => 'Expires: :date',
                'never_expires' => 'Never expires',
                'usage' => 'Usage limit: :max (used: :used)',
                'close' => 'Close',
            ],
            'copy' => [
                'label' => 'Copy link',
            ],
            'revoke' => [
                'label' => 'Revoke',
                'heading' => 'Revoke public link',
                'description' => 'The link stops working immediately. This cannot be undone.',
                'success' => 'The link has been revoked.',
            ],
            'revoke_bulk' => [
                'label' => 'Revoke selected',
                'heading' => 'Revoke the selected links',
                'description' => 'Active links among them stop working immediately. This cannot be undone.',
                'success' => 'The selected links have been revoked.',
            ],
            'access_log' => [
                'label' => 'Access log',
                'heading' => 'Access log',
                'description' => 'The last 50 requests to this link. IP addresses are not stored.',
                'empty' => 'This link has not been opened yet.',
                'time' => 'Time',
                'outcome' => 'Outcome',
                'action' => 'Action',
                'user_agent' => 'Browser',
            ],
        ],
        'outcomes' => [
            'opened' => 'Opened',
            'action' => 'Action',
            'expired' => 'Refused: expired',
            'revoked' => 'Refused: revoked',
            'exhausted' => 'Refused: used up',
            'not_found' => 'Unknown link',
            'unknown_action' => 'Refused: unknown action',
            'missing_target' => 'Refused: record deleted',
            'missing_handler' => 'Refused: no handler',
        ],
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
