<?php

declare(strict_types=1);

return [

    'status' => [
        'active' => 'Attivo',
        'expired' => 'Scaduto',
        'revoked' => 'Revocato',
        'exhausted' => 'Esaurito',
    ],

    'layout' => [
        'powered_by' => 'Realizzato con Victorio Public Links',
    ],

    'purposes' => [
        'view' => 'Visualizza',
        'accept' => 'Accetta',
        'download' => 'Scarica',
    ],

    'filament' => [
        // PHP date() format of every time the package shows; PublicLinksPlugin::dateTimeFormat()
        // overrides it.
        'date_time_format' => 'd/m/Y H:i',
        'relation_manager' => [
            'title' => 'Link pubblici',
            'empty' => 'Ancora nessun link pubblico.',
        ],
        'columns' => [
            'purpose' => 'Scopo',
            'status' => 'Stato',
            'expires_at' => 'Scade',
            'uses' => 'Utilizzi',
            'opens' => 'Aperture',
            'last_opened_at' => 'Ultima apertura',
            'created_at' => 'Creato',
            'never' => 'Mai',
            'unlimited' => 'illimitato',
        ],
        'filters' => [
            'status' => 'Stato',
        ],
        'form' => [
            'purpose' => 'Scopo',
            'expiry' => 'Scade tra',
            'expiry_days' => '{1} :count giorno|[2,*] :count giorni',
            'expiry_custom' => 'In una data…',
            'expires_on' => 'Data di scadenza',
            'max_uses' => 'Limite di utilizzo',
            'max_uses_help' => 'Lascia vuoto per nessun limite.',
            'permanent' => 'Non scade mai',
            'locale' => 'Lingua',
            'locale_help' => 'La lingua della pagina aperta dal link.',
            'locale_automatic' => 'Automatica (browser del visitatore)',
            'label' => 'Nota',
            'label_help' => 'Visibile solo agli amministratori, ad es. a chi è stato inviato il link.',
        ],
        'actions' => [
            'issue' => [
                'label' => 'Nuovo link pubblico',
                'heading' => 'Nuovo link pubblico',
                'submit' => 'Crea link',
                'created' => 'Link pubblico creato',
            ],
            'url' => [
                'heading' => 'Link pubblico',
                'description' => 'Chiunque abbia questo link può aprirlo senza accedere.',
                'field' => 'Link',
                'copy' => 'Copia',
                'copied' => 'Copiato',
                'manual' => 'Premi Ctrl+C (Cmd+C su Mac) per copiare il link selezionato.',
                'unavailable' => 'Questo link non può più essere mostrato perché la chiave dell\'applicazione è cambiata. Revocalo e creane uno nuovo se necessario.',
                'expires' => 'Scade: :date',
                'never_expires' => 'Non scade mai',
                'usage' => 'Limite di utilizzo: :max (usati: :used)',
                'close' => 'Chiudi',
            ],
            'copy' => [
                'label' => 'Copia link',
            ],
            'revoke' => [
                'label' => 'Revoca',
                'heading' => 'Revoca link pubblico',
                'description' => 'Il link smette subito di funzionare. Questa azione non può essere annullata.',
                'success' => 'Il link è stato revocato.',
            ],
            'revoke_bulk' => [
                'label' => 'Revoca selezionati',
                'heading' => 'Revoca i link selezionati',
                'description' => 'I link attivi tra questi smettono subito di funzionare. Questa azione non può essere annullata.',
                'success' => 'I link selezionati sono stati revocati.',
            ],
            'access_log' => [
                'label' => 'Registro accessi',
                'heading' => 'Registro accessi',
                'heading_with_note' => 'Registro accessi — :note',
                'description' => 'Le ultime 50 richieste a questo link. Gli indirizzi IP non vengono memorizzati.',
                'empty' => 'Questo link non è ancora stato aperto.',
                'time' => 'Ora',
                'outcome' => 'Esito',
                'action' => 'Azione',
                'user_agent' => 'Browser',
            ],
        ],
        'outcomes' => [
            'opened' => 'Aperto',
            'action' => 'Azione',
            'expired' => 'Rifiutato: scaduto',
            'revoked' => 'Rifiutato: revocato',
            'exhausted' => 'Rifiutato: esaurito',
            'not_found' => 'Link sconosciuto',
            'unknown_action' => 'Rifiutato: azione sconosciuta',
            'missing_target' => 'Rifiutato: record eliminato',
            'missing_handler' => 'Rifiutato: nessun gestore',
        ],
    ],

    'errors' => [
        'not_found' => 'Questo link non esiste.',
        'expired' => 'Questo link è scaduto.',
        'revoked' => 'Questo link non è più disponibile.',
        'exhausted' => 'Questo link è già stato utilizzato.',
        'contact' => 'Ti serve un nuovo link? Scrivi a :email.',
        'throttled' => 'Troppe richieste. Riprova più tardi.',
    ],

];
