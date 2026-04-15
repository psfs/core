services:
  php:
    image: c15k02k18/php:8.3-alpine
    pull_policy: always
    restart: always
    tty: true
    working_dir: /var/www
    volumes:
      - .:/var/www
      - ./docker/php.ini:/usr/local/etc/php/php.ini
    ports:
      - "${HOST_PORT:-8001}:8080"
    command: "php -S 0.0.0.0:8080 -t ./html"
    environment:
      PHP_TIMEZONE: "${PHP_TIMEZONE:-Europe/Madrid}"
      PHP_OPCACHE: "${PHP_OPCACHE:-0}"
    depends_on:
      redis:
        condition: service_healthy
      db:
        condition: service_healthy
    networks:
      - psfs-network

  redis:
    image: valkey/valkey:latest
    restart: always
    command: [
      "valkey-server",
      "--appendonly", "no",
      "--save", "",
      "--maxmemory", "256M",
      "--maxmemory-policy", "allkeys-lru"
    ]
    healthcheck:
      test: [ "CMD-SHELL", "valkey-cli ping | grep PONG" ]
      interval: 10s
      timeout: 3s
      retries: 5
      start_period: 5s
    networks:
      - psfs-network

  db:
    image: mysql:8.4
    restart: always
    environment:
      MYSQL_USER: ${MYSQL_USER:-psfs}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD:-psfs}
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:-psfs}
      MYSQL_DATABASE: ${MYSQL_DATABASE:-psfs}
    volumes:
      - psfs-db-data:/var/lib/mysql
    healthcheck:
      test: [ "CMD", "mysqladmin", "ping", "-h", "localhost" ]
      interval: 5s
      timeout: 5s
      retries: 20
    networks:
      - psfs-network

networks:
  psfs-network:
    driver: bridge

volumes:
  psfs-db-data:
