# syntax=docker/dockerfile:1
FROM node:24.19.0-bookworm-slim AS base

# SQLite comes from the Node runtime, so no dependency compiles here. npm still
# needs root certificates to reach the registry over TLS.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# node:sqlite is a release candidate, so Node before 24.15 prints an
# ExperimentalWarning on import. NODE_OPTIONS reaches every stage's npm
# scripts and the final CMD; on 24.15 and later (this image's pinned tag) the
# flag is a no-op.
ENV npm_config_update_notifier=false \
    NODE_OPTIONS=--disable-warning=ExperimentalWarning

WORKDIR /var/www/src

# ---------------------------------------------------------------------------
# dev: today's bind-mount workflow. `make up` builds this target; source
# arrives through the compose bind mount, not COPY, and the entrypoint
# installs dependencies, migrates, seeds, and builds assets at container
# start.
# ---------------------------------------------------------------------------
FROM base AS dev

# The container runs as the host uid docker-compose.yml injects, which owns
# no home directory in the image. npm needs a writable HOME and cache or
# every install fails on permissions.
ENV HOME=/tmp \
    npm_config_cache=/tmp/.npm

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["node", "--watch", "app/server.ts"]

# ---------------------------------------------------------------------------
# build: installs every dependency, including the Tailwind CLI, and builds
# the stylesheet once at image build time rather than on every container
# start.
# ---------------------------------------------------------------------------
FROM base AS build

COPY src/package.json src/package-lock.json ./
RUN npm ci

COPY src/app ./app
COPY src/public ./public
RUN npm run assets

# ---------------------------------------------------------------------------
# runtime: production dependencies only, non-root, no bind mount. Migrations
# run as an explicit step (`node app/db/migrate.ts`) before the container
# starts serving; nothing here seeds demo data.
# ---------------------------------------------------------------------------
FROM base AS runtime

ENV NODE_ENV=production \
    DATABASE_FILE=/var/www/src/storage/production.sqlite3 \
    UPLOADS_DIR=/var/www/src/public/uploads

COPY src/package.json src/package-lock.json ./
RUN npm ci --omit=dev

COPY --chown=node:node src/app ./app
# .dockerignore keeps generated public output out of the build context, so
# the whole public dir comes from the build stage instead: the tracked
# app.js alongside the built app.css, their hashed and pre-compressed
# siblings, and the manifest mapping between them.
COPY --chown=node:node --from=build /var/www/src/public ./public

# DATABASE_FILE and UPLOADS_DIR default into these two directories; both must
# be writable by the user the container actually runs as.
RUN mkdir -p storage public/uploads \
    && chown -R node:node storage public/uploads

USER node

VOLUME ["/var/www/src/storage", "/var/www/src/public/uploads"]

HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=5 \
    CMD ["node", "-e", "fetch('http://127.0.0.1:4000/health').then(r=>process.exit(r.ok?0:1),()=>process.exit(1))"]

CMD ["node", "app/server.ts"]
