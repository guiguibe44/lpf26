# Déploiement : local → GitHub → OVH (hébergement mutualisé Pro)

Ce document décrit un **flux simple** et les **options** adaptées à un Symfony sur **OVH Web Hosting (mutualisé Pro)**.

## 1. Schéma du flux

1. **Local** : développement, tests, migrations Doctrine générées en fichiers versionnés.
2. **GitHub** (`main`) : référence partagée ; chaque push peut déclencher un déploiement automatique (voir workflow ci-dessous).
3. **OVH** : fichiers de l’application sur l’espace web (FTP/SFTP), PHP et base de données configurés dans le manager OVH.

```
┌─────────┐   git push    ┌─────────┐   FTP/FTPS    ┌─────────┐
│  Local  │ ───────────► │ GitHub  │ ───────────► │   OVH   │
└─────────┘              └─────────┘               └─────────┘
     │                        │                        │
     └─ composer / migrate    └─ Actions (optionnel)   └─ .env.local.php
        en local uniquement      build + sync            + BDD prod
```

## 2. Branches et commits (recommandé)

- **`main`** : code déployé en **production** (stable).
- Optionnel : branche `develop` pour l’intégration, puis *merge* dans `main` pour livrer.

Règle d’or : **ne jamais committer** `.env.local`, mots de passe, ni clés. En prod OVH, utiliser **variables d’environnement** ou un fichier **`.env.local.php`** (hors Git) généré sur le serveur.

## 3. Première mise en ligne sur OVH (manuelle)

À faire **une fois** (ou quand la structure change fortement) :

