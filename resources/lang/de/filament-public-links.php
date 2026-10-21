<?php

declare(strict_types=1);

return [

    'status' => [
        'active' => 'Aktiv',
        'expired' => 'Abgelaufen',
        'revoked' => 'Widerrufen',
        'exhausted' => 'Aufgebraucht',
    ],

    'layout' => [
        'powered_by' => 'Bereitgestellt von Victorio Public Links',
    ],

    'purposes' => [
        'view' => 'Ansehen',
        'accept' => 'Annehmen',
        'download' => 'Herunterladen',
    ],

    'filament' => [
        // PHP date() format of every time the package shows; PublicLinksPlugin::dateTimeFormat()
        // overrides it.
        'date_time_format' => 'd.m.Y H:i',
        'relation_manager' => [
            'title' => 'Öffentliche Links',
            'empty' => 'Noch keine öffentlichen Links.',
        ],
        'columns' => [
            'purpose' => 'Zweck',
            'status' => 'Status',
            'expires_at' => 'Läuft ab',
            'uses' => 'Nutzungen',
            'opens' => 'Aufrufe',
            'last_opened_at' => 'Zuletzt geöffnet',
            'created_at' => 'Erstellt',
            'never' => 'Nie',
            'unlimited' => 'unbegrenzt',
        ],
        'filters' => [
            'status' => 'Status',
        ],
        'form' => [
            'purpose' => 'Zweck',
            'expiry' => 'Läuft ab in',
            'expiry_days' => '{1} :count Tag|[2,*] :count Tage',
            'expiry_custom' => 'An einem Datum…',
            'expires_on' => 'Ablaufdatum',
            'max_uses' => 'Nutzungslimit',
            'max_uses_help' => 'Leer lassen für kein Limit.',
            'permanent' => 'Läuft nie ab',
            'locale' => 'Sprache',
            'locale_help' => 'Die Sprache der Seite, die der Link öffnet.',
            'locale_automatic' => 'Automatisch (Browser des Besuchers)',
            'label' => 'Notiz',
            'label_help' => 'Nur für Admins sichtbar, z. B. an wen der Link ging.',
        ],
        'actions' => [
            'issue' => [
                'label' => 'Neuer öffentlicher Link',
                'heading' => 'Neuer öffentlicher Link',
                'submit' => 'Link erstellen',
                'created' => 'Öffentlicher Link erstellt',
            ],
            'url' => [
                'heading' => 'Öffentlicher Link',
                'description' => 'Jeder mit diesem Link kann ihn ohne Anmeldung öffnen.',
                'field' => 'Link',
                'copy' => 'Kopieren',
                'copied' => 'Kopiert',
                'manual' => 'Drücken Sie Strg+C (Cmd+C auf dem Mac), um den markierten Link zu kopieren.',
                'unavailable' => 'Dieser Link kann nicht mehr angezeigt werden, weil sich der Anwendungsschlüssel geändert hat. Widerrufen Sie ihn und erstellen Sie bei Bedarf einen neuen.',
                'expires' => 'Läuft ab: :date',
                'never_expires' => 'Läuft nie ab',
                'usage' => 'Nutzungslimit: :max (genutzt: :used)',
                'close' => 'Schließen',
            ],
            'copy' => [
                'label' => 'Link kopieren',
            ],
            'revoke' => [
                'label' => 'Widerrufen',
                'heading' => 'Öffentlichen Link widerrufen',
                'description' => 'Der Link funktioniert sofort nicht mehr. Dies kann nicht rückgängig gemacht werden.',
                'success' => 'Der Link wurde widerrufen.',
            ],
            'revoke_bulk' => [
                'label' => 'Auswahl widerrufen',
                'heading' => 'Die ausgewählten Links widerrufen',
                'description' => 'Aktive Links darunter funktionieren sofort nicht mehr. Dies kann nicht rückgängig gemacht werden.',
                'success' => 'Die ausgewählten Links wurden widerrufen.',
            ],
            'access_log' => [
                'label' => 'Zugriffsprotokoll',
                'heading' => 'Zugriffsprotokoll',
                'heading_with_note' => 'Zugriffsprotokoll — :note',
                'description' => 'Die letzten 50 Aufrufe dieses Links. IP-Adressen werden nicht gespeichert.',
                'empty' => 'Dieser Link wurde noch nicht geöffnet.',
                'time' => 'Zeit',
                'outcome' => 'Ergebnis',
                'action' => 'Aktion',
                'user_agent' => 'Browser',
            ],
        ],
        'outcomes' => [
            'opened' => 'Geöffnet',
            'action' => 'Aktion',
            'expired' => 'Abgelehnt: abgelaufen',
            'revoked' => 'Abgelehnt: widerrufen',
            'exhausted' => 'Abgelehnt: aufgebraucht',
            'not_found' => 'Unbekannter Link',
            'unknown_action' => 'Abgelehnt: unbekannte Aktion',
            'missing_target' => 'Abgelehnt: Datensatz gelöscht',
            'missing_handler' => 'Abgelehnt: kein Handler',
        ],
    ],

    'errors' => [
        'not_found' => 'Dieser Link existiert nicht.',
        'expired' => 'Dieser Link ist abgelaufen.',
        'revoked' => 'Dieser Link ist nicht mehr verfügbar.',
        'exhausted' => 'Dieser Link wurde bereits verwendet.',
        'contact' => 'Sie brauchen einen neuen Link? Schreiben Sie an :email.',
        'throttled' => 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.',
    ],

];
