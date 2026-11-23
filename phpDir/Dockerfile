
FROM php:8.0-apache

RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

RUN printf '%s\n' \
    'deb http://snapshot.debian.org/archive/debian/20260903T220410Z bullseye main' \
    'deb http://snapshot.debian.org/archive/debian/20260903T220410Z bullseye-updates main' \
    'deb http://snapshot.debian.org/archive/debian-security/20260903T220410Z bullseye-security main' \
    > /etc/apt/sources.list \
    && apt-get -o Acquire::Check-Valid-Until=false update \
    && apt-get -o Acquire::Check-Valid-Until=false upgrade -y \
    && rm -rf /var/lib/apt/lists/*
