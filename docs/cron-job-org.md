# Configuration cron-job.org (LPF26)

## 1. Secret sur le serveur (une fois)

En SSH sur OVH :

```bash
openssl rand -hex 32
```

Dans `/homez.1544/lotopou/lpf26/.env.local` :

```env
CRON_SECRET=coller_la_valeur_générée
```

Puis :

```bash
php bin/console cache:clear --env=prod
```

Remplace `VOTRE_SECRET` ci-dessous par cette même valeur.

## 2. URLs à appeler

Base du site prod : **`https://26.lotopotofoot.fr`** (sans `/l` — pas de préfixe de chemin).

| Tâche | URL |
|--------|-----|
| **Vider le cache Symfony après déploiement FTP** (ponctuel) | `https://26.lotopotofoot.fr/cron-cache-flush.php?token=VOTRE_SECRET` |
| Synchro scores / buts (3 min) | `https://26.lotopotofoot.fr/cron/live-match-sync?token=VOTRE_SECRET` |
| Relances pronostic (5 min) | `https://26.lotopotofoot.fr/cron/pronostic-reminders?token=VOTRE_SECRET` |
| Match test manuel (ponctuel, **une URL par étape**) | `https://26.lotopotofoot.fr/cron/test-match-step?token=VOTRE_SECRET&match_id=ID&step=…` |

Ne pas utiliser `lotopotofoot.fr` ni `www.lotopotofoot.fr` (redirection vers l’ancien site).

### Pas à pas : que faire exactement dans cron-job.org

