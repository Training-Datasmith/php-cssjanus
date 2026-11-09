FROM php:8.2-cli@sha256:604de2f0cba9e9dde16cf717c2f03b0948c99949bdf7c11cdf76326e0da7d2ea

ENV DEBIAN_FRONTEND=noninteractive

RUN sed -i \
        -e 's|URIs: http://deb.debian.org|URIs: https://deb.debian.org|g' \
        /etc/apt/sources.list.d/debian.sources \
    && if grep -R 'URIs: http://' /etc/apt/sources.list.d/ >/dev/null 2>&1; then \
        echo 'FATAL: apt sources still use http://' >&2; \
        grep -R 'URIs:' /etc/apt/sources.list.d/ >&2 || true; \
        exit 1; \
    fi \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        git \
        unzip \
        libonig-dev \
    && docker-php-ext-install -j"$(nproc)" mbstring \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
