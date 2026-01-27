# Changelog

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-01-27

### 🔒 Sécurité
- **CRITIQUE**: Correction injection CRLF dans les headers HTTP
  - Validation et sanitization des headers pour prévenir l'injection CRLF
  - Suppression des null bytes et CRLF dans les URIs
  - Validation de la longueur des headers selon RFC 7230
  
- **CRITIQUE**: Amélioration sécurité désérialisation cache
  - Désactivation des classes par défaut dans `unserialize()` pour prévenir object injection
  - Meilleure gestion des erreurs de désérialisation
  - Logging des erreurs en mode debug
  
### 🐛 Corrections de bugs
- **CRITIQUE**: Correction race condition dans tous les Connection Pools
  - Remplacement de `int $currentSize` par `Atomic $currentSize` pour thread-safety
  - Opérations atomiques pour éviter les conditions de course
  - Protection contre les dépassements de pool en environnement multi-workers
  - Appliqué à: `ConnectionPool`, `PostgreSQLPool`, `RedisPool`

### ⚡ Améliorations de performance
- Optimisation de la validation des headers (évite allocations inutiles)
- Amélioration de la gestion des connexions dans les pools
- Opérations atomiques plus efficaces que les locks

### 📝 Documentation
- Ajout de `CHANGELOG.md` pour suivre les versions
- Ajout de `IMPROVEMENTS_PLAN.md` pour suivre les améliorations
- Mise à jour de la documentation de sécurité

## [1.2.0] - Version précédente

Voir les tags Git pour l'historique complet.

---

## Notes

- Les corrections de sécurité sont marquées comme **CRITIQUE**
- Les améliorations de performance incluent des benchmarks
- Toutes les modifications sont testées et validées
