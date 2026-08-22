FROM ruby:3.3-slim

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

# The generator has to exist before src/Gemfile does, so Rails is installed into
# the image rather than resolved from the bundle.
RUN gem install rails -v "~> 8.0" --no-document

# The bundle lives in the bind mount so gems survive `docker compose down` and a
# rebuilt image. HOME is writable by any uid, which is what lets the container
# run as the host user.
ENV BUNDLE_PATH=/var/www/src/vendor/bundle \
    BUNDLE_APP_CONFIG=/var/www/src/.bundle \
    HOME=/tmp

WORKDIR /var/www/src

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["bin/rails", "server", "-b", "0.0.0.0", "-p", "3000"]
