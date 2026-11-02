# Eufy SDK Jeedom — prototype v0.1

Ce plugin **distinct** (`eufysdk`) ne remplace pas `eufy` et utilise le bridge officiel du projet communautaire mega-yfue, lui-même basé sur `@mega-yfue/eufy-sdk`.

## Pré-requis

- Jeedom 4.4+ / Debian 12, Python 3 et `python3-venv`.
- Docker et Docker Compose pour exécuter le bridge **indépendamment** du plugin (sur la VM ou le Synology).
- Une session Eufy ; le compte peut demander un code 2FA ou un CAPTCHA.

## Démarrer le bridge

### Cas A — Jeedom et Docker sur la même machine (géré depuis le plugin)

Depuis Jeedom 4.4+/v4, le plugin peut démarrer, arrêter et configurer lui-même le bridge
Docker, à condition que l'utilisateur du serveur web (`www-data`) puisse exécuter `docker` :

```bash
sudo usermod -aG docker www-data
# puis redémarrer apache/php-fpm et le démon Jeedom pour appliquer le nouveau groupe
```

Si cela n'est pas souhaité, configurer à la place le mécanisme sudoers de Jeedom pour
autoriser `www-data` à lancer `docker` sans mot de passe.

Ensuite, dans **Plugins > Sécurité > Eufy SDK (Mega) > Configuration** :

1. Vérifier le **port** du bridge (champ « Port », par défaut `3001`). Le conteneur
   publie ce port vers son port interne `3000` (`ports: "${BRIDGE_PORT}:3000"` dans
   `docker-compose.yml`) : pas besoin que ce soit `3000` côté hôte. **Si un autre bridge
   Eufy tourne déjà sur cette machine** (par exemple le conteneur `eufy` /
   `bropat/eufy-security-ws` utilisé par l'autre plugin Jeedom "eufy"), choisir un port
   libre (3001, 3002…) pour éviter un conflit `EADDRINUSE`. Vérifier avec
   `docker ps -a` et `sudo ss -ltnp | grep <port>` avant de démarrer.
2. Saisir l'email, le mot de passe et le pays (`EUFY_COUNTRY`, le pays **du compte Eufy**,
   pas forcément celui où tourne Jeedom) du compte Eufy.
3. Cliquer sur **Enregistrer et (re)démarrer le bridge** : le plugin écrit `docker/.env`
   (droits `600`, mot de passe stocké chiffré côté Jeedom via `utils::encrypt`) et lance
   `docker compose up -d` depuis son propre dossier `docker/`.
4. L'état du bridge (OK/NOK) s'affiche sur la page ; **Démarrer/Arrêter le bridge** et
   **Synchroniser maintenant** permettent de piloter le conteneur sans terminal.
5. En cas de demande de code 2FA ou de CAPTCHA, utiliser les champs dédiés
   (**Code reçu par 2FA** / **Réponse CAPTCHA**) puis **Valider** ; **Tester la connexion**
   affiche l'état d'authentification brut renvoyé par le bridge.

Pare-feu requis dans tous les cas : **ne pas exposer le port du bridge sur Internet**.

### Cas B — Bridge sur une machine distincte (VM dédiée, NAS/Synology)

Si Jeedom ne peut pas piloter le démon Docker (hôte différent), laisser les champs
email/mot de passe vides côté Jeedom (les boutons démarrer/arrêter afficheront alors
« Docker introuvable ou inaccessible », ce qui est normal) et déployer le bridge
manuellement sur cette machine :

```bash
cp docker/.env.example docker/.env
chmod 600 docker/.env
# éditer docker/.env avec l'identifiant, le mot de passe Eufy, le pays, et BRIDGE_PORT
# (3001 par défaut ; choisir un port libre si un autre bridge Eufy tourne déjà dessus)
docker compose -f docker/docker-compose.yml up -d
curl http://<ip-du-nas>:3001/healthz
```

Puis saisir dans la configuration du plugin l'IP LAN et le port de cette machine
(`bridge_host` / `bridge_port`).

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

