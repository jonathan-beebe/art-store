FROM node:24-bookworm-slim

# SQLite comes from the Node runtime, so no dependency compiles here. npm still
# needs root certificates to reach the registry over TLS.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# The container runs as the host uid, which owns no home directory in the image.
# npm needs a writable HOME and cache or every install fails on permissions.
#
# node:sqlite is a release candidate, so Node before 24.15 prints an
# ExperimentalWarning on import. NODE_OPTIONS reaches the entrypoint, the compose
# command, and every `docker compose run`; on 24.15 and later the flag does nothing.
ENV HOME=/tmp \
    npm_config_cache=/tmp/.npm \
    npm_config_update_notifier=false \
    NODE_OPTIONS=--disable-warning=ExperimentalWarning

WORKDIR /var/www/src

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["node", "--watch", "app/server.ts"]
