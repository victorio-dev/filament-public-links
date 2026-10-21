<?php

declare(strict_types=1);

return [

    'status' => [
        'active' => 'Actif',
        'expired' => 'Expiré',
        'revoked' => 'Révoqué',
        'exhausted' => 'Épuisé',
    ],

    'layout' => [
        'powered_by' => 'Propulsé par Victorio Public Links',
    ],

    'purposes' => [
        'view' => 'Consulter',
        'accept' => 'Accepter',
        'download' => 'Télécharger',
    ],

    'filament' => [
        // PHP date() format of every time the package shows; PublicLinksPlugin::dateTimeFormat()
        // overrides it.
        'date_time_format' => 'd/m/Y H:i',
        'relation_manager' => [
            'title' => 'Liens publics',
            'empty' => 'Aucun lien public pour le moment.',
        ],
        'columns' => [
            'purpose' => 'Objet',
            'status' => 'Statut',
            'expires_at' => 'Expire',
            'uses' => 'Utilisations',
            'opens' => 'Ouvertures',
            'last_opened_at' => 'Dernière ouverture',
            'created_at' => 'Créé',
            'never' => 'Jamais',
            'unlimited' => 'illimité',
        ],
        'filters' => [
            'status' => 'Statut',
        ],
        'form' => [
            'purpose' => 'Objet',
            'expiry' => 'Expire dans',
            'expiry_days' => '{1} :count jour|[2,*] :count jours',
            'expiry_custom' => 'À une date…',
            'expires_on' => 'Date d\'expiration',
            'max_uses' => 'Limite d\'utilisation',
            'max_uses_help' => 'Laisser vide pour aucune limite.',
            'permanent' => 'N\'expire jamais',
            'locale' => 'Langue',
            'locale_help' => 'La langue de la page qu\'ouvre le lien.',
            'locale_automatic' => 'Automatique (navigateur du visiteur)',
            'label' => 'Note',
            'label_help' => 'Visible uniquement par les administrateurs, p. ex. à qui le lien a été envoyé.',
        ],
        'actions' => [
            'issue' => [
                'label' => 'Nouveau lien public',
                'heading' => 'Nouveau lien public',
                'submit' => 'Créer le lien',
                'created' => 'Lien public créé',
            ],
            'url' => [
                'heading' => 'Lien public',
                'description' => 'Toute personne disposant de ce lien peut l\'ouvrir sans se connecter.',
                'field' => 'Lien',
                'copy' => 'Copier',
                'copied' => 'Copié',
                'manual' => 'Appuyez sur Ctrl+C (Cmd+C sur Mac) pour copier le lien sélectionné.',
                'unavailable' => 'Ce lien ne peut plus être affiché, car la clé de l\'application a changé. Révoquez-le et créez-en un nouveau si nécessaire.',
                'expires' => 'Expire : :date',
                'never_expires' => 'N\'expire jamais',
                'usage' => 'Limite d\'utilisation : :max (utilisé : :used)',
                'close' => 'Fermer',
            ],
            'copy' => [
                'label' => 'Copier le lien',
            ],
            'revoke' => [
                'label' => 'Révoquer',
                'heading' => 'Révoquer le lien public',
                'description' => 'Le lien cesse immédiatement de fonctionner. Cette action est irréversible.',
                'success' => 'Le lien a été révoqué.',
            ],
            'revoke_bulk' => [
                'label' => 'Révoquer la sélection',
                'heading' => 'Révoquer les liens sélectionnés',
                'description' => 'Les liens actifs parmi eux cessent immédiatement de fonctionner. Cette action est irréversible.',
                'success' => 'Les liens sélectionnés ont été révoqués.',
            ],
            'access_log' => [
                'label' => 'Journal d\'accès',
                'heading' => 'Journal d\'accès',
                'heading_with_note' => 'Journal d\'accès — :note',
                'description' => 'Les 50 dernières requêtes vers ce lien. Les adresses IP ne sont pas enregistrées.',
                'empty' => 'Ce lien n\'a pas encore été ouvert.',
                'time' => 'Heure',
                'outcome' => 'Résultat',
                'action' => 'Action',
                'user_agent' => 'Navigateur',
            ],
        ],
        'outcomes' => [
            'opened' => 'Ouvert',
            'action' => 'Action',
            'expired' => 'Refusé : expiré',
            'revoked' => 'Refusé : révoqué',
            'exhausted' => 'Refusé : épuisé',
            'not_found' => 'Lien inconnu',
            'unknown_action' => 'Refusé : action inconnue',
            'missing_target' => 'Refusé : enregistrement supprimé',
            'missing_handler' => 'Refusé : aucun gestionnaire',
        ],
    ],

    'errors' => [
        'not_found' => 'Ce lien n\'existe pas.',
        'expired' => 'Ce lien a expiré.',
        'revoked' => 'Ce lien n\'est plus disponible.',
        'exhausted' => 'Ce lien a déjà été utilisé.',
        'contact' => 'Besoin d’un nouveau lien ? Écrivez à :email.',
        'throttled' => 'Trop de requêtes. Veuillez réessayer plus tard.',
    ],

];
