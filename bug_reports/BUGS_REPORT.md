# 🐛 Rapport de bugs - toadbeatz/swoole-bundle v1.0.0

Ce document liste les bugs découverts lors des tests du bundle avec Swoole 6.1.4 et Symfony 7.4.

## 📊 Résumé

| # | Sévérité | Fichier | Description |
|---|----------|---------|-------------|
| 1 | 🔴 CRITIQUE | `Cache/SwooleCacheItem.php` | Méthodes manquantes `tag()` et `getMetadata()` |
| 2 | 🔴 CRITIQUE | `HttpClient/SwooleResponseStream.php` | Signature `current()` incompatible |
| 3 | 🟠 MAJEUR | `Resources/config/services.yaml` | HotReloadWatcher non configuré |
| 4 | 🟠 MAJEUR | `Server/SwooleServer.php` | API Swoole 6.1 incompatible (`enableCoroutine`) |
| 5 | 🟠 MAJEUR | `Command/*.php` | Commandes sans attribut `#[AsCommand]` |
| 6 | 🟠 MAJEUR | `Server/SwooleServer.php` | Options HTTP/2 non supportées par Swoole 6.1 |
| 7 | 🔴 CRITIQUE | `Server/HttpServerManager.php` | Callbacks `onTask`/`onFinish` manquants |

---

## 🔴 BUG #1 - SwooleCacheItem : méthodes manquantes

**Fichier:** `src/Cache/SwooleCacheItem.php`

**Problème:** La classe implémente `Symfony\Contracts\Cache\ItemInterface` mais il manque les méthodes `tag()` et `getMetadata()`.

**Erreur:**
```
Class Toadbeatz\SwooleBundle\Cache\SwooleCacheItem contains 2 abstract methods and must therefore be declared abstract or implement the remaining methods (Symfony\Contracts\Cache\ItemInterface::tag, Symfony\Contracts\Cache\ItemInterface::getMetadata)
```

**Fix requis:**
```php
private array $tags = [];

public function tag(string|iterable $tags): static
{
    if (is_string($tags)) {
        $tags = [$tags];
    }
    foreach ($tags as $tag) {
        $this->tags[$tag] = $tag;
    }
    return $this;
}

public function getMetadata(): array
{
    return [
        'expiry' => $this->ttl ? time() + $this->ttl : null,
        'ctime' => time(),
        'tags' => array_values($this->tags),
    ];
}
```

---

## 🔴 BUG #2 - SwooleResponseStream : signature incompatible

**Fichier:** `src/HttpClient/SwooleResponseStream.php`

**Problème:** La méthode `current()` retourne `ResponseInterface` au lieu de `ChunkInterface` comme requis par `ResponseStreamInterface`.

**Erreur:**
```
Declaration of Toadbeatz\SwooleBundle\HttpClient\SwooleResponseStream::current(): Symfony\Contracts\HttpClient\ResponseInterface must be compatible with Symfony\Contracts\HttpClient\ResponseStreamInterface::current(): Symfony\Contracts\HttpClient\ChunkInterface
```

**Fix requis:**
1. Modifier le type de retour de `current()` pour `ChunkInterface`
2. Créer une classe `SwooleChunk` implémentant `ChunkInterface`

---

## 🟠 BUG #3 - HotReloadWatcher : configuration manquante

**Fichier:** `src/Resources/config/services.yaml`

**Problème:** Le service `HotReloadWatcher` n'a pas ses arguments configurés explicitement.

**Erreur:**
```
Cannot autowire service "Toadbeatz\SwooleBundle\HotReload\HotReloadWatcher": argument "$watchPaths" of method "__construct()" is type-hinted "array", you should configure its value explicitly.
```

**Fix requis:** Ajouter dans `services.yaml`:
```yaml
Toadbeatz\SwooleBundle\HotReload\HotReloadWatcher:
    arguments:
        $watchPaths: '%swoole.hot_reload.watch%'
        $enabled: '%swoole.hot_reload.enabled%'
```

