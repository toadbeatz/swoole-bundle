# Packagist Setup Guide / Guide de Configuration Packagist

🇬🇧 **[English](#english)** | 🇫🇷 **[Français](#français)**

---

# English

This guide explains how to configure your GitHub repository to work with Packagist.org.

## 📦 GitHub Repository Name

### Naming Convention

For a package named `toadbeatz/swoole-bundle` in `composer.json`, your GitHub repository must be:

```
https://github.com/toadbeatz/swoole-bundle
```

**Format:** `github.com/{vendor}/{package-name}`

Where:
- `toadbeatz` = your GitHub username (or organization)
- `swoole-bundle` = the package name (without vendor)

### ✅ Recommended Names

- ✅ `toadbeatz/swoole-bundle` (recommended)
- ✅ `toadbeatz/symfony-swoole-bundle`
- ✅ `toadbeatz/swoole-symfony-bundle`

### ❌ Names to Avoid

- ❌ `swoole-bundle` (without vendor)
- ❌ `SwooleBundle` (uppercase)
- ❌ `swoole_bundle` (underscores, use hyphens)

---

## 🔧 Packagist Configuration

### Step 1: Create GitHub Repository

1. Go to GitHub and create a new repository
2. Name: `swoole-bundle` (under your account `toadbeatz`)
3. Visibility: **Public** (required for free Packagist)
4. Do NOT check "Initialize with README" if you have local code

### Step 2: Push Your Code

```bash
# If you haven't initialized git yet
git init
git add .
git commit -m "Initial commit"
git branch -M main
git remote add origin https://github.com/toadbeatz/swoole-bundle.git
git push -u origin main

# If you already have a git repo
git remote add origin https://github.com/toadbeatz/swoole-bundle.git
git push -u origin main
```

### Step 3: Create Version Tag

Packagist requires at least one tag to create a version:

```bash
# Create annotated tag (recommended)
git tag -a v1.0.0 -m "Version 1.0.0 - Initial release"
git push origin v1.0.0

# Or simple tag
git tag v1.0.0
git push origin v1.0.0
```

**Version convention:**
- `v1.0.0` (recommended)
- `1.0.0` (without 'v' also works)
- Follow Semantic Versioning (MAJOR.MINOR.PATCH)

### Step 4: Create Packagist Account

1. Go to https://packagist.org
2. Create an account (or sign in with GitHub)
3. Click "Submit" in the menu

### Step 5: Submit Your Package

1. In the Packagist form, enter your repository URL:
   ```
   https://github.com/toadbeatz/swoole-bundle
   ```

2. Packagist will automatically:
   - Detect `composer.json`
   - Validate package name (`toadbeatz/swoole-bundle`)
   - Create the package

### Step 6: Configure GitHub Webhook (IMPORTANT)

For Packagist to update automatically when you push code:

**Option A: GitHub Packagist Integration (Recommended)**

1. Go to your Packagist profile settings
2. Connect your GitHub account
3. Enable auto-update for your packages

**Option B: Manual Webhook**

1. In Packagist, go to your package
2. Click "Update" to see webhook options
3. Copy the webhook URL

4. On GitHub:
   - Go to Settings > Webhooks in your repository
   - Click "Add webhook"
   - Payload URL: Paste the Packagist webhook URL
   - Content type: `application/json`
   - Events: Select "Just the push event"
   - Click "Add webhook"

---

## ✅ Verification Checklist

### Before Submission

- [ ] GitHub repository created with correct name
- [ ] Code pushed to GitHub (main/master branch)
- [ ] Repository is **public**
- [ ] `composer.json` contains correct name (`toadbeatz/swoole-bundle`)
- [ ] At least one version tag created (e.g., `v1.0.0`)
- [ ] README.md exists and is complete
- [ ] LICENSE file exists

### After Submission

- [ ] Package appears on Packagist
- [ ] Version is displayed correctly
- [ ] Webhook is configured
- [ ] Test installation works

### Test Installation

```bash
# Create a test project
composer create-project symfony/skeleton test-project
cd test-project

# Require your package
composer require toadbeatz/swoole-bundle

# Verify installation
php bin/console | grep swoole
```

---

## 📋 composer.json Requirements

Your `composer.json` should include:

```json
{
    "name": "toadbeatz/swoole-bundle",
    "type": "symfony-bundle",
    "description": "High-performance Swoole 6.1.4 integration bundle for Symfony 7/8",
    "keywords": ["swoole", "symfony", "async", "performance"],
    "license": "MIT",
    "homepage": "https://github.com/toadbeatz/swoole-bundle",
    "authors": [{
        "name": "toadbeatz",
        "email": "alvingely.pro@gmail.com"
    }],
    "support": {
        "issues": "https://github.com/toadbeatz/swoole-bundle/issues",
        "source": "https://github.com/toadbeatz/swoole-bundle"
    },
    "require": {
        "php": "^8.2 || ^8.3 || ^8.4",
        "symfony/framework-bundle": "^7.0 || ^8.0"
    },
    "autoload": {
        "psr-4": {
            "Toadbeatz\\SwooleBundle\\": "src/"
        }
    },
    "extra": {
        "symfony": {
            "allow-contrib": false,
            "require": "^7.0 || ^8.0"
        }
    }
}
```

---

## 🔄 Automatic Updates

Once webhook is configured:

1. Make your changes
2. Commit and push:
   ```bash
   git add .
   git commit -m "New feature"
   git push
   ```
3. Create new tag for new version:
   ```bash
   git tag -a v1.0.1 -m "Version 1.0.1"
   git push origin v1.0.1
   ```
4. Packagist updates automatically (within minutes)

---

## 🐛 Troubleshooting

### Error: "Repository not found"

- Verify repository is **public**
- Check URL is correct
- Verify code is pushed to main branch

### Error: "Package name mismatch"

- `name` in `composer.json` must match repository
- Format: `{vendor}/{package-name}`
- Repository: `github.com/{vendor}/{package-name}`

### Packagist doesn't update

- Check webhook is configured correctly
- Check webhook logs on GitHub
- Click "Update" manually on Packagist

### Package doesn't appear in search

- Wait a few minutes (indexing)
- Verify package is "published"
- Use direct URL: `packagist.org/packages/toadbeatz/swoole-bundle`

---

## 🎯 Summary

**GitHub Repository:** `swoole-bundle`  
**Full URL:** `https://github.com/toadbeatz/swoole-bundle`  
**Packagist Name:** `toadbeatz/swoole-bundle`  
**Installation:** `composer require toadbeatz/swoole-bundle`

---

# Français

Ce guide explique comment configurer votre dépôt GitHub pour qu'il fonctionne avec Packagist.org.

## 📦 Nom du Dépôt GitHub

### Convention de nommage

Pour un package nommé `toadbeatz/swoole-bundle` dans `composer.json`, votre dépôt GitHub doit être :

```
https://github.com/toadbeatz/swoole-bundle
```

**Format :** `github.com/{vendor}/{nom-du-package}`

Où :
- `toadbeatz` = votre nom d'utilisateur GitHub (ou organisation)
- `swoole-bundle` = le nom du package (sans le vendor)

### ✅ Noms recommandés

- ✅ `toadbeatz/swoole-bundle` (recommandé)
- ✅ `toadbeatz/symfony-swoole-bundle`
- ✅ `toadbeatz/swoole-symfony-bundle`

### ❌ Noms à éviter

- ❌ `swoole-bundle` (sans vendor)
- ❌ `SwooleBundle` (majuscules)
- ❌ `swoole_bundle` (underscores, utilisez des tirets)

---

## 🔧 Configuration Packagist

### Étape 1 : Créer le dépôt GitHub

1. Allez sur GitHub et créez un nouveau dépôt
2. Nom : `swoole-bundle` (sous votre compte `toadbeatz`)
3. Visibilité : **Public** (requis pour Packagist gratuit)
4. Ne cochez PAS "Initialize with README" si vous avez du code local

### Étape 2 : Pousser votre code

```bash
# Si vous n'avez pas encore initialisé git
git init
git add .
git commit -m "Commit initial"
git branch -M main
git remote add origin https://github.com/toadbeatz/swoole-bundle.git
git push -u origin main

# Si vous avez déjà un repo git
git remote add origin https://github.com/toadbeatz/swoole-bundle.git
git push -u origin main
```

### Étape 3 : Créer un tag de version

Packagist nécessite au moins un tag pour créer une version :

```bash
# Créer un tag annoté (recommandé)
git tag -a v1.0.0 -m "Version 1.0.0 - Release initiale"
git push origin v1.0.0

# Ou un tag simple
git tag v1.0.0
git push origin v1.0.0
```

**Convention de version :**
- `v1.0.0` (recommandé)
- `1.0.0` (sans le 'v' fonctionne aussi)
- Suivez le Semantic Versioning (MAJEUR.MINEUR.CORRECTIF)

### Étape 4 : Créer un compte Packagist

1. Allez sur https://packagist.org
2. Créez un compte (ou connectez-vous avec GitHub)
3. Cliquez sur "Submit" dans le menu

### Étape 5 : Soumettre votre package

1. Dans le formulaire Packagist, entrez l'URL de votre dépôt :
   ```
   https://github.com/toadbeatz/swoole-bundle
   ```

2. Packagist va automatiquement :
   - Détecter le `composer.json`
   - Valider le nom du package (`toadbeatz/swoole-bundle`)
   - Créer le package

### Étape 6 : Configurer le webhook GitHub (IMPORTANT)

Pour que Packagist se mette à jour automatiquement quand vous poussez du code :

**Option A : Intégration GitHub Packagist (Recommandé)**

1. Allez dans les paramètres de votre profil Packagist
2. Connectez votre compte GitHub
3. Activez la mise à jour automatique pour vos packages

**Option B : Webhook manuel**

1. Sur Packagist, allez sur votre package
2. Cliquez sur "Update" pour voir les options de webhook
3. Copiez l'URL du webhook

4. Sur GitHub :
   - Allez dans Settings > Webhooks de votre dépôt
   - Cliquez sur "Add webhook"
   - Payload URL : Collez l'URL du webhook Packagist
   - Content type : `application/json`
   - Events : Sélectionnez "Just the push event"
   - Cliquez sur "Add webhook"

---

## ✅ Liste de vérification

### Avant la soumission

- [ ] Dépôt GitHub créé avec le bon nom
- [ ] Code poussé sur GitHub (branche main/master)
- [ ] Le dépôt est **public**
- [ ] `composer.json` contient le bon nom (`toadbeatz/swoole-bundle`)
- [ ] Au moins un tag de version créé (ex: `v1.0.0`)
- [ ] README.md existe et est complet
- [ ] Fichier LICENSE existe

### Après la soumission

- [ ] Le package apparaît sur Packagist
- [ ] La version est affichée correctement
- [ ] Le webhook est configuré
- [ ] L'installation de test fonctionne

### Tester l'installation

```bash
# Créer un projet de test
composer create-project symfony/skeleton test-project
cd test-project

# Requérir votre package
composer require toadbeatz/swoole-bundle

# Vérifier l'installation
php bin/console | grep swoole
```

---

## 📋 Exigences composer.json

Votre `composer.json` devrait inclure :

```json
{
    "name": "toadbeatz/swoole-bundle",
    "type": "symfony-bundle",
    "description": "Bundle d'intégration Swoole 6.1.4 haute performance pour Symfony 7/8",
    "keywords": ["swoole", "symfony", "async", "performance"],
    "license": "MIT",
    "homepage": "https://github.com/toadbeatz/swoole-bundle",
    "authors": [{
        "name": "toadbeatz",
        "email": "alvingely.pro@gmail.com"
    }],
    "support": {
        "issues": "https://github.com/toadbeatz/swoole-bundle/issues",
        "source": "https://github.com/toadbeatz/swoole-bundle"
    },
    "require": {
        "php": "^8.2 || ^8.3 || ^8.4",
        "symfony/framework-bundle": "^7.0 || ^8.0"
    },
    "autoload": {
        "psr-4": {
            "Toadbeatz\\SwooleBundle\\": "src/"
        }
    },
    "extra": {
        "symfony": {
            "allow-contrib": false,
            "require": "^7.0 || ^8.0"
        }
    }
}
```

---

## 🔄 Mises à jour automatiques

Une fois le webhook configuré :

1. Faites vos modifications
2. Commitez et poussez :
   ```bash
   git add .
   git commit -m "Nouvelle fonctionnalité"
   git push
   ```
3. Créez un nouveau tag pour une nouvelle version :
   ```bash
   git tag -a v1.0.1 -m "Version 1.0.1"
   git push origin v1.0.1
   ```
4. Packagist se met à jour automatiquement (quelques minutes)

---

## 🐛 Dépannage

### Erreur : "Repository not found"

- Vérifiez que le dépôt est **public**
- Vérifiez que l'URL est correcte
- Vérifiez que le code est poussé sur la branche principale

### Erreur : "Package name mismatch"

- Le `name` dans `composer.json` doit correspondre au dépôt
- Format : `{vendor}/{nom-du-package}`
- Dépôt : `github.com/{vendor}/{nom-du-package}`

### Packagist ne se met pas à jour

- Vérifiez que le webhook est configuré correctement
- Vérifiez les logs du webhook sur GitHub
- Cliquez sur "Update" manuellement sur Packagist

### Le package n'apparaît pas dans la recherche

- Attendez quelques minutes (indexation)
- Vérifiez que le package est "publié"
- Utilisez l'URL directe : `packagist.org/packages/toadbeatz/swoole-bundle`

---

## 🎯 Résumé

**Dépôt GitHub :** `swoole-bundle`  
**URL complète :** `https://github.com/toadbeatz/swoole-bundle`  
**Nom Packagist :** `toadbeatz/swoole-bundle`  
**Installation :** `composer require toadbeatz/swoole-bundle`

Tout doit correspondre ! 🚀
