<?php

declare(strict_types=1);

return [

    'status' => [
        'active' => 'Activo',
        'expired' => 'Caducado',
        'revoked' => 'Revocado',
        'exhausted' => 'Agotado',
    ],

    'layout' => [
        'powered_by' => 'Con la tecnología de Victorio Public Links',
    ],

    'purposes' => [
        'view' => 'Ver',
        'accept' => 'Aceptar',
        'download' => 'Descargar',
    ],

    'filament' => [
        // PHP date() format of every time the package shows; PublicLinksPlugin::dateTimeFormat()
        // overrides it.
        'date_time_format' => 'd/m/Y H:i',
        'relation_manager' => [
            'title' => 'Enlaces públicos',
            'empty' => 'Todavía no hay enlaces públicos.',
        ],
        'columns' => [
            'purpose' => 'Propósito',
            'status' => 'Estado',
            'expires_at' => 'Caduca',
            'uses' => 'Usos',
            'opens' => 'Aperturas',
            'last_opened_at' => 'Última apertura',
            'created_at' => 'Creado',
            'never' => 'Nunca',
            'unlimited' => 'ilimitado',
        ],
        'filters' => [
            'status' => 'Estado',
        ],
        'form' => [
            'purpose' => 'Propósito',
            'expiry' => 'Caduca en',
            'expiry_days' => '{1} :count día|[2,*] :count días',
            'expiry_custom' => 'En una fecha…',
            'expires_on' => 'Fecha de caducidad',
            'max_uses' => 'Límite de uso',
            'max_uses_help' => 'Déjelo vacío para no poner límite.',
            'permanent' => 'No caduca nunca',
            'locale' => 'Idioma',
            'locale_help' => 'El idioma de la página que abre el enlace.',
            'locale_automatic' => 'Automático (navegador del visitante)',
            'label' => 'Nota',
            'label_help' => 'Solo la ven los administradores, p. ej. a quién se envió el enlace.',
        ],
        'actions' => [
            'issue' => [
                'label' => 'Nuevo enlace público',
                'heading' => 'Nuevo enlace público',
                'submit' => 'Crear enlace',
                'created' => 'Enlace público creado',
            ],
            'url' => [
                'heading' => 'Enlace público',
                'description' => 'Cualquiera con este enlace puede abrirlo sin iniciar sesión.',
                'field' => 'Enlace',
                'copy' => 'Copiar',
                'copied' => 'Copiado',
                'manual' => 'Pulse Ctrl+C (Cmd+C en Mac) para copiar el enlace seleccionado.',
                'unavailable' => 'Este enlace ya no se puede mostrar porque la clave de la aplicación cambió. Revóquelo y cree uno nuevo si es necesario.',
                'expires' => 'Caduca: :date',
                'never_expires' => 'No caduca nunca',
                'usage' => 'Límite de uso: :max (usado: :used)',
                'close' => 'Cerrar',
            ],
            'copy' => [
                'label' => 'Copiar enlace',
            ],
            'revoke' => [
                'label' => 'Revocar',
                'heading' => 'Revocar enlace público',
                'description' => 'El enlace deja de funcionar de inmediato. Esto no se puede deshacer.',
                'success' => 'El enlace ha sido revocado.',
            ],
            'revoke_bulk' => [
                'label' => 'Revocar seleccionados',
                'heading' => 'Revocar los enlaces seleccionados',
                'description' => 'Los enlaces activos entre ellos dejan de funcionar de inmediato. Esto no se puede deshacer.',
                'success' => 'Los enlaces seleccionados han sido revocados.',
            ],
            'access_log' => [
                'label' => 'Registro de accesos',
                'heading' => 'Registro de accesos',
                'heading_with_note' => 'Registro de accesos — :note',
                'description' => 'Las últimas 50 solicitudes a este enlace. No se guardan direcciones IP.',
                'empty' => 'Este enlace aún no se ha abierto.',
                'time' => 'Hora',
                'outcome' => 'Resultado',
                'action' => 'Acción',
                'user_agent' => 'Navegador',
            ],
        ],
        'outcomes' => [
            'opened' => 'Abierto',
            'action' => 'Acción',
            'expired' => 'Rechazado: caducado',
            'revoked' => 'Rechazado: revocado',
            'exhausted' => 'Rechazado: agotado',
            'not_found' => 'Enlace desconocido',
            'unknown_action' => 'Rechazado: acción desconocida',
            'missing_target' => 'Rechazado: registro eliminado',
            'missing_handler' => 'Rechazado: sin controlador',
        ],
    ],

    'errors' => [
        'not_found' => 'Este enlace no existe.',
        'expired' => 'Este enlace ha caducado.',
        'revoked' => 'Este enlace ya no está disponible.',
        'exhausted' => 'Este enlace ya se ha utilizado.',
        'contact' => '¿Necesita un enlace nuevo? Escriba a :email.',
        'throttled' => 'Demasiadas solicitudes. Inténtelo de nuevo más tarde.',
    ],

];
