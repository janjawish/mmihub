# MMI HUB

MMI HUB est une plateforme web destinée aux étudiants en BUT Métiers du Multimédia et de l’Internet (MMI). Elle centralise le suivi pédagogique et plusieurs services communautaires dans une application PHP/MySQL.

## Fonctionnalités

- Création de compte, validation par e-mail et authentification à deux facteurs
- Consultation et export des notes, absences et statistiques par semestre
- Emploi du temps personnel au format iCalendar
- Profils publics et annuaire étudiant
- Chat communautaire avec réactions et modération
- Espace de services et d’offres pour les étudiants et entreprises

## Stack technique

- PHP 8.2+
- MySQL ou MariaDB
- Apache avec `mod_rewrite`
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) pour les e-mails transactionnels

## Installation locale

1. Clonez le dépôt dans le répertoire servi par Apache, par exemple `C:\\xampp\\htdocs\\mmihub`.
2. Créez une base de données nommée `mmihub`, puis importez le schéma sans données de démonstration :

   ```bash
   mysql -u root -p mmihub < database/schema.sql
   ```

3. Définissez les variables d’environnement indiquées dans [`.env.example`](.env.example). Les variables `SMTP_USERNAME` et `SMTP_PASSWORD` sont nécessaires aux e-mails de vérification et à l’A2F.
4. Vérifiez que le dossier `register/vendor/` est présent. Il contient PHPMailer, la dépendance nécessaire à l’application.
5. Activez `mod_rewrite`, puis ouvrez `http://localhost/mmihub/`.

### Variables de configuration

| Variable | Description | Valeur locale habituelle |
| --- | --- | --- |
| `DB_HOST` | Hôte de la base de données | `localhost` |
| `DB_NAME` | Nom de la base | `mmihub` |
| `DB_USER` | Utilisateur SQL | `root` |
| `DB_PASSWORD` | Mot de passe SQL | — |
| `SMTP_HOST` / `SMTP_PORT` | Serveur SMTP | selon le fournisseur |
| `SMTP_USERNAME` / `SMTP_PASSWORD` | Identifiants SMTP | requis |
| `SMTP_FROM_EMAIL` | Adresse expéditrice | requis |

Ne versionnez jamais de valeurs réelles : `.env`, les exports de base de données et les fichiers téléversés sont exclus via `.gitignore`.

## Base de données et données personnelles

Le fichier [`database/schema.sql`](database/schema.sql) ne contient que la structure de la base. Les dumps contenant des comptes, mots de passe hachés, adresses e-mail, adresses IP ou contenus de chat ne doivent pas être publiés. Les téléversements d’avatars et de profils sont également exclus du dépôt.

## Sécurité

- Les identifiants SQL et SMTP sont lus depuis l’environnement ; aucune clé ni mot de passe n’est codé en dur.
- Avant toute mise en production, utilisez un compte SQL aux privilèges minimaux et un secret SMTP dédié.
- Faites tourner tout identifiant qui a pu être exposé dans une copie locale, un historique Git ou un partage antérieur.

## Licence

Ce projet est un travail étudiant. Aucune licence de réutilisation n’est actuellement définie.
