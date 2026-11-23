#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# Runs once, when the database volume is created (S-0923). Two accounts:
# the migrations account owns the schema and grants table rights; the runtime
# account of the application gets them per table after each migration run
# (entrypoint.sh, role migrate). The root password is random and never used.
# Sourced by the image's entrypoint, which provides docker_process_sql.

: "${MARIADB_DATABASE:?}" "${PROJEKTRACK_DB_MIGRATOR_PASSWORD:?}" "${PROJEKTRACK_DB_PASSWORD:?}"

for value in "${MARIADB_DATABASE}" "${PROJEKTRACK_DB_MIGRATOR_PASSWORD}" "${PROJEKTRACK_DB_PASSWORD}"; do
    if [[ ! "${value}" =~ ^[A-Za-z0-9_]+$ ]]; then
        echo 'Database name and passwords may only contain letters, digits and underscores.' >&2
        exit 1
    fi
done

docker_process_sql --database=mysql <<-EOSQL
	CREATE USER 'projektrack_migrator'@'%' IDENTIFIED BY '${PROJEKTRACK_DB_MIGRATOR_PASSWORD}';
	GRANT ALL PRIVILEGES ON \`${MARIADB_DATABASE}\`.* TO 'projektrack_migrator'@'%' WITH GRANT OPTION;
	CREATE USER 'projektrack'@'%' IDENTIFIED BY '${PROJEKTRACK_DB_PASSWORD}';
	FLUSH PRIVILEGES;
EOSQL
