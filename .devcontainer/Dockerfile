FROM php:8.4.14-cli-bookworm@sha256:8ee2296ac8fafec3f0a414430c4a6ae90fe9b9165b2eda1146e4e554d7b33daa

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    ca-certificates curl default-mysql-client git unzip libzip-dev libonig-dev \
  && docker-php-ext-install pdo_mysql mbstring \
  && rm -rf /var/lib/apt/lists/* \
  && printf 'opcache.enable=0\nopcache.enable_cli=0\n' > /usr/local/etc/php/conf.d/opcache-dev.ini

COPY --from=composer:2.8.8@sha256:3df281ca83119bff1332f0f5f311960cb16b99238f8273acd965be6bd508aa8e \
  /usr/bin/composer /usr/bin/composer

# 計測用 k6（ホストの docker/finch run なしで bench できるようにする）
ARG TARGETARCH
ARG K6_VERSION=v1.6.0
RUN case "$TARGETARCH" in \
      amd64) K6_ARCH=amd64; K6_SHA256=365591ae4039b51b2843b4a117e2dde96fa20cc0c5df800a330790380eebb697 ;; \
      arm64) K6_ARCH=arm64; K6_SHA256=5b1139ec5e2fa503fa694961120859db4db7573e4934f4069ed8964f3e3d7450 ;; \
      *) echo "unsupported arch: $TARGETARCH" >&2; exit 1 ;; \
    esac \
  && curl -fsSL "https://github.com/grafana/k6/releases/download/${K6_VERSION}/k6-${K6_VERSION}-linux-${K6_ARCH}.tar.gz" -o /tmp/k6.tgz \
  && echo "${K6_SHA256}  /tmp/k6.tgz" | sha256sum -c - \
  && tar -xzf /tmp/k6.tgz --strip-components=1 -C /usr/local/bin "k6-${K6_VERSION}-linux-${K6_ARCH}/k6" \
  && rm /tmp/k6.tgz \
  && k6 version

COPY .devcontainer/entrypoint.sh /usr/local/bin/web-tune-handson-entrypoint
RUN chmod +x /usr/local/bin/web-tune-handson-entrypoint

WORKDIR /workspaces/web-tune-handson/app
EXPOSE 3000
CMD ["/usr/local/bin/web-tune-handson-entrypoint"]
