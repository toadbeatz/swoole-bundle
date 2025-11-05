# Guide d'Installation - Swoole Bundle

Guide complet pour installer et configurer le bundle Swoole pour Symfony 7.

## 📋 Prérequis

- PHP 8.2 ou supérieur
- Extension Swoole 6.1 ou supérieure
- Symfony 7.0 ou supérieur
- Composer

## 🔧 Installation de Swoole

### Installation via PECL (Recommandé)

```bash
pecl install swoole
```

### Configuration PHP

Ajoutez l'extension dans votre `php.ini` :

```ini
extension=swoole
```

Ou créez un fichier `swoole.ini` dans votre répertoire de configuration PHP :

```ini
extension=swoole
```

### Vérification

Vérifiez que Swoole est installé :

```bash
php -m | grep swoole
php -r "echo swoole_version();"
```

Vous devriez voir la version de Swoole (6.1+ recommandé).

## 📦 Installation du Bundle

### Via Composer

```bash
composer require toadbeatz/swoole-bundle
```

## ⚙️ Configuration

### 1. Activer le Bundle

Le bundle est automatiquement activé si vous utilisez Symfony Flex. Sinon, ajoutez dans `config/bundles.php` :

```php
return [
    // ...
    Toadbeatz\SwooleBundle\SwooleBundle::class => ['all' => true],
];
```

### 2. Configuration de Base

Créez `config/packages/swoole.yaml` :

```yaml
swoole:
    http:
        host: '0.0.0.0'
        port: 9501
    
    hot_reload:
        enabled: true
        watch:
            - src
            - config
    
    performance:
        worker_num: ~  # Auto-détecte le nombre de CPU
        enable_coroutine: true
    
    debug:
        enabled: '%kernel.debug%'
```

## 🚀 Premier Lancement

### Démarrer le Serveur

```bash
# Mode développement avec hot-reload
php bin/console swoole:server:watch

# Mode production
php bin/console swoole:server:start
```

### Vérifier que ça Fonctionne

Ouvrez votre navigateur sur `http://localhost:9501` (ou le port configuré).

Vous devriez voir votre application Symfony.

## 🔍 Dépannage

### Erreur : "Swoole extension is not loaded"

1. Vérifiez que l'extension est chargée :
   ```bash
   php -m | grep swoole
   ```

2. Vérifiez votre `php.ini` :
   ```bash
   php --ini
   ```

3. Redémarrez PHP-FPM ou votre serveur web si nécessaire.

### Erreur : "Swoole version too old"

Mettez à jour Swoole :

```bash
pecl upgrade swoole
```

### Le serveur ne démarre pas

1. Vérifiez que le port n'est pas déjà utilisé :
   ```bash
   lsof -i :9501
   ```

2. Vérifiez les permissions :
   ```bash
   ls -la /tmp/swoole.log
   ```

3. Consultez les logs :
   ```bash
   tail -f /tmp/swoole.log
   ```

## ✅ Vérification de l'Installation

### Checklist

- [ ] Swoole 6.1+ installé et chargé
- [ ] Bundle installé via Composer
- [ ] Bundle activé dans `config/bundles.php`
- [ ] Configuration créée dans `config/packages/swoole.yaml`
- [ ] Serveur démarre sans erreur
- [ ] Application accessible sur le port configuré
- [ ] Hot-reload fonctionne en développement
- [ ] `dd()` fonctionne correctement