---

## 🟠 BUG #4 - API Swoole 6.1 incompatible

**Fichier:** `src/Server/SwooleServer.php` (ligne 61)

**Problème:** `Runtime::enableCoroutine()` dans Swoole 6.1 n'accepte qu'un seul argument.

**Erreur:**
```
Swoole\Runtime::enableCoroutine() expects at most 1 argument, 2 given
```

**Code actuel (incorrect):**
```php
Runtime::enableCoroutine($hookFlags, true);
```

**Fix requis:**
```php
Runtime::enableCoroutine($hookFlags);
```

---

## 🟠 BUG #5 - Commandes Symfony 7+ incompatibles

**Fichiers:** `src/Command/ServerStartCommand.php`, `ServerStopCommand.php`, `HotReloadCommand.php`

**Problème:** Les commandes utilisent des propriétés statiques `$defaultName` qui ne fonctionnent plus avec Symfony 7+.

**Erreur:**
```
The command defined in "Toadbeatz\SwooleBundle\Command\ServerStopCommand" cannot have an empty name.
```

**Fix requis:** Utiliser l'attribut PHP 8:
```php
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'swoole:server:stop',
    description: 'Stop the Swoole HTTP server'
)]
class ServerStopCommand extends Command
{
    // Supprimer $defaultName et $defaultDescription
}
```

---

## 🟠 BUG #6 - Options HTTP/2 non supportées

**Fichier:** `src/Server/SwooleServer.php` (lignes 128-132)

**Problème:** Swoole 6.1 ne supporte pas les options HTTP/2 granulaires.

**Erreur:**
```
unsupported option [http2_initial_window_size]
```

**Options problématiques:**
```php
$serverOptions['http2_header_table_size']
$serverOptions['http2_initial_window_size']
$serverOptions['http2_max_concurrent_streams']
$serverOptions['http2_max_frame_size']
$serverOptions['http2_max_header_list_size']
```

**Fix requis:** Supprimer ou conditionner ces options pour Swoole 6.1+

---

## 🔴 BUG #7 - Callbacks onTask manquants

**Fichier:** `src/Server/HttpServerManager.php`

**Problème:** Quand `task_worker_num > 0`, Swoole exige les callbacks `onTask` et `onFinish`.

**Erreur:**
```
Swoole\Server::start(): failed to start server. Error: Server::start_check() (ERRNO 9015): require 'onTask' callback
```

**Fix requis:** Ajouter dans `initialize()`:
```php
$server->on('task', function ($server, $taskId, $fromId, $data) {
    if (is_callable($data)) {
        return $data();
    }
    return $data;
});

$server->on('finish', function ($server, $taskId, $data) {
    // Task completed
});
```

---

## 📁 Scripts de patch créés

Les patches temporaires suivants ont été créés dans ce projet pour tester :

- `patch_cache_item.php` - Fix BUG #1
- `patch_response_stream.php` - Fix BUG #2
- `patch_services.php` - Fix BUG #3
- `patch_swoole_server.php` - Fix BUG #4
- `patch_commands.php` - Fix BUG #5
- `patch_http2_options.php` - Fix BUG #6
- `patch_task_callbacks.php` - Fix BUG #7

---

## 🧪 Comment tester

```bash
# Setup complet
make setup

# Démarrer le serveur
make start

# Tester les fonctionnalités
make test-bundle

# Debug
make debug
```

---

## 📋 Recommandations

1. **Compatibilité Swoole 6.1+**: Tester avec différentes versions de Swoole
2. **Compatibilité Symfony**: Utiliser les attributs PHP 8 pour les commandes
3. **Interfaces Symfony**: Implémenter toutes les méthodes requises
4. **Configuration**: S'assurer que tous les services ont leurs dépendances configurées
5. **Task Workers**: Rendre les callbacks optionnels ou configurer par défaut

---

*Rapport généré lors des tests du 12/12/2024*

