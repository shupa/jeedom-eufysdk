# Image expérimentale du bridge mega-yfue avec un correctif maison ajoutant le déclenchement
# d'alarme (siren trigger/stop) pour les Wall Light Cam autonomes (T84A1 / T84A0 / S100), que le
# projet upstream n'expose pas encore pour ce modèle (voir siren.patch).
#
# NON VÉRIFIÉ sur du matériel réel au moment de l'écriture : le correctif s'appuie sur la table de
# commandes d'eufy-security-client (qui liste bien DeviceTriggerAlarmSound pour ce DeviceType) mais
# personne n'a confirmé que la caméra sonne réellement. À tester, pas à déployer en confiance.
#
# Construire :
#   docker compose -f ../docker-compose.custom-bridge.yml build
# (voir ce fichier pour l'utiliser à la place de l'image publique)

FROM node:24-alpine AS sdk-build
RUN apk add --no-cache git
WORKDIR /build
ARG EUFY_SDK_REF=v0.5.0
RUN git clone --depth 1 --branch ${EUFY_SDK_REF} https://github.com/mega-yfue/eufy-sdk.git .
COPY siren.patch /tmp/siren.patch
RUN git apply /tmp/siren.patch
RUN npm install --ignore-scripts --no-audit --no-fund \
 && npm run build \
 && npm pack \
 && mv mega-yfue-eufy-sdk-*.tgz /build/eufy-sdk.tgz

FROM node:24-alpine
RUN apk add --no-cache ffmpeg curl git
WORKDIR /app

ARG GO2RTC_VERSION=1.9.9
ARG TARGETARCH
RUN case "${TARGETARCH:-amd64}" in \
      amd64) g2="go2rtc_linux_amd64" ;; \
      arm64) g2="go2rtc_linux_arm64" ;; \
      arm) g2="go2rtc_linux_arm" ;; \
      *) echo "unsupported TARGETARCH: ${TARGETARCH}" && exit 1 ;; \
    esac \
 && curl -fsSL -o /usr/local/bin/go2rtc \
      "https://github.com/AlexxIT/go2rtc/releases/download/v${GO2RTC_VERSION}/${g2}" \
 && chmod +x /usr/local/bin/go2rtc

ARG HA_BRIDGE_REF=main
RUN git clone --depth 1 --branch ${HA_BRIDGE_REF} https://github.com/mega-yfue/ha-eufy-sdk-bridge.git /tmp/bridge \
 && cp -r /tmp/bridge/. /app/ \
 && rm -rf /tmp/bridge

COPY --from=sdk-build /build/eufy-sdk.tgz /tmp/eufy-sdk.tgz
# Même technique que le Dockerfile officiel pour sa variante SDK_DIST_TAG=beta : installer d'abord
# les dépendances normales (registre npm), puis écraser uniquement @mega-yfue/eufy-sdk avec notre
# tarball patché, sans toucher package.json/lock (--no-save).
RUN npm install --omit=dev --no-audit --no-fund \
 && npm install --omit=dev --no-audit --no-fund --no-save /tmp/eufy-sdk.tgz \
 && rm /tmp/eufy-sdk.tgz \
 && node -e "console.log('SDK patché installé :', JSON.parse(require('fs').readFileSync('node_modules/@mega-yfue/eufy-sdk/package.json')).version)"

RUN chmod +x bin/start.sh && ln -sf /app/bin/start.sh /usr/local/bin/eufy-sdk-bridge

ENV BRIDGE_APP_DIR=/app BRIDGE_PORT=3000 BRIDGE_HOST=0.0.0.0 \
    GO2RTC_CONFIG=/app/data/go2rtc.yaml EUFY_SESSION=/app/data/.eufy-session.json
RUN mkdir -p /app/data
EXPOSE 3000 1984 8554 8555/udp

CMD [ "eufy-sdk-bridge" ]
