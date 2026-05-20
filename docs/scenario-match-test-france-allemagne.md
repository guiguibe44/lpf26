# Scénario match test — France vs Allemagne (15h, sans API)

Match manuel : **France (domicile) – Allemagne (extérieur)**, coup d’envoi **15:00** (Europe/Paris).

Outil : commande `app:test-match:step` ou cron HTTP `/cron/test-match-step`.

---

## 1. Préparation (avant le jour J)

### Match EasyAdmin

| Champ | Valeur |
|--------|--------|
| Pays domicile | France |
| Pays extérieur | Allemagne |
| Date/heure | Jour J à **15:00** |
| Statut | Programmé |
| Synchro API-Football | **Non** |
| Fixture API | vide |

Noter l’**ID du match** : `MATCH_ID=___`

### 3 équipes × 2 joueurs (cotisation payée)

| Équipe | Joueur 1 | Joueur 2 | Prono match | Buteur |
|--------|----------|----------|-------------|--------|
| **A** | **2-1** France | **1-1** | les deux avant 14h | **Dembélé** (FR) + **Havertz** (DE) |
| **B** | sans ligne jusqu’à la relance **14h**, puis **1-2** avant 15h | **aucune ligne** jusqu’au CO | relance J1+J2 ; seul J1 corrige | **Doué** (FR) + un buteur **hors** France/Allemagne |
| **C** | **2-1** France | **aucune ligne** | J2 relancé ; 0-0 auto au CO | au choix |

**Important — relance prono :** seuls les joueurs **sans aucune entrée** dans la table `Pronostic` pour ce match sont relancés. Un joueur qui n’a pas touché le formulaire mais a déjà une ligne (même vide) **ne sera pas** relancé.

### Buteurs pour les buts simulés

Choisir dans l’admin et noter les **ID** :

| Rôle | Exemple | ID |
|------|---------|-----|
| 1er but France (sélectionné) | ex. **Dembélé** | `BUTEUR_FR_1=` |
| But Allemagne (ex. Havertz sur grille A) | ex. **Havertz** | `BUTEUR_DE_1=` |
| 2e but France (ex. Doué sur grille B) | ex. **Doué** | `BUTEUR_FR_2=` |

Vérifier que le pays du buteur correspond au pays qui marque (France → domicile, Allemagne → extérieur).

---

## 2. Déroulé horaire (cron-job.org ou CLI)

Heures en **Europe/Paris**. Remplacer `MATCH_ID`, `BUTEUR_*`, `VOTRE_SECRET`, `BASE_URL`.

### Planning

