<?php

declare(strict_types=1);

return [

    'status' => [
        'active' => 'Actief',
        'expired' => 'Verlopen',
        'revoked' => 'Ingetrokken',
        'exhausted' => 'Opgebruikt',
    ],

    'layout' => [
        'powered_by' => 'Mogelijk gemaakt door Victorio Public Links',
    ],

    'purposes' => [
        'view' => 'Bekijken',
        'accept' => 'Accepteren',
        'download' => 'Downloaden',
    ],

    'filament' => [
        // PHP date() format of every time the package shows; PublicLinksPlugin::dateTimeFormat()
        // overrides it.
        'date_time_format' => 'd-m-Y H:i',
        'relation_manager' => [
            'title' => 'Openbare links',
            'empty' => 'Nog geen openbare links.',
        ],
        'columns' => [
            'purpose' => 'Doel',
            'status' => 'Status',
            'expires_at' => 'Verloopt',
            'uses' => 'Gebruik',
            'opens' => 'Geopend',
            'last_opened_at' => 'Laatst geopend',
            'created_at' => 'Aangemaakt',
            'never' => 'Nooit',
            'unlimited' => 'onbeperkt',
        ],
        'filters' => [
            'status' => 'Status',
        ],
        'form' => [
            'purpose' => 'Doel',
            'expiry' => 'Verloopt over',
            'expiry_days' => '{1} :count dag|[2,*] :count dagen',
            'expiry_custom' => 'Op een datum…',
            'expires_on' => 'Vervaldatum',
            'max_uses' => 'Gebruikslimiet',
            'max_uses_help' => 'Leeg laten voor geen limiet.',
            'permanent' => 'Verloopt nooit',
            'locale' => 'Taal',
            'locale_help' => 'De taal van de pagina die de link opent.',
            'locale_automatic' => 'Automatisch (browser van de bezoeker)',
            'label' => 'Notitie',
            'label_help' => 'Alleen zichtbaar voor beheerders, bijv. naar wie de link is gestuurd.',
        ],
        'actions' => [
            'issue' => [
                'label' => 'Nieuwe openbare link',
                'heading' => 'Nieuwe openbare link',
                'submit' => 'Link aanmaken',
                'created' => 'Openbare link aangemaakt',
            ],
            'url' => [
                'heading' => 'Openbare link',
                'description' => 'Iedereen met deze link kan hem zonder inloggen openen.',
                'field' => 'Link',
                'copy' => 'Kopiëren',
                'copied' => 'Gekopieerd',
                'manual' => 'Druk op Ctrl+C (Cmd+C op een Mac) om de geselecteerde link te kopiëren.',
                'unavailable' => 'Deze link kan niet meer worden getoond, omdat de applicatiesleutel is gewijzigd. Trek hem in en maak zo nodig een nieuwe aan.',
                'expires' => 'Verloopt: :date',
                'never_expires' => 'Verloopt nooit',
                'usage' => 'Gebruikslimiet: :max (gebruikt: :used)',
                'close' => 'Sluiten',
            ],
            'copy' => [
                'label' => 'Link kopiëren',
            ],
            'revoke' => [
                'label' => 'Intrekken',
                'heading' => 'Openbare link intrekken',
                'description' => 'De link werkt meteen niet meer. Dit kan niet ongedaan worden gemaakt.',
                'success' => 'De link is ingetrokken.',
            ],
            'revoke_bulk' => [
                'label' => 'Selectie intrekken',
                'heading' => 'De geselecteerde links intrekken',
                'description' => 'Actieve links daaronder werken meteen niet meer. Dit kan niet ongedaan worden gemaakt.',
                'success' => 'De geselecteerde links zijn ingetrokken.',
            ],
            'access_log' => [
                'label' => 'Toegangslogboek',
                'heading' => 'Toegangslogboek',
                'heading_with_note' => 'Toegangslogboek — :note',
                'description' => 'De laatste 50 verzoeken naar deze link. IP-adressen worden niet opgeslagen.',
                'empty' => 'Deze link is nog niet geopend.',
                'time' => 'Tijd',
                'outcome' => 'Resultaat',
                'action' => 'Actie',
                'user_agent' => 'Browser',
            ],
        ],
        'outcomes' => [
            'opened' => 'Geopend',
            'action' => 'Actie',
            'expired' => 'Geweigerd: verlopen',
            'revoked' => 'Geweigerd: ingetrokken',
            'exhausted' => 'Geweigerd: opgebruikt',
            'not_found' => 'Onbekende link',
            'unknown_action' => 'Geweigerd: onbekende actie',
            'missing_target' => 'Geweigerd: record verwijderd',
            'missing_handler' => 'Geweigerd: geen handler',
        ],
    ],

    'errors' => [
        'not_found' => 'Deze link bestaat niet.',
        'expired' => 'Deze link is verlopen.',
        'revoked' => 'Deze link is niet meer beschikbaar.',
        'exhausted' => 'Deze link is al gebruikt.',
        'contact' => 'Een nieuwe link nodig? Schrijf naar :email.',
        'throttled' => 'Te veel verzoeken. Probeer het later opnieuw.',
    ],

];
