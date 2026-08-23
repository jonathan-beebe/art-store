FROM node:24-bookworm-slim

# better-sqlite3 falls back to compiling from source when no prebuilt binary
# matches the platform, which needs node-gyp's toolchain.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        g++ \
        make \
        python3 \
    && rm -rf /var/lib/apt/lists/*

# The container runs as the host uid, which owns no home directory in the image.
# npm needs a writable HOME and cache or every install fails on permissions.
ENV HOME=/tmp \
    npm_config_cache=/tmp/.npm \
    npm_config_update_notifier=false

WORKDIR /var/www/src

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["node", "--watch", "app/server.ts"]
