# Fonctionnalités d'Intelligence Artificielle — TunisiaCamp

> **Public visé** — Jury universitaire et évaluateurs techniques.
> **Périmètre** — Modules d'IA non conversationnels : Explicabilité, Matching de
> groupes, Tarification dynamique, Sécurité, Intelligence météo, Recommandation
> d'équipement, Moteur de recommandation, Profils comportementaux.
> *Le planificateur de voyage (Trip Planner) et le chatbot conversationnel sont
> hors périmètre.*

---

## Synthèse pour le jury

TunisiaCamp implémente une couche d'IA selon un principe d'architecture clair et
défendable : **« le PHP décide, le LLM rédige »**.

- **Toutes les décisions** (quelle zone, quel équipement, quel prix, quel niveau
  de risque) sont calculées de façon **déterministe** en PHP, à l'aide de règles
  métier explicites et d'algorithmes de *machine learning* classiques (similarité
  cosinus, K-Means, DBSCAN, filtrage collaboratif).
- **Le LLM (Groq)** n'intervient que pour **traduire un résultat structuré en
  langage naturel français**. Il ne choisit jamais une entité, ne calcule jamais
  un nombre, ne décide jamais de la sécurité.

**Avantages pédagogiques et techniques de ce choix :** reproductibilité,
testabilité, robustesse (chaque service possède un repli déterministe et un mode
`mock` sans API), et explicabilité native (chaque décision est traçable).

**Techniques mobilisées :** similarité cosinus, similarité de Jaccard,
clustering K-Means (initialisation K-Means++) et DBSCAN, distance euclidienne,
filtrage collaboratif, ingénierie de caractéristiques (vectorisation
normalisée), moteurs de règles pondérées, génération de texte par LLM.

**Modèles / APIs externes :** Groq (LLM, `llama-3.3-70b-versatile`),
OpenWeatherMap (prévisions météo côté serveur), Open-Meteo (widget météo côté
navigateur).

**Limite principale :** le back-end est **complet et de qualité production**,
mais il est aujourd'hui **« sans tête » (headless)** : hormis le chatbot et le
formulaire de profil, l'interface React ne consomme pas encore ces endpoints.

---

## Vue d'ensemble du flux de données

```
ENTRÉE                INGÉNIERIE DE          DÉCISION (PHP)         RÉDACTION        SORTIE
(profil, activité,    CARACTÉRISTIQUES       règles + ML            (LLM, option.)   (DTO → JSON)
 zone, météo)    ─▶   vecteurs normalisés ─▶ cosinus / K-Means ─▶  phrase FR     ─▶ avec score +
                      signaux comportement.  scores pondérés        (repli règle)     confiance
```

Chaque module suit ce même pipeline. Le LLM est toujours **optionnel** : en cas
d'échec ou en mode `mock`, un repli déterministe produit une réponse correcte.

---

## 1. Moteur de Recommandation

**Objectif** — Classer les zones de camping et les équipements selon le profil du
campeur.

**Entrées**
- Profil statique (`profile_campeurs`) : niveau, confort, budget, styles et
  activités préférés, nombre de sorties.
- Profil comportemental inféré (voir §8), fusionné si fiabilité suffisante.
- Entités : zones (`camping_zones`), équipements (`materielles`).

