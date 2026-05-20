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
| Match test manuel (ponctuel) | `https://26.lotopotofoot.fr/cron/test-match-step?token=VOTRE_SECRET&match_id=ID&step=…` |

Ne pas utiliser `lotopotofoot.fr` ni `www.lotopotofoot.fr` (redirection vers l’ancien site).

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

## 4. cron-job.org — tâche 2 : relances prono

1. **Create cronjob**
2. **Title** : `LPF26 — relances pronostic`
3. **URL** : `https://26.lotopotofoot.fr/cron/pronostic-reminders?token=VOTRE_SECRET`
4. **Schedule** : toutes les **5 minutes** (`*/5 * * * *`)
5. **GET**, activé
6. Enregistrer

## 5. Vérifier les exécutions

Dans cron-job.org : onglet **History** de chaque tâche → statut **200** et corps JSON.

Sur le serveur :

```bash
tail -30 var/log/live-match-sync.log
```

## 6. Match test manuel (scénario France–Allemagne)

Voir le guide complet : [scenario-match-test-france-allemagne.md](scenario-match-test-france-allemagne.md).

Exemple (coup d’envoi) :

```text
https://26.lotopotofoot.fr/cron/test-match-step?token=VOTRE_SECRET&match_id=42&step=kickoff
```

Étapes : `info`, `reset-reminder`, `reminder`, `kickoff`, `goal` (+ `buteur_id`, `minute`), `finish`.

## 7. Dépannage

| Réponse | Cause |
|---------|--------|
| 403 Token invalide | `CRON_SECRET` différent entre URL et `.env.local` |
| 503 CRON_SECRET non configuré | Variable absente de `.env.local` ou cache non vidé |
| 302 / page login | Route `/cron/` non déployée ou cache prod ancien |
| 404 sur nouvelles pages admin | Cache prod obsolète → appeler `cron-cache-flush.php` (voir ci-dessus) |
| 500 | Voir `var/log/prod.log` |
