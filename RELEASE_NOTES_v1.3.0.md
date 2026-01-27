# Release Notes - Version 1.3.0

## 🎯 Vue d'ensemble

Cette version apporte des **corrections critiques de sécurité** et des **améliorations de stabilité** importantes pour le bundle Swoole.

## 🔒 Corrections de Sécurité Critiques

### 1. Protection contre l'injection CRLF
- **Problème**: Les headers HTTP n'étaient pas validés contre l'injection CRLF (`\r\n`)
- **Impact**: Vulnérabilité de sécurité permettant l'injection de headers malveillants
- **Solution**: Validation et sanitization complète des headers et URIs
- **Fichiers modifiés**: `src/Server/HttpServerManager.php`

### 2. Amélioration sécurité désérialisation cache
- **Problème**: Désérialisation avec `allowed_classes => true` permettait object injection
- **Impact**: Risque d'exécution de code arbitraire via objets sérialisés malveillants
- **Solution**: Désactivation des classes par défaut (`allowed_classes => false`)
- **Fichiers modifiés**: `src/Cache/SwooleCacheAdapter.php`

## 🐛 Corrections de Bugs Critiques

### 3. Race Conditions dans les Connection Pools
- **Problème**: `int $currentSize` n'était pas thread-safe en environnement multi-workers
- **Impact**: Conditions de course pouvant causer des dépassements de pool et des connexions perdues
- **Solution**: Remplacement par `Atomic $currentSize` pour opérations thread-safe
- **Fichiers modifiés**: 
  - `src/Database/ConnectionPool.php`
  - `src/Database/PostgreSQLPool.php`
  - `src/Database/RedisPool.php`

## ⚡ Améliorations de Performance

- Optimisation de la validation des headers (réduction allocations mémoire)
- Opérations atomiques plus efficaces que les locks traditionnels
- Meilleure gestion des erreurs de connexion

## 📋 Compatibilité

- ✅ Compatible avec Swoole 6.0+
- ✅ Compatible avec Symfony 7.0 et 8.0
- ✅ Compatible avec PHP 8.2, 8.3, 8.4

## 🔄 Migration

Aucune action requise pour la migration. Les corrections sont rétrocompatibles.

## 📝 Notes

- Les corrections de sécurité sont **critiques** et doivent être appliquées immédiatement
- Toutes les modifications ont été testées et validées
- Les pools de connexions sont maintenant thread-safe

## 🙏 Remerciements

Merci à tous les contributeurs et utilisateurs qui ont signalé ces problèmes.

---

**Date de release**: 2026-01-27  
**Version**: 1.3.0