Pour un CAPTCHA, envoyer `{"captcha":"..."}`. Ces deux opérations sont aussi disponibles
depuis la configuration du plugin (champs **Code reçu par 2FA** / **Réponse CAPTCHA**),
sans passer par le terminal ; l'affichage de l'image CAPTCHA elle-même n'est pas encore
intégré, seul le texte/la réponse peut être saisi.

## Fonctionnalités v0.1

- Découverte et création des équipements (caméras, stations, capteurs exposés par le SDK)
- Propriétés **scalaires** en commandes info Jeedom ; synchronisation par minute (pas temps réel)
- Commandes `ON`/`OFF` automatiques pour les propriétés booléennes modifiables du manifeste
- Commandes **« Déclencher l'alarme »** (durée en secondes) / **« Arrêter l'alarme »** créées
  automatiquement quand le bridge annonce la capacité `siren` sur l'équipement (HomeBase, ou
  caméra rattachée à une HomeBase exposant le slot sirène) — ce n'est pas une propriété mais
  une action (`device.action`), distincte des commandes ON/OFF ci-dessus
- URL de snapshot en mode `stored` (réduit les réveils de caméras sur batterie)
- État d'authentification, dernière synchro et logs
- Saisie des identifiants Eufy (email/mot de passe/pays) et pilotage du bridge Docker
  (démarrer/arrêter/synchroniser, test de connexion, 2FA/CAPTCHA) directement depuis la
  configuration du plugin, lorsque Docker est accessible sur la machine Jeedom

## Limitations actuelles

- **Pas d'événements instantanés** (push motion, sonnette), ni WebRTC/RTSP intégré à la tuile Jeedom.
- Pas encore de commandes pour les sélecteurs/numériques ni de prise en charge complète des `decodedState`.
- Le pilotage du bridge depuis Jeedom nécessite que `www-data` puisse exécuter `docker` sur
  la même machine ; sur une topologie distribuée (NAS distinct), revenir à la configuration
  manuelle (cas B ci-dessus).
- Pas d'affichage de l'image CAPTCHA dans l'interface Jeedom (seule la réponse texte peut être envoyée).
- Aucun test avec tes caméras réelles ; vérifier les commandes avant de les utiliser dans des scénarios de sécurité.
- Le script Python établit une nouvelle connexion WebSocket à chaque requête. Cette V0 n'est pas destinée aux gros parcs : optimisation ultérieure par démon persistent.
- Des changements de protocole dans le bridge peuvent nécessiter une adaptation.

## Alarme sur Wall Light Cam (T84A1 / T84A0 / S100)

Le bridge officiel n'expose pas de commande d'alarme pour les Wall Light Cam autonomes (pas de
capacité `siren` dans `devices.list`), car `@mega-yfue/eufy-sdk` ne reconnaît cette sirène que sur
une caméra attachée à une HomeBase séparée. Or `eufy-security-client` (la lib bas niveau) traite un
appareil auto-hébergé (sa propre « station ») comme une station déclenchant **sa propre** alarme
(`CMD_SET_TONE_FILE`/1201), pas comme un hub qui relaie vers un autre appareil (1202) — seul ce cas
« la caméra est sa propre station » n'est pas codé côté `@mega-yfue/eufy-sdk`.

Le dossier `docker/custom-bridge/` contient un correctif (`siren.patch`) qui ajoute ce cas, et c'est
l'image Docker que `docker/docker-compose.yml` construit **par défaut** (fork source de `eufy-sdk` +
`ha-eufy-sdk-bridge`, patché puis compilé) — il n'y a plus d'image publique séparée à activer.
**Confirmé sonore sur un vrai T84A1** (révision 4, voir historique ci-dessous) — reconstruit depuis
une capture réseau du déclenchement/arrêt de l'appli officielle, pas depuis une hypothèse.

Le premier démarrage construit l'image (quelques minutes) ; l'installation des dépendances du plugin
(`plugin_info/install_dependencies.sh`) la pré-construit déjà en tâche de fond pour éviter ce délai
au moment de démarrer le démon depuis l'interface Jeedom.

**Historique des essais sur un vrai T84A1 (Wall Light Cam S100)** :
- *Révision 1* (trame `CAMERA_TONE`/1202, niveau 1, comme une caméra relayée par une HomeBase) :
  acceptée par le bridge (`ok:true`) mais **aucun son** — mauvaise trame pour ce modèle autonome.