| Heure | Étape | Effet attendu |
|-------|--------|----------------|
| **14:00** | `reminder` | E-mail/push aux **3 joueurs** sans ligne : **B-J1, B-J2, C-J2** |
| **15:00** | `kickoff` | Statut LIVE 0-0 ; **0-0 auto** pour **B-J2** et **C-J2** seulement |
| **15:23** | `goal` FR #1 | Score **1-0** ; points buteur si buteur choisi ; notif push |
| **15:45** | `goal` DE | Score **1-1** ; points buteur si `BUTEUR_DE_1` est coché (ex. Havertz sur A) |
| **16:07** (~67') | `goal` FR #2 | Score **2-1** |
| **17:05** | `finish` | FINISHED ; finalisation ; **classement** recalculé |

### Commandes CLI (local / SSH)

```bash
# Infos match (relance due à 14h pour CO 15h)
php bin/console app:test-match:step --match-id=MATCH_ID --step=info

# Veille de test : réinitialiser la relance si besoin
php bin/console app:test-match:step --match-id=MATCH_ID --step=reset-reminder

# 14:00 — relance pronos oubliés
php bin/console app:test-match:step --match-id=MATCH_ID --step=reminder

# 15:00 — coup d'envoi
php bin/console app:test-match:step --match-id=MATCH_ID --step=kickoff

# Buts
php bin/console app:test-match:step --match-id=MATCH_ID --step=goal --buteur-id=BUTEUR_FR_1 --minute=23
php bin/console app:test-match:step --match-id=MATCH_ID --step=goal --buteur-id=BUTEUR_DE_1 --minute=45
php bin/console app:test-match:step --match-id=MATCH_ID --step=goal --buteur-id=BUTEUR_FR_2 --minute=67

# Fin (scores déjà 2-1 via les buts)
php bin/console app:test-match:step --match-id=MATCH_ID --step=finish
```

### URLs cron-job.org (prod)

Tâches **ponctuelles** (schedule « once » ou cron à la date du test) :

```
# 14:00 — relances
BASE_URL/cron/test-match-step?token=SECRET&match_id=MATCH_ID&step=reminder

# 15:00 — coup d'envoi
BASE_URL/cron/test-match-step?token=SECRET&match_id=MATCH_ID&step=kickoff

# 15:23 — 1er but France
BASE_URL/cron/test-match-step?token=SECRET&match_id=MATCH_ID&step=goal&buteur_id=BUTEUR_FR_1&minute=23

# 15:45 — but Allemagne
BASE_URL/cron/test-match-step?token=SECRET&match_id=MATCH_ID&step=goal&buteur_id=BUTEUR_DE_1&minute=45

# 16:07 — 2e but France (67e minute)
BASE_URL/cron/test-match-step?token=SECRET&match_id=MATCH_ID&step=goal&buteur_id=BUTEUR_FR_2&minute=67

# 17:05 — fin
BASE_URL/cron/test-match-step?token=SECRET&match_id=MATCH_ID&step=finish
```

`BASE_URL` = `https://26.lotopotofoot.fr` en prod.

La tâche existante **relances pronostic** (`/cron/pronostic-reminders`, toutes les 5 min) enverra aussi la relance vers **14h** si le match est encore `SCHEDULED` et `pushReminderSentAt` est null — vous pouvez vous en servir **à la place** d’un cron dédié à 14h.

---

## 3. Vérifications après chaque étape

| Étape | Admin / front |
|--------|----------------|
| reminder | Historique **Relances prono** ; logs e-mail / push |
| kickoff | `/matchs` : match en direct ; oublis en 0-0 |
| goal | Admin **Buts** ; score carte match ; points prono recalculés |
| finish | `liveScoresFinalizedAt` renseigné ; **Classement** à jour |

Checklist interactive : `/admin/checklist-compet`.

---

## 4. Matrice de pronos (score final 2-1 France)

| Équipe | Prono | Résultat attendu (sans joker) |
|--------|-------|------------------------------|
| A – J1 | 2-1 | Score exact |
| A – J2 | 1-1 | Mauvais (nul prédit, victoire France réelle) |
| B – J1 | 1-2 | Mauvais (victoire extérieure prédite) |
| B – J2 | 0-0 (auto) | Mauvais |
| C – J1 | 2-1 | Score exact |
| C – J2 | 0-0 (auto) | Mauvais |

Ajuster selon vos jokers placés avant 15h.

---

## 5. Dépannage

| Problème | Cause probable |
|----------|----------------|
| Relance non envoyée | Joueur a déjà une ligne `Pronostic` ; ou `pushReminderSentAt` déjà set → `reset-reminder` |
| Relance trop tôt | CO à 15h → due à partir de **14h** seulement |
| Erreur synchro API | Désactiver synchro API sur le match |
| But refusé (pays) | Pays du buteur ≠ France ou Allemagne du match |
| Pas de notif but | Préférences push ; `--no-notify` sur la commande |

---

## 6. Fichier d’environnement (optionnel)

Copier `scripts/test-match-scenario.env.example` vers `scripts/test-match-scenario.env`, renseigner les IDs, puis :

```bash
source scripts/test-match-scenario.env
./scripts/test-match-scenario.sh info
```
