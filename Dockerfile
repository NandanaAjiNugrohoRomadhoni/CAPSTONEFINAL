# ============================================================
# Stage 1: Build Next.js SPA (static export)
# ============================================================
FROM node:22-alpine AS builder

ARG NEXT_PUBLIC_API_BASE_URL=http://localhost:4000
ENV NEXT_PUBLIC_API_BASE_URL=${NEXT_PUBLIC_API_BASE_URL}

WORKDIR /build/gudang-app
COPY gudang-app/package*.json ./
COPY gudang-app/ .
RUN npm ci && npm run build

# ============================================================
# Stage 2: FrankenPHP runtime (Caddy + PHP 8.4 embedded)
# ============================================================
FROM docker.io/dunglas/frankenphp:alpine

# ── Composer (not in base image) ────────────────────────────
COPY --from=docker.io/composer:latest /usr/bin/composer /usr/local/bin/composer

# ── PHP extensions required by CodeIgniter 4 + Shield ─────
RUN install-php-extensions \
    pcntl \
    pdo_mysql \
    mysqli \
    mbstring \
    intl \
    openssl \
    opcache

# ── Copy Node.js into runtime (maintenance / one-off scripts) ──
COPY --from=builder /usr/local/bin/node /usr/local/bin/node
COPY --from=builder /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

# ── PHP backend code ───────────────────────────────────────
COPY Capstone/backend/ /app

# Remove host vendor to guarantee clean production install
RUN rm -rf /app/vendor \
    && cd /app \
    && composer install --no-dev --optimize-autoloader --no-interaction

# ── Remove migrations that conflict with vendor packages ──
# Shield vendor already creates users + auth tables.
# App copies just cause "table already exists" errors.
RUN rm -f \
    /app/app/Database/Migrations/2026-03-31-110526_CreateUsers.php \
    /app/app/Database/Migrations/2026-04-02-100000_CreateShieldAuthTables.php

# ── Next.js static export → backend/public/ ───────────────
COPY --from=builder /build/gudang-app/out/ /app/public/

# ── Docker-specific environment (overrides .env) ──────────
COPY .env.docker /app/.env

# ── Caddy configuration ────────────────────────────────────
COPY Caddyfile /etc/caddy/Caddyfile

# ── Entrypoint (migrations + startup) ────────────────────
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# ── Permissions ────────────────────────────────────────────
RUN chown -R www-data:www-data /app/writable /app/public

WORKDIR /app

EXPOSE 4000

ENTRYPOINT ["/entrypoint.sh"]