- *Révision 2* (trame `CMD_SET_TONE_FILE`/1201 en `SET_PAYLOAD` niveau 2, via `homeBaseAlarm` déjà
  existant) : échec différent — `StationKeyUnavailableError` : cet appareil n'établit jamais de clé
  de session niveau 2 pour lui-même, donc toute commande chiffrée échoue quel que soit son contenu.
- *Révision 3* : `CMD_SET_TONE_FILE`/1201 en forme **niveau 1** int-plus-string, en reprenant
  verbatim la branche « integrated device » d'`eufy-security-client` (`value:2`, `valueSub:secondes`,
  canal `Station.CHANNEL`/255) : acceptée (`ok:true`) mais **aucun son** — bon identifiant de
  commande, mauvais mapping de champs et mauvais canal pour ce modèle précis.
- *Révision 4* (actuelle, **confirmée sonore**) : capture réseau du trafic P2P réel entre l'appli
  officielle (iPhone, via `rvictl` + Wireshark) et la caméra pendant un déclenchement puis un arrêt
  manuel. Les deux trames `CMD_SET_TONE_FILE`/1201 capturées ont été déchiffrées hors-ligne (AES-128-
  ECB, clé = `stationSn[-7:] + p2pDid` — ajoutée temporairement en log de debug dans le bridge patché)
  pour lire les champs en clair : `channel=0` (pas 255), `value` = durée en secondes (`30` sur le
  déclenchement capturé, `0` sur l'arrêt), et un `valueSub` fixe qui diffère entre déclenchement
  (`23`) et arrêt (`4`) — un sélecteur de sous-action, pas une valeur dérivée de la durée, donc
  reproduit tel quel plutôt que deviné. Voir `integratedStationAlarm()` dans `siren.patch`.

### Tester manuellement

Le plugin construit et démarre déjà cette image via `eufysdk::deamon_start()` (bouton « Démarrer le
bridge » / installation des dépendances). Pour un test manuel direct (hors Jeedom) :

```bash
cd docker
cp .env.example .env   # si pas déjà fait ; EUFY_EMAIL/PASSWORD/COUNTRY/BRIDGE_PORT
docker compose up -d --build
curl http://127.0.0.1:${BRIDGE_PORT:-3001}/healthz
```

Puis vérifier que la capacité apparaît et tester le déclenchement :

```bash
sudo -u www-data /var/www/html/plugins/eufysdk/core/python/.venv/bin/python \
  /var/www/html/plugins/eufysdk/core/python/bridge_cli.py \
  --url ws://127.0.0.1:3001/ws --cmd devices.list
# chercher "siren" dans les capabilities de la caméra, puis :
sudo -u www-data /var/www/html/plugins/eufysdk/core/python/.venv/bin/python \
  /var/www/html/plugins/eufysdk/core/python/bridge_cli.py \
  --url ws://127.0.0.1:3001/ws --cmd device.action \
  --fields '{"sn":"<SN_DE_LA_CAM>","action":"trigger","args":[5]}'
```

`eufysdk::syncActions()` crée les commandes « Déclencher l'alarme » / « Arrêter l'alarme » dès que le
bridge annonce `siren` dans les capacités de l'équipement — resynchroniser l'équipement après le
premier démarrage sur la nouvelle image si elles n'apparaissent pas tout de suite.

Si un autre modèle Wall Light Cam ne sonne pas avec ce correctif, le wire exact peut différer d'un
modèle à l'autre : reprendre la méthode de capture réseau (voir historique ci-dessus, révision 4)
plutôt que deviner, et envisager un ticket upstream avec le résultat sur
https://github.com/mega-yfue/eufy-sdk.

## Vérification rapide

```bash
php -l /var/www/html/plugins/eufysdk/core/class/eufysdk.class.php
curl -s http://127.0.0.1:3000/healthz
```

Sources : https://github.com/mega-yfue/ha-eufy-sdk-bridge/blob/main/docs/ws-protocol.md et https://doc.jeedom.com/dev/fr_FR/plugin_template
