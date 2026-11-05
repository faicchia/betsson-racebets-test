#!/bin/bash

# Runs once, on first start of an empty data volume, after 01-init.sql has created the test database.
# Applies the single schema file to both the app and the test database.
set -euo pipefail

for db in "$MYSQL_DATABASE" racebets_test; do
    mysql --protocol=socket -uroot -p"$MYSQL_ROOT_PASSWORD" --database="$db" < /schema/schema.sql
done
