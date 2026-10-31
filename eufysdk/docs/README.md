# Eufy SDK Jeedom — prototype v0.1

Ce plugin **distinct** (`eufysdk`) ne remplace pas `eufy` et utilise le bridge officiel du projet communautaire mega-yfue, lui-même basé sur `@mega-yfue/eufy-sdk`.

## Pré-requis

- Jeedom 4.4+ / Debian 12, Python 3 et `python3-venv`.
- Docker et Docker Compose pour exécuter le bridge **indépendamment** du plugin (sur la VM ou le Synology).
- Une session Eufy ; le compte peut demander un code 2FA ou un CAPTCHA.

## Démarrer le bridge

Copier le dossier `docker` dans un répertoire persistant **hors du dossier Jeedom**, puis :

```bash
cp .env.example .env
chmod 600 .env
# éditer .env avec l'identifiant, mot de passe Eufy, pays BE
docker compose up -d
curl http://127.0.0.1:3000/healthz
```

La variable `EUFY_COUNTRY` concerne le **pays du compte Eufy**, pas forcément le pays où tourne Jeedom.

Le réseau `host` simplifie l'accès au service mais **expose aussi les ports du bridge** sur les interfaces de la machine. Pare-feu requis : **ne pas exposer le port 3000 sur Internet**. Sur un NAS distinct, saisir son IP LAN dans la configuration du plugin.

## Installer le plugin

1. Installer le ZIP depuis le gestionnaire de plugins Jeedom en tant que plugin tiers (ou déposer le dossier sous `/var/www/html/plugins/eufysdk`). Les options d'import ZIP dépendent de la version Jeedom ; utiliser l'installation GitHub ou copie manuelle si nécessaire.
2. Installer les dépendances depuis Jeedom ; sinon : `sudo apt install python3-venv` puis `sudo -u www-data bash /var/www/html/plugins/eufysdk/plugin_info/install_dependencies.sh`.
3. Configurer `bridge_host` (IP LAN du bridge) et `bridge_port` (3000).
4. Activer le plugin. Le cron Jeedom `eufysdk::cron()` synchronise les appareils toutes les minutes.

## Première connexion / 2FA

Vérifier l'état :

```bash
sudo -u www-data /var/www/html/plugins/eufysdk/core/python/.venv/bin/python \
 /var/www/html/plugins/eufysdk/core/python/bridge_cli.py \
 --url ws://127.0.0.1:3000/ws --cmd auth.status
```

Si `require_2fa`, remplacer `123456` par le code reçu :

```bash
sudo -u www-data /var/www/html/plugins/eufysdk/core/python/.venv/bin/python \
 /var/www/html/plugins/eufysdk/core/python/bridge_cli.py \
 --url ws://127.0.0.1:3000/ws --cmd auth.submit --fields '{"code":"123456"}'
```

Pour un CAPTCHA, envoyer `{"captcha":"..."}`. L'interface CAPTCHA avec affichage image n'est pas encore implémentée dans le plugin. Le bridge peut fournir l'image via `auth.status`.

## Fonctionnalités v0.1

- Découverte et création des équipements (caméras, stations, capteurs exposés par le SDK)
- Propriétés **scalaires** en commandes info Jeedom ; synchronisation par minute (pas temps réel)
- Commandes `ON`/`OFF` automatiques pour les propriétés booléennes modifiables du manifeste
- URL de snapshot en mode `stored` (réduit les réveils de caméras sur batterie)
- État d'authentification, dernière synchro et logs

## Limitations actuelles

- **Pas d'événements instantanés** (push motion, sonnette), ni WebRTC/RTSP intégré à la tuile Jeedom.
- Pas encore de commandes pour les sélecteurs/numériques ni de prise en charge complète des `decodedState`.
- Pas encore de formulaire 2FA/CAPTCHA depuis l'interface Jeedom.
- Aucun test avec tes caméras réelles ; vérifier les commandes avant de les utiliser dans des scénarios de sécurité.
- Le script Python établit une nouvelle connexion WebSocket à chaque requête. Cette V0 n'est pas destinée aux gros parcs : optimisation ultérieure par démon persistent.
- Des changements de protocole dans le bridge peuvent nécessiter une adaptation.

## Vérification rapide

```bash
php -l /var/www/html/plugins/eufysdk/core/class/eufysdk.class.php
curl -s http://127.0.0.1:3000/healthz
```

Sources : https://github.com/mega-yfue/ha-eufy-sdk-bridge/blob/main/docs/ws-protocol.md et https://doc.jeedom.com/dev/fr_FR/plugin_template