**Traitement**
- **Approche contenu (similarité cosinus).** Construction d'un **vecteur
  utilisateur à 6 dimensions** (niveau, budget, poids terrain, richesse
  d'activités, expérience, confiance comportementale) comparé à un **vecteur
  zone à 6 dimensions** (difficulté, note, correspondance terrain relative à
  l'utilisateur, recouvrement d'activités via **Jaccard**, accessibilité, preuve
  sociale). L'équipement utilise des vecteurs à 4 dimensions.
- **Filtrage collaboratif.** Recherche des utilisateurs au même niveau + budget,
  agrégation de leurs avis approuvés ; bonus aux zones bien notées par des
  profils similaires.
- **Mélange final :** `score = cosinus × 0,7 + collaboratif × 0,3`. Top 5 zones,
  top 10 équipements.

**Sorties** — Liste classée avec `score_breakdown` (détail des vecteurs et
contributions) et une **explication** rattachée à chaque zone.

**Valeur métier** — Personnalisation au cœur de la découverte et de la
conversion en réservation.

---

## 2. Profils Comportementaux

**Objectif** — Remplacer les champs de profil déclarés (souvent obsolètes) par
des préférences **inférées de l'activité réelle**.

**Entrées** — Réservations de centres, locations d'équipement, avis approuvés,
favoris.

**Traitement** — Calcul de **6 signaux** :
1. Niveau inféré (à partir de `group_skill_level` des réservations) ;
2. Budget inféré (dépense moyenne) ;
3. Terrain préféré (score net avis + favoris par type de terrain) ;
4. Besoin d'équipement (locations vs sorties seules) ;
5. Taille de groupe moyenne ;
6. **Score de confiance** (volume d'activité + bonus avis/favoris).

La fusion `mergeWithStatic()` fait primer le comportemental sur le statique
**lorsque la confiance ≥ 0,4**. Mise en cache (1 h) avec **invalidation
automatique** via des *observers* (réservation, location, avis, favori).

**Sorties** — Un DTO `BehavioralProfile` consommé par le moteur de
recommandation.

**Valeur métier** — Recommandations toujours à jour sans nouvelle saisie de
l'utilisateur.

---

## 3. Recommandation d'Équipement (Assistant matériel)

**Objectif** — Générer une checklist d'équipement adaptée au terrain, à la météo
et au niveau, reliée aux articles réels du marketplace.

**Entrées** — Zone (terrain), prévisions météo (risque), profil (niveau), taille
du groupe.

**Traitement** — **Matrice de décision** combinant des règles : base (toujours) +
terrain + météo + niveau → ensemble de **catégories requises** (déduplication par
priorité). Une seule requête récupère les `materielles` correspondants ;
détection des **catégories critiques de sécurité manquantes**
(`is_safety_critical`). Enrichissement optionnel des conseils par LLM.

**Sorties** — `GearChecklist` (articles, raisons, conseils, priorités,
`missing_critical`) + alerte si équipement de sécurité indisponible.

**Valeur métier** — Sécurité du campeur et vente croisée du marketplace.

---

## 4. Intelligence Météo

**Objectif** — Récupérer et **évaluer le risque** des prévisions à 3 jours pour
les coordonnées d'une zone.

**Entrées** — Latitude/longitude de la zone.

**APIs** — **OpenWeatherMap** `/forecast` (côté serveur). *Remarque : le widget
du navigateur utilise directement **Open-Meteo**, une implémentation parallèle.*

**Traitement** — Agrégation des créneaux de 3 h en résumés **journaliers**
(temp min/max, vent max, précipitations cumulées, humidité, condition dominante),
puis évaluation d'un **niveau de risque** sur 4 paliers :
- **extrême** : orage, vent > 20 m/s ;
- **élevé** : précip > 20 mm, vent > 12, gel < 2 °C, chaleur > 40 °C ;
- **modéré** : pluie > 5 mm, vent > 8, nuits < 8 °C, chaleur > 35 °C, neige.

**Sorties** — `WeatherForecast` (jours + facteurs de risque en français), niveau
global, indicateur d'alerte, résumé court pour les prompts LLM.

**Valeur métier** — Sécurité, confiance, pertinence de l'équipement. Mise en
cache 3 h, limitation de débit (50/min, 900/jour), repli silencieux.

---

## 5. Moteur de Sécurité

**Objectif** — (A) Évaluer le risque d'une sortie ; (B) **modérer** le contenu
des annonces.

**Entrées** — (A) Profil × zone × météo × taille de groupe ; (B) titre,
description, catégorie, prix d'une annonce.

**Traitement (A — évaluation)** — **5 moteurs de règles** ajoutant des
`RiskFactor` : incompatibilité de niveau, niveau de danger de la zone, sortie en
solo, risque météo, incompatibilité de confort. **Score pondéré** par sévérité
(low 5 / modéré 15 / élevé 30 / extrême 50, plafonné à 100) → **libellé** : `safe`
(≤15), `caution` (≤35), `warning` (≤65), `danger`. Résumé rédigé par LLM (repli
par modèle de phrase).

**Traitement (B — modération)** — Pipeline étagé : mots-clés interdits → rejet ;
motifs suspects (description courte, prix 0 ou > 500…) ; contenu propre →
approuvé **sans appel LLM** ; contenu suspect → modération LLM (JSON strict).
Compteurs statistiques en cache.

**Sorties** — `SafetyAssessment` (score, libellé, facteurs, résumé) ;
`ModerationResult` (statut, raisons, suggestions, confiance).

**Valeur métier** — Réduction du risque juridique, qualité du marketplace,
confiance. Intégration météo : l'évaluation incorpore les facteurs de risque
météo dans le même score.

---

## 6. Tarification Dynamique

**Objectif** — Suggérer une fourchette de prix optimale aux fournisseurs et
fournir des aperçus de marché.

**Entrées (`DemandSignal`)** — Réservations et favoris des 30 derniers jours,
note moyenne, **prix moyen de la catégorie**, prix actuel, **saison**, tags
tendances (`trip_purpose` les plus fréquents).

**Traitement** — Niveau de demande : `score = réservations × 3 + favoris × 1`
(peak ≥20 / high ≥10 / modéré ≥4 / low). **Modèle multiplicatif** :
`optimal = base × mult_demande × mult_saison × mult_note`. Calcul d'une
**direction** (augmenter/diminuer/maintenir), d'un **score de confiance** et de
recommandations d'action. Explication rédigée par LLM (repli par règle).

**Sorties** — `PricingSuggestion` (min/optimal/max, niveau de demande, confiance,
direction, explication, actions).

**Valeur métier** — Optimisation des revenus fournisseurs, liquidité du marché.

> **Limite (données)** — Les zones n'ont pas de colonne de prix : la tarification
> de zone reste donc un espace réservé. La tarification de l'équipement est
> pleinement fonctionnelle.
> **Méthode** — Il s'agit d'une **heuristique sur 30 jours**, *non* d'une
> prévision (forecasting) par série temporelle.

---

## 7. Matching de Groupes

**Objectif** — Regrouper les campeurs par affinité et recommander des groupes
compatibles.

**Entrées** — Profils campeurs.

**Traitement**
- **Vectorisation** : chaque profil → **vecteur à 6 dimensions** normalisées
  (niveau, confort, budget, sorties, nb de styles, nb d'activités).
- **Clustering K-Means** (`k = 4`, initialisation **K-Means++**, distance
  euclidienne, convergence à 0,001).
- **DBSCAN** (`ε = 0,3`, `minPts = 2`) en parallèle pour détecter les
  **points aberrants** (autorisés à matcher entre clusters).
- **Classement par similarité cosinus** des vecteurs, filtré au cluster de
  l'utilisateur ; calcul des **traits communs** ; pour le top 3 (similarité
  > 0,7), **blurb de compatibilité rédigé par LLM**.
- Étiquette de cluster déduite (« Campeurs Aventuriers », « Glamping »…) et
  mesure de **cohésion** (distance intra-cluster moyenne).

**Sorties** — Liste de `GroupMatch` (score, % de compatibilité, traits communs,
explication) ; statistiques de clusters (admin).

**Valeur métier** — Engagement social, réservations de groupe, fidélisation.

> **À noter** — L'interface actuelle (`ZoneClustersModal`) affiche des **données
> fictives** et n'est pas reliée à ces endpoints.

---

## 8. Explicabilité

**Objectif** — Produire, pour chaque sortie d'IA, une explication « pourquoi »,
une liste de facteurs et un score de confiance.

**Entrées** — Le résultat structuré d'un autre module (ventilation de score,
facteurs de risque, prévisions…).

**Traitement**
- **Par trace de règles (par défaut)** — 6 explicateurs typés projettent
  déterministiquement le DTO source en facteurs lisibles
  (recommandation, météo, sécurité, équipement, groupe, tarification).
- **Par LLM (à la demande uniquement)** — `explainOnDemand()` interroge le LLM
  avec un contrat JSON strict (`{why, factors[]}`), mis en cache 1 h.
- **Confiance** déterministe et propre à chaque source (ex. météo 0,9 en réel /
  0,6 en mock ; sécurité `1 − score/100`).

**Sorties** — DTO `Explanation` (`why`, `factors`, `confidence`, `source`,
`llmEnriched`).

**Valeur métier** — Transparence, confiance, différenciation, valeur académique.

---

## Mécanismes transversaux

| Mécanisme | Mise en œuvre |
|---|---|
| **Stratégie de fournisseur** | `LLMAdapterInterface` (Groq/Mock) et `WeatherAdapterInterface` (OpenWeatherMap/Mock), injectés via `AppServiceProvider`. |
| **Indicateurs de fonctionnalité** | `config/ai.php` (météo, gear, matching, pricing, safety, explicabilité…). |
| **Limitation de débit** | `RateLimitService` (compteurs) + *throttle* Laravel (`ai` 10/min, `weather` 30/min, `safety` 60/min). |
| **Mise en cache** | `Cache::remember` partout (prévisions 3 h, tarifs 1 h, sécurité 30 min, modération 24 h, clusters 1 h, profils 1 h). |
| **Observabilité** | Journaux structurés `Log::info/warning/error` à chaque décision. |
| **Résilience** | Méthodes non bloquantes, repli déterministe, mode `mock`. |

---

## Mécanismes d'IA — récapitulatif par catégorie

- **Recommandation** : similarité cosinus (contenu) + filtrage collaboratif,
  mélange pondéré 0,7 / 0,3, enrichi par profil comportemental.
- **Sécurité** : moteurs de règles pondérées par sévérité ; pipeline de
  modération étagé (mots-clés → motifs → LLM).
- **Matching** : K-Means++ + DBSCAN, classement par cosinus, étiquetage et
  cohésion de clusters.
- **Tarification** : modèle multiplicatif piloté par signaux de demande
  (heuristique 30 jours + saison + note).
- **Explicabilité** : trace de règles déterministe + explicateur LLM à la demande.

---

## Limites et pistes d'amélioration (pour la discussion)

1. **Intégration front-end** absente pour la plupart des modules (back-end
   « headless »).
2. **Pas de forecasting** réel en tarification (heuristique de fenêtre glissante).
3. **Modération non déclenchée automatiquement** à la création d'annonce.
4. **Colonne de prix manquante** sur les zones (tarification de zone inerte).
5. **Double source météo** (OpenWeatherMap serveur vs Open-Meteo navigateur).
6. **Reclustering manuel** (pas de planification automatique).

> Ces points sont détaillés et priorisés dans `AI_FEATURE_GAP_ANALYSIS.md`.
> L'inventaire technique complet figure dans `AI_IMPLEMENTATION_AUDIT.md`, et la
> procédure de test dans `AI_TESTING_GUIDE.md`.

---

## POINTS À CLARIFIER

1. Le caractère « headless » du back-end est-il assumé pour cette version
   (livrable API/académique) ou une interface est-elle attendue ?
2. Le widget météo doit-il consommer le modèle de risque du back-end plutôt
   qu'Open-Meteo ?
3. La modération doit-elle s'exécuter automatiquement à la soumission d'annonces ?
4. Une colonne de prix est-elle prévue pour les zones ?
5. Le statut `blocks_booking` doit-il réellement bloquer une réservation
   « danger » ?
6. Existe-t-il une suite de tests automatisés pour ces services ? (aucune
   trouvée dans le périmètre inspecté.)
