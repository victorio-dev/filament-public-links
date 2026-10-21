<?php

declare(strict_types=1);

return [

    'status' => [
        'active' => 'Ativo',
        'expired' => 'Expirado',
        'revoked' => 'Revogado',
        'exhausted' => 'Esgotado',
    ],

    'layout' => [
        'powered_by' => 'Desenvolvido com Victorio Public Links',
    ],

    'purposes' => [
        'view' => 'Visualizar',
        'accept' => 'Aceitar',
        'download' => 'Baixar',
    ],

    'filament' => [
        // PHP date() format of every time the package shows; PublicLinksPlugin::dateTimeFormat()
        // overrides it.
        'date_time_format' => 'd/m/Y H:i',
        'relation_manager' => [
            'title' => 'Links públicos',
            'empty' => 'Ainda não há links públicos.',
        ],
        'columns' => [
            'purpose' => 'Finalidade',
            'status' => 'Status',
            'expires_at' => 'Expira',
            'uses' => 'Usos',
            'opens' => 'Aberturas',
            'last_opened_at' => 'Última abertura',
            'created_at' => 'Criado',
            'never' => 'Nunca',
            'unlimited' => 'ilimitado',
        ],
        'filters' => [
            'status' => 'Status',
        ],
        'form' => [
            'purpose' => 'Finalidade',
            'expiry' => 'Expira em',
            'expiry_days' => '{1} :count dia|[2,*] :count dias',
            'expiry_custom' => 'Em uma data…',
            'expires_on' => 'Data de expiração',
            'max_uses' => 'Limite de uso',
            'max_uses_help' => 'Deixe em branco para não ter limite.',
            'permanent' => 'Nunca expira',
            'locale' => 'Idioma',
            'locale_help' => 'O idioma da página que o link abre.',
            'locale_automatic' => 'Automático (navegador do visitante)',
            'label' => 'Observação',
            'label_help' => 'Visível só para administradores, por exemplo para quem o link foi enviado.',
        ],
        'actions' => [
            'issue' => [
                'label' => 'Novo link público',
                'heading' => 'Novo link público',
                'submit' => 'Criar link',
                'created' => 'Link público criado',
            ],
            'url' => [
                'heading' => 'Link público',
                'description' => 'Qualquer pessoa com este link pode abri-lo sem fazer login.',
                'field' => 'Link',
                'copy' => 'Copiar',
                'copied' => 'Copiado',
                'manual' => 'Pressione Ctrl+C (Cmd+C no Mac) para copiar o link selecionado.',
                'unavailable' => 'Este link não pode mais ser exibido porque a chave da aplicação mudou. Revogue-o e crie um novo, se necessário.',
                'expires' => 'Expira: :date',
                'never_expires' => 'Nunca expira',
                'usage' => 'Limite de uso: :max (usado: :used)',
                'close' => 'Fechar',
            ],
            'copy' => [
                'label' => 'Copiar link',
            ],
            'revoke' => [
                'label' => 'Revogar',
                'heading' => 'Revogar link público',
                'description' => 'O link para de funcionar imediatamente. Isso não pode ser desfeito.',
                'success' => 'O link foi revogado.',
            ],
            'revoke_bulk' => [
                'label' => 'Revogar selecionados',
                'heading' => 'Revogar os links selecionados',
                'description' => 'Os links ativos entre eles param de funcionar imediatamente. Isso não pode ser desfeito.',
                'success' => 'Os links selecionados foram revogados.',
            ],
            'access_log' => [
                'label' => 'Registro de acessos',
                'heading' => 'Registro de acessos',
                'heading_with_note' => 'Registro de acessos — :note',
                'description' => 'As últimas 50 solicitações a este link. Endereços IP não são armazenados.',
                'empty' => 'Este link ainda não foi aberto.',
                'time' => 'Horário',
                'outcome' => 'Resultado',
                'action' => 'Ação',
                'user_agent' => 'Navegador',
            ],
        ],
        'outcomes' => [
            'opened' => 'Aberto',
            'action' => 'Ação',
            'expired' => 'Recusado: expirado',
            'revoked' => 'Recusado: revogado',
            'exhausted' => 'Recusado: esgotado',
            'not_found' => 'Link desconhecido',
            'unknown_action' => 'Recusado: ação desconhecida',
            'missing_target' => 'Recusado: registro excluído',
            'missing_handler' => 'Recusado: sem manipulador',
        ],
    ],

    'errors' => [
        'not_found' => 'Este link não existe.',
        'expired' => 'Este link expirou.',
        'revoked' => 'Este link não está mais disponível.',
        'exhausted' => 'Este link já foi usado.',
        'contact' => 'Precisa de um novo link? Escreva para :email.',
        'throttled' => 'Muitas solicitações. Tente novamente mais tarde.',
    ],

];
