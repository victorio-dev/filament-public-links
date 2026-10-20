<?php

declare(strict_types=1);

return [

    'status' => [
        'active' => 'Aktív',
        'expired' => 'Lejárt',
        'revoked' => 'Visszavonva',
        'exhausted' => 'Felhasználva',
    ],

    'layout' => [
        'powered_by' => 'Működteti: Victorio Public Links',
    ],

    'purposes' => [
        'view' => 'Megtekintés',
        'accept' => 'Elfogadás',
        'download' => 'Letöltés',
    ],

    'filament' => [
        // PHP date() format of every time the package shows; PublicLinksPlugin::dateTimeFormat()
        // overrides it.
        'date_time_format' => 'Y. M j. H:i',

        'relation_manager' => [
            'title' => 'Nyilvános linkek',
            'empty' => 'Még nincs nyilvános link.',
        ],
        'columns' => [
            'purpose' => 'Cél',
            'status' => 'Állapot',
            'expires_at' => 'Lejár',
            'uses' => 'Használat',
            'opens' => 'Megnyitás',
            'last_opened_at' => 'Utoljára megnyitva',
            'created_at' => 'Létrehozva',
            'never' => 'Soha',
            'unlimited' => 'korlátlan',
        ],
        'filters' => [
            'status' => 'Állapot',
        ],
        'form' => [
            'purpose' => 'Cél',
            'expiry' => 'Lejárat',
            'expiry_days' => '{1} :count nap|[2,*] :count nap',
            'expiry_custom' => 'Adott napon…',
            'expires_on' => 'Lejárat napja',
            'max_uses' => 'Használati korlát',
            'max_uses_help' => 'Üresen hagyva nincs korlát.',
            'permanent' => 'Soha nem jár le',
            'label' => 'Megjegyzés',
            'label_help' => 'Csak az adminok látják, pl. kinek küldték a linket.',
        ],
        'actions' => [
            'issue' => [
                'label' => 'Új nyilvános link',
                'heading' => 'Új nyilvános link',
                'submit' => 'Link létrehozása',
                'created' => 'Nyilvános link létrehozva',
            ],
            'url' => [
                'heading' => 'Nyilvános link',
                'description' => 'Ezzel a linkkel bárki bejelentkezés nélkül megnyithatja.',
                'field' => 'Link',
                'copy' => 'Másolás',
                'copied' => 'Másolva',
                'manual' => 'A kijelölt linket Ctrl+C-vel (Macen Cmd+C-vel) másolhatja.',
                'unavailable' => 'Ez a link már nem jeleníthető meg, mert megváltozott az alkalmazás kulcsa. Szükség esetén vonja vissza, és hozzon létre újat.',
                'expires' => 'Lejár: :date',
                'never_expires' => 'Soha nem jár le',
                'usage' => 'Használati korlát: :max (felhasználva: :used)',
                'close' => 'Bezárás',
            ],
            'copy' => [
                'label' => 'Link másolása',
            ],
            'revoke' => [
                'label' => 'Visszavonás',
                'heading' => 'Nyilvános link visszavonása',
                'description' => 'A link azonnal megszűnik működni. Ez nem vonható vissza.',
                'success' => 'A linket visszavontuk.',
            ],
            'revoke_bulk' => [
                'label' => 'Kijelöltek visszavonása',
                'heading' => 'A kijelölt linkek visszavonása',
                'description' => 'Közülük az aktív linkek azonnal megszűnnek működni. Ez nem vonható vissza.',
                'success' => 'A kijelölt linkeket visszavontuk.',
            ],
            'access_log' => [
                'label' => 'Hozzáférési napló',
                'heading' => 'Hozzáférési napló',
                'description' => 'A link utolsó 50 lekérése. IP-címet nem tárolunk.',
                'empty' => 'A linket még nem nyitották meg.',
                'time' => 'Időpont',
                'outcome' => 'Eredmény',
                'action' => 'Művelet',
                'user_agent' => 'Böngésző',
            ],
        ],
        'outcomes' => [
            'opened' => 'Megnyitva',
            'action' => 'Művelet',
            'expired' => 'Elutasítva: lejárt',
            'revoked' => 'Elutasítva: visszavonva',
            'exhausted' => 'Elutasítva: felhasználva',
            'not_found' => 'Ismeretlen link',
            'unknown_action' => 'Elutasítva: ismeretlen művelet',
            'missing_target' => 'Elutasítva: a rekord törölve',
            'missing_handler' => 'Elutasítva: nincs kezelő',
        ],
    ],

    'errors' => [
        'not_found' => 'Ez a link nem létezik.',
        'expired' => 'Ez a link lejárt.',
        'revoked' => 'Ez a link már nem érhető el.',
        'exhausted' => 'Ezt a linket már felhasználták.',
        'contact' => 'Új linkre van szüksége? Írjon ide: :email.',
        'throttled' => 'Túl sok kérés. Kérjük, próbálja újra később.',
    ],

];