1. **Créer un compte** sur [cron-job.org](https://cron-job.org) (gratuit) et te connecter.
2. Menu **« Cronjobs »** → bouton **« Create cronjob »** (ou équivalent « Nouvelle tâche »).
3. Remplir les champs (les noms peuvent varier un peu selon la version du site) :
   - **Title** : un libellé pour toi, ex. `LPF26 synchro live` (ça n’affecte pas le site).
   - **Address / URL** : colle **toute** l’adresse, **y compris** `?token=…` à la fin. Le `token` doit être **exactement** le même que `CRON_SECRET` dans le `.env.local` du serveur OVH (pas d’espace en trop).
   - **Schedule** (planning) :
     - pour la **synchro live** : choisir une exécution **toutes les 3 minutes** (souvent « Custom » / expression cron : `*/3 * * * *`), ou l’équivalent proposé par l’interface ;
     - pour les **relances prono** : **toutes les 5 minutes** (`*/5 * * * *`).
   - **Request method** : **GET** (pas POST).
   - **Timezone** : si le site le propose, choisir **Europe/Paris** (important surtout pour des tâches à une heure précise le jour J).
4. **Enregistrer** la tâche (Save). Elle apparaît dans la liste des Cronjobs.
5. **Vérifier** : ouvre la tâche → onglet **« History »** / **« Executions »** : tu dois voir des lignes en **statut 200** et un corps de réponse en **JSON** (pas une page HTML de login).

**En résumé** : **une ligne dans la liste = une URL + un planning**. Tes deux crons = **deux lignes** (deux tâches), chacune avec sa propre URL et son propre intervalle.

**Pour un match test** (un seul jour, plusieurs actions à des heures différentes) : c’est la même procédure, mais tu **crées plusieurs tâches** (ex. 6), une par étape, chacune avec la **bonne URL** (`step=reminder`, puis `step=kickoff`, etc. — voir § 6 plus bas) et un **horaire différent** le jour du test. Après le test, **désactive** ou **supprime** ces tâches-là pour qu’elles ne se relancent pas tout seuls.

### Après chaque déploiement FTP (404, nouvelles routes absentes)

Le workflow GitHub **n’envoie pas** `var/cache`. Tant que le cache prod n’est pas vidé, Symfony peut répondre **404** sur les nouvelles pages (ex. `/admin/checklist-compet`) et servir d’anciens templates (icônes manquantes).

**À faire une fois** après un push (navigateur ou curl) :

```bash
curl -sS "https://26.lotopotofoot.fr/cron-cache-flush.php?token=VOTRE_SECRET"
```

Réponse attendue : `{"ok":true,...}`. Puis recharger le site (le premier chargement peut prendre quelques secondes).

Test (doit renvoyer du JSON, pas une page login) :

```bash
curl -sS "https://26.lotopotofoot.fr/cron/live-match-sync?token=VOTRE_SECRET"
```

## 3. cron-job.org — tâche 1 : synchro live

1. **Cronjobs** → **Create cronjob**
2. **Title** : `LPF26 — synchro matchs API`
3. **URL** : `https://26.lotopotofoot.fr/cron/live-match-sync?token=VOTRE_SECRET`
4. **Schedule** : toutes les **3 minutes** (ou expression `*/3 * * * *`)
5. **Request method** : **GET**
6. **Enabled** : oui
7. **Notify on failure** : oui (e-mail) au début
8. Enregistrer

## 4. cron-job.org — tâche 2 : relances prono (+ coup d’envoi auto)

1. **Create cronjob**
2. **Title** : `LPF26 — relances pronostic`
3. **URL** : `https://26.lotopotofoot.fr/cron/pronostic-reminders?token=VOTRE_SECRET`
4. **Schedule** : toutes les **5 minutes** (`*/5 * * * *`)
5. **GET**, activé
6. Enregistrer

À chaque exécution, ce cron fait aussi le **coup d’envoi** des matchs encore « Programmé » dont l’heure est passée : statut **LIVE**, score **0-0**, pronos **0-0** pour les joueurs cotisés sans ligne. **Pas besoin** d’un cron `kickoff` séparé pour le match test si cette tâche est active.

## 5. Vérifier les exécutions

Dans cron-job.org : onglet **History** de chaque tâche → statut **200** et corps JSON.

Sur le serveur :

```bash
tail -30 var/log/live-match-sync.log
```

## 6. Crons dédiés « match test » (cron-job.org)

Les deux crons permanents (`live-match-sync`, `pronostic-reminders`) ne suffisent pas à enchaîner un match manuel : il faut appeler **`/cron/test-match-step`** à des heures précises, **une étape par requête**.

### Principe

1. **Créer une tâche cron-job.org par étape** (souvent **6** : `reminder`, `kickoff`, 3× `goal`, `finish`) — même `CRON_SECRET` que les autres URLs.
2. **Méthode** : **GET** (comme les autres crons LPF26).
3. **Fuseau** : régler le job sur **Europe/Paris** dans cron-job.org si l’option existe.
4. **Après le test** : **désactiver ou supprimer** ces tâches, sinon elles se réexécutent au même horaire (ex. le lendemain ou l’année suivante selon l’expression).

### Planning type (à adapter au jour J)

| Titre suggéré (cron-job.org) | Heure (Paris) | Paramètres `step` + extras |
|------------------------------|---------------|----------------------------|
| `LPF26 test — reminder` | ex. 14:00 | `step=reminder` |
| `LPF26 test — kickoff` | ex. 15:00 | `step=kickoff` |
| `LPF26 test — goal FR1` | ex. 15:23 | `step=goal&buteur_id=…&minute=23` |
| `LPF26 test — goal DE` | ex. 15:45 | `step=goal&buteur_id=…&minute=45` |
| `LPF26 test — goal FR2` | ex. 16:07 | `step=goal&buteur_id=…&minute=67` |
| `LPF26 test — finish` | ex. 17:05 | `step=finish` |

### Liste complète des 6 tâches à créer (copier-coller)

**Base** : `https://26.lotopotofoot.fr` — **GET** — fuseau **Europe/Paris**. Remplacer `VOTRE_SECRET`, `MATCH_ID`, `BUTEUR_FR_1`, `BUTEUR_DE_1`, `BUTEUR_FR_2`.

| # | Title (cron-job.org) | Heure (Paris) | URL |
|---|------------------------|---------------|-----|
| 1 | `LPF26 test — reminder` | 14:00 jour J | `https://26.lotopotofoot.fr/cron/test-match-step?token=VOTRE_SECRET&match_id=MATCH_ID&step=reminder` |
| 2 | ~~`LPF26 test — kickoff`~~ | ~~15:00~~ | **Inutile** si `pronostic-reminders` tourne toutes les 5 min : coup d’envoi auto (LIVE + 0-0) à l’heure du match. Garder seulement pour forcer à la main. |
| 3 | `LPF26 test — goal FR1` | 15:23 | `https://26.lotopotofoot.fr/cron/test-match-step?token=VOTRE_SECRET&match_id=MATCH_ID&step=goal&buteur_id=BUTEUR_FR_1&minute=23` |
| 4 | `LPF26 test — goal DE` | 15:45 | `https://26.lotopotofoot.fr/cron/test-match-step?token=VOTRE_SECRET&match_id=MATCH_ID&step=goal&buteur_id=BUTEUR_DE_1&minute=45` |
| 5 | `LPF26 test — goal FR2` | 16:07 | `https://26.lotopotofoot.fr/cron/test-match-step?token=VOTRE_SECRET&match_id=MATCH_ID&step=goal&buteur_id=BUTEUR_FR_2&minute=67` |
| 6 | `LPF26 test — finish` | 17:05 | `https://26.lotopotofoot.fr/cron/test-match-step?token=VOTRE_SECRET&match_id=MATCH_ID&step=finish` |

**Exemple d’expressions cron** (une par tâche), pour un test le **20 mai** (jour **20**, mois **5**) — adapter jour/mois à ton calendrier :

| # | Expression (`minute heure jour mois *`) |
|---|----------------------------------------|
| 1 | `0 14 20 5 *` |
| 2 | *(optionnel — voir ligne kickoff ci-dessus)* |
| 3 | `23 15 20 5 *` |
| 4 | `45 15 20 5 *` |
| 5 | `7 16 20 5 *` |
| 6 | `5 17 20 5 *` |

Tu peux **omettre la tâche 1** si la relance est déjà couverte par ton cron permanent **`pronostic-reminders`** (voir § ci-dessous).

URL modèle (remplacer `VOTRE_SECRET`, `MATCH_ID`, IDs buteurs) :

```text
https://26.lotopotofoot.fr/cron/test-match-step?token=VOTRE_SECRET&match_id=MATCH_ID&step=kickoff
```

Pour **générer les URLs complètes** en local (secret uniquement dans le shell, pas dans Git) :

```bash
source scripts/test-match-scenario.env
export CRON_SECRET='…'
export BASE_URL='https://26.lotopotofoot.fr'
chmod +x scripts/print-test-match-cron-urls.sh
./scripts/print-test-match-cron-urls.sh
```

### Expression cron (exemple)

Pour un test le **25 mai** à **14h00** uniquement : `0 14 25 5 *` (minute 0, heure 14, jour 25, mois 5). Vérifier dans l’UI cron-job.org que le fuseau est bien **Paris**. Pensez à **désactiver** le job après succès pour ne pas relancer le 25 mai suivant.

### Rappel : relance 14h sans job dédié

La tâche existante **`pronostic-reminders` (toutes les 5 min)** peut envoyer la relance vers **14h** si le match est encore programmé et la relance pas encore marquée — voir [scenario-match-test-france-allemagne.md](scenario-match-test-france-allemagne.md).

### Guide scénario (pronos, équipes, vérifs)

Détail du déroulé France–Allemagne et matrice des points : [scenario-match-test-france-allemagne.md](scenario-match-test-france-allemagne.md).

Étapes HTTP / CLI : `info`, `reset-reminder`, `reminder`, `kickoff`, `goal` (+ `buteur_id`, `minute`), `finish`. Paramètres optionnels : `now`, `dry_run`, `no_notify`, `score_home`, `score_away` (voir `CronController`).

## 7. Dépannage

| Réponse | Cause |
|---------|--------|
| 403 Token invalide | `CRON_SECRET` différent entre URL et `.env.local` |
| 503 CRON_SECRET non configuré | Variable absente de `.env.local` ou cache non vidé |
| 302 / page login | Route `/cron/` non déployée ou cache prod ancien |
| 404 sur nouvelles pages admin | Cache prod obsolète → appeler `cron-cache-flush.php` (voir ci-dessus) |
| 500 | Voir `var/log/prod.log` |
