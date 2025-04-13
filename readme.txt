quick-checkout-popup/
├── quick-checkout-popup.php         # Fichier principal (initialisation)
├── readme.txt                         # Standard WordPress readme
├── languages/                         # Fichiers de traduction (.pot, .po, .mo)
│   └── quick-checkout-popup.pot
├── includes/                          # Fichiers PHP principaux
│   ├── class-qcp-main.php             # Classe principale, hooks & filtres
│   ├── class-qcp-frontend.php         # Logique Frontend (bouton, popup HTML)
│   ├── class-qcp-ajax.php             # Gestionnaires AJAX
│   ├── class-qcp-admin.php            # Page d'options Admin & paramètres
│   ├── class-qcp-order.php            # Logique de création de commande
│   ├── class-qcp-sheets.php           # Intégration Google Sheets (optionnel)
│   └── class-qcp-stats.php            # Logique de suivi des statistiques
├── assets/                            # Ressources statiques
│   ├── css/
│   │   ├── frontend.css               # Styles pour la popup et le bouton
│   │   └── admin.css                  # Styles pour la page d'options
│   ├── js/
│   │   ├── frontend.js                # JS pour la popup (ouverture, AJAX, validation)
│   │   └── admin.js                   # JS pour la page d'options (si nécessaire)
│   └── images/                        # Icônes, etc. (si nécessaire)
└── templates/                         # Templates surchargeables (bonne pratique)
    └── popup-checkout-form.php        # Template HTML de la popup