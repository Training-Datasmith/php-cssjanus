FROM php:8.2-cli@sha256:604de2f0cba9e9dde16cf717c2f03b0948c99949bdf7c11cdf76326e0da7d2ea

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        git \
        unzip \
        libonig-dev \
    && docker-php-ext-install -j"$(nproc)" mbstring \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
