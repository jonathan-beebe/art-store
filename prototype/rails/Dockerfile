# syntax=docker/dockerfile:1
FROM ruby:3.3-slim AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        build-essential \
        ca-certificates \
        curl \
        git \
        libsqlite3-dev \
        libyaml-dev \
        pkg-config \
        sqlite3 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/src

# ---------------------------------------------------------------------------
# dev: today's bind-mount workflow. `make up` builds this target; source
# arrives through the compose bind mount, not COPY, and the entrypoint
# installs the bundle, prepares the database, and builds the stylesheet at
# container start.
# ---------------------------------------------------------------------------
FROM base AS dev

# The generator has to exist before src/Gemfile does, so Rails is installed into
# the image rather than resolved from the bundle.
RUN gem install rails -v "~> 8.0" --no-document

# The bundle lives in the bind mount so gems survive `docker compose down` and a
# rebuilt image. HOME is writable by any uid, which is what lets the container
# run as the host user.
ENV BUNDLE_PATH=/var/www/src/vendor/bundle \
    BUNDLE_APP_CONFIG=/var/www/src/.bundle \
    HOME=/tmp

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["bin/rails", "server", "-b", "0.0.0.0", "-p", "3000"]

# ---------------------------------------------------------------------------
# build: installs the production bundle and precompiles the assets (Propshaft
# digests, with the Tailwind build hooked into assets:precompile) once at
# image build time rather than on every container start.
# ---------------------------------------------------------------------------
FROM base AS build

ENV RAILS_ENV=production \
    BUNDLE_DEPLOYMENT=1 \
    BUNDLE_PATH=/usr/local/bundle \
    BUNDLE_WITHOUT="development test"

COPY src/Gemfile src/Gemfile.lock ./
RUN bundle install && rm -rf "$BUNDLE_PATH"/cache

COPY src/ ./

# assets:precompile boots the app; the dummy key stands in for the real
# SECRET_KEY_BASE, which only the running service knows. log/ and tmp/ are
# kept out of the build context, and bootsnap wants tmp/cache at boot.
RUN mkdir -p app/assets/builds log storage tmp/pids \
    && SECRET_KEY_BASE_DUMMY=1 bin/rails assets:precompile

# ---------------------------------------------------------------------------
# runtime: production configuration, non-root, no bind mount. `bin/deploy`
# prepares the database, seeds, and serves; Puma reads PORT from the
# environment (default 3000). SECRET_KEY_BASE must arrive from the
# environment.
# ---------------------------------------------------------------------------
FROM base AS runtime

ENV RAILS_ENV=production \
    BUNDLE_DEPLOYMENT=1 \
    BUNDLE_PATH=/usr/local/bundle \
    BUNDLE_WITHOUT="development test"

RUN useradd --create-home --shell /usr/sbin/nologin rails

COPY --from=build /usr/local/bundle /usr/local/bundle
COPY --from=build --chown=rails:rails /var/www/src /var/www/src

# The SQLite file and the Active Storage uploads both live under storage/, so
# one persistent volume covers all durable state. log/ and tmp/ are written
# at runtime.
RUN chown -R rails:rails storage log tmp db

USER rails

VOLUME ["/var/www/src/storage"]

HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=5 \
    CMD ["curl", "-fsS", "http://127.0.0.1:3000/up"]

CMD ["bin/rails", "server", "-b", "0.0.0.0"]
