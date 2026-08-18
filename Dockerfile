FROM node:22-bookworm-slim AS frontend
WORKDIR /src
COPY package.json ./
RUN npm install --no-audit --no-fund
COPY resources ./resources
COPY public ./public
COPY vite.config.ts tsconfig.json ./
RUN npm run build

FROM dunglas/frankenphp:php8.4-bookworm
RUN install-php-extensions pdo_pgsql redis pcntl intl mbstring zip xml bcmath
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . /app
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
COPY --from=frontend /src/public/build /app/public/build
EXPOSE 8000
CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000"]
