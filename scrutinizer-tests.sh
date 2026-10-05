#!/bin/sh
set -eu

export API_DB_HOST="${SERVICE_MYSQL_IP:-127.0.0.1}"
export DB_HOST="${SERVICE_MYSQL_IP:-127.0.0.1}"

mkdir -p cache/coverage

vendor/bin/phpunit \
    --configuration phpunit.xml.dist \
    --colors=never \
    --stop-on-error \
    --stop-on-failure \
    --coverage-clover cache/coverage/coverage.xml