1. **Manager OVH** : créer la base MySQL/MariaDB ou PostgreSQL (selon ton offre), noter `DATABASE_URL`.
2. **Version PHP** : aligner avec le projet (actuellement **PHP ≥ 8.4** dans `composer.json` — vérifier la version disponible sur l’hébergement et ajuster si besoin).
3. **Racine du site** : pointer le domaine vers le dossier **`public/`** de Symfony (souvent via « Dossier racine » = `public` ou équivalent OVH).
4. Sur le serveur (FTP **ou** SSH si activé sur ton offre Pro) :
   - déposer les fichiers du projet (ou cloner Git puis `composer install` si SSH + Git disponibles) ;
   - créer **`public/uploads`** si besoin, droits d’écriture pour avatars / logos ;
   - générer **`APP_SECRET`** et les variables prod (voir [Symfony deployment](https://symfony.com/doc/current/deployment.html)).
5. **Migrations** : depuis une session SSH ou un script autorisé par OVH :

   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction --env=prod
   ```

   Si tu n’as **pas** SSH, OVH propose parfois une exécution PHP en tâche planifiée ou un accès « console » limité — sinon exécuter les migrations **en local contre la BDD prod** (avec précaution : IP autorisée, backup avant).

6. **Cache prod** (après chaque déploiement important) :

   ```bash
   php bin/console cache:clear --env=prod
   php bin/console cache:warmup --env=prod
   ```

7. **Assets** : le workflow GitHub exécute **`tailwind:build`** puis **`asset-map:compile`** avant l’envoi FTP (sans build Tailwind, `asset-map:compile` échoue sur un clone nu). En déploiement manuel, lancer les deux commandes avant upload ou sur le serveur si PHP CLI disponible.

## 4. Déploiement automatique : GitHub Actions → FTP OVH

Le fichier **`.github/workflows/deploy-ovh-ftp.yml`** :

- se déclenche sur **push** vers `main` et en **manuel** (*Run workflow*) ;
- installe les dépendances Composer (**actuellement sans `--no-dev`**, car plusieurs bundles de prod sont encore dans `require-dev` — à corriger pour une prod stricte, voir § 8) ;
- exécute **`tailwind:build`** (bundle Symfonycasts) puis **`asset-map:compile`** en environnement `prod` ;
- synchronise le dépôt vers ton FTP OVH (**FTPS** recommandé).

### Secrets à configurer (dépôt GitHub → *Settings* → *Secrets and variables* → *Actions*)

| Secret | Description |
|--------|-------------|
| `OVH_FTP_HOST` | Hôte FTP (ex. `ftp.clusterXXX.hosting.ovh.net`) |
| `OVH_FTP_USER` | Identifiant FTP principal ou utilisateur FTP créé pour ce site |
| `OVH_FTP_PASSWORD` | Mot de passe FTP |
| `OVH_FTP_SERVER_DIR` | Dossier distant **avec /** final (ex. `www/` ou `./` selon ce que tu vois à la connexion FTP) |

En cas de **403** ou listing vide, ajuster `OVH_FTP_SERVER_DIR` (chemin racine vu par le compte FTP).

### Erreur GitHub Actions : `Input required and not supplied: server`

L’action FTP attend l’entrée `server`, alimentée par le secret **`OVH_FTP_HOST`**. Si ce secret n’existe pas ou est vide, le job échoue avec ce message.

**À faire** : dans le dépôt GitHub → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**, créer ou corriger les quatre secrets listés dans le tableau ci-dessus (noms **exactement** `OVH_FTP_HOST`, `OVH_FTP_USER`, `OVH_FTP_PASSWORD`, `OVH_FTP_SERVER_DIR`).

Si tu utilises des **secrets d’environnement** (Environment) au lieu des secrets du dépôt, il faut soit les dupliquer au niveau du dépôt, soit ajouter `environment: …` au job du workflow pour que ces secrets soient injectés.

### Fichiers non envoyés (uploads, cache, Git)

Le workflow **exclut** notamment `public/uploads` pour **ne pas effacer** les fichiers utilisateurs déjà en prod. Les chemins exclus sont listés dans le YAML ; adapte si tu utilises d’autres répertoires persistants.

## 5. Alternative : déploiement Git intégré OVH

Certaines offres OVH permettent de **lier le dépôt GitHub** au hébergement : push sur une branche → pull automatique côté serveur. Dans ce cas :

- tu n’utilises pas forcément l’action FTP ;
- il faut quand même **Composer** et **migrations** après mise à jour (cron SSH, ou pipeline externe).

Comparer avec la doc OVH « Git sur hébergement web » pour ton offre exacte.

## 6. APP_ENV=prod et détail des erreurs

Sur le serveur, dans **`.env.local`** (non versionné) :

```env
APP_ENV=prod
APP_DEBUG=0
```

- **`APP_ENV=prod`** : cache optimisé, pas de barre de debug Symfony, comportement production.
- **`APP_DEBUG=0`** : les visiteurs voient une page d’erreur générique (pas de stack trace publique).

**Ne pas mettre `APP_DEBUG=1` en prod ouverte au public** : toute la pile d’appel et des infos sensibles seraient visibles par n’importe qui.

### Où voir le détail des erreurs ?

Monolog en prod utilise le handler **fingers_crossed** : en cas d’erreur, le contexte (niveau debug) est écrit dans :

```text
var/log/prod.log
```

Consultation en SSH :

```bash
cd /homez.1544/lotopou/lpf26
tail -100 var/log/prod.log
```

Après changement de `.env.local` :

```bash
php bin/console cache:clear --env=prod
```

### Notifications push (VAPID)

Les push **serveur** (relances prono, buteur, messages admin) exigent trois variables dans **`.env.local` sur le serveur** (jamais dans Git) :

```env
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
VAPID_SUBJECT=mailto:no-reply@votredomaine.fr
```

Génération (une fois) :

```bash
php bin/console app:generate-vapid-keys
```

Copier **uniquement** les trois lignes `VAPID_*` dans `.env.local`, puis vider le cache prod.

**Important :** ne pas régénérer les clés en prod sans raison : les abonnements déjà enregistrés sur les téléphones deviennent invalides (erreur FCM 403). Les joueurs doivent alors rouvrir Mon compte → Notifications et réactiver le push sur chaque appareil.

Test :

```bash
php bin/console app:push-test-send
```

Pour déboguer ponctuellement (court moment, puis remettre `0`) : `APP_DEBUG=1` uniquement si tu acceptes l’exposition temporaire, ou reproduire l’erreur en local avec la même config.

## 7. Check-list après chaque livraison

- [ ] Migrations appliquées sur la BDD prod si le modèle a changé  
- [ ] Variables d’environnement à jour (mailer, `DATABASE_URL`, `APP_SECRET`, `APP_ENV=prod`, `APP_DEBUG=0`, etc.)  
- [ ] Cache prod vidé / réchauffé  
- [ ] Test rapide : page d’accueil, login, une action sensible (admin, pronos)  
- [ ] Sauvegarde BDD avant migration majeure  

## 8. Rollback

- **Code** : `git revert` sur `main`, push → redéploiement du workflow.  
- **BDD** : restaurer un dump OVH / point de restauration si la migration a échoué.

---

Pour toute adaptation (SSH + `rsync` au lieu de FTP, environnement de **préprod** sur un sous-domaine, etc.), duplique le workflow avec d’autres secrets (`OVH_FTP_*_STAGING`).

## 9. Dette technique : `composer.json` et `--no-dev`

Aujourd’hui, une partie des paquets nécessaires en production (Doctrine, Twig, Security, etc.) figurent dans **`require-dev`**. Tant que ce n’est pas corrigé, le workflow utilise **`composer install`** complet (y compris les dépendances de dev) pour que la console et le compile d’assets fonctionnent.

**Objectif prod** : déplacer vers **`require`** tout ce qui est chargé pour `APP_ENV=prod` (voir `config/bundles.php`), puis dans le workflow remplacer par :

`composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`

Cela réduit la surface d’attaque et la taille du déploiement.

## 10. Déploiement rapide depuis Cursor (SSH + SFTP OVH)

Si tu as **SSH** sur le mutualisé (en plus du SFTP), tu peux pousser le code **sans passer par GitHub Actions** depuis la machine locale :

1. **Une fois** : copier `.env.deploy.example` vers **`.env.deploy`** (fichier **ignoré par Git**) et renseigner `DEPLOY_SSH_HOST`, `DEPLOY_SSH_USER`, `DEPLOY_REMOTE_PATH` (chemin absolu du projet sur le serveur, là où se trouve `composer.json`). Optionnel : `DEPLOY_SSH_KEY`, `DEPLOY_PHP_BIN`, `DEPLOY_COMPOSER` si les chemins OVH diffèrent (`which composer` en SSH).

2. **SSH**  
   - **Avec clé** (recommandé) : ajoute ta clé publique dans l’espace client OVH ; teste :  
     `ssh -o BatchMode=yes TON_USER@TON_HOST "pwd"`  
   - **Mot de passe seulement** : dans **`.env.deploy`**, mets **`DEPLOY_SSH_PASSWORD_AUTH=1`**. Le script désactive `BatchMode` et réutilise une seule connexion SSH (tu ne saisis le mot de passe qu’**une fois** par déploiement). Lance **`./scripts/deploy-ovh.sh`** dans un **terminal interactif** (pas une tâche sans TTY), sinon la demande de mot de passe ne s’affiche pas correctement.

3. **Sur le serveur** : place une fois **`config/secrets`** ou **`.env.local.php`** avec `DATABASE_URL`, `APP_SECRET`, etc. (non synchronisés par le script).

4. **Dans Cursor** :  
   **Terminal → Run Task…** (ou `Cmd+Shift+B` / palette **Tasks: Run Task**) → choisir **Deploy OVH (SSH + rsync)**.  
   Variante **Deploy OVH (dry-run)** pour voir ce qui serait envoyé sans écrire sur le serveur.

Le script **`scripts/deploy-ovh.sh`** fait : test SSH → `rsync` (exclut `vendor/`, `var/`, `public/uploads/`, etc.) → `composer install` sur le serveur → `asset-map:compile` → `doctrine:migrations:migrate` → `cache:clear` / `cache:warmup`.

En ligne de commande depuis la racine du projet :

```bash
./scripts/deploy-ovh.sh
./scripts/deploy-ovh.sh --dry-run
```

**Composer sur OVH** : si la commande `composer` n’existe pas (souvent le cas), connecte-toi en SSH, va dans le dossier du site (`cd` vers `DEPLOY_REMOTE_PATH`) puis **une seule fois** :

```bash
curl -sS https://getcomposer.org/installer | php
# crée ./composer.phar dans ce dossier
```

Ensuite dans **`.env.deploy`** sur ton Mac :

```bash
DEPLOY_COMPOSER="php composer.phar"
```

(le script exécute déjà `cd` vers `DEPLOY_REMOTE_PATH` avant Composer.)
