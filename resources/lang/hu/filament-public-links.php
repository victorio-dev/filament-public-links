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

    'errors' => [
        'not_found' => 'Ez a link nem létezik.',
        'expired' => 'Ez a link lejárt.',
        'revoked' => 'Ez a link már nem érhető el.',
        'exhausted' => 'Ezt a linket már felhasználták.',
        'contact' => 'Új linkre van szüksége? Írjon ide: :email.',
        'throttled' => 'Túl sok kérés. Kérjük, próbálja újra később.',
    ],

];
