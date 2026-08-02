#!/bin/bash
#
# SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/../" >/dev/null 2>&1 && pwd )"

cd $DIR/

git submodule update --init

# Codespace config
cp .devcontainer/codespace.config.php config/codespace.config.php

# VSCode debugger profile
mkdir -p .vscode && cp .devcontainer/launch.json .vscode/launch.json

ADMIN_USER="${NEXTCLOUD_ADMIN_USER:-admin}"
ADMIN_PASS="${NEXTCLOUD_ADMIN_PASSWORD:-}"
DB_USER="${POSTGRES_USER:-postgres}"
DB_PASS="${POSTGRES_PASSWORD:-}"
DB_NAME="${POSTGRES_DB:-postgres}"

if [[ -z "$ADMIN_PASS" || -z "$DB_PASS" ]]; then
    echo "Refusing to install with empty credentials."
    echo "Set NEXTCLOUD_ADMIN_PASSWORD and POSTGRES_PASSWORD before first start."
    exit 1
fi

# Onetime installation setup
if [[ ! $(sudo -u ${APACHE_RUN_USER} php occ status) =~ installed:[[:space:]]*true ]]; then
    echo "Running NC installation"
    sudo -u ${APACHE_RUN_USER} php occ maintenance:install \
        --verbose \
        --database=pgsql \
        --database-name="$DB_NAME" \
        --database-host=127.0.0.1 \
        --database-port=5432 \
        --database-user="$DB_USER" \
        --database-pass="$DB_PASS" \
        --admin-user "$ADMIN_USER" \
        --admin-pass "$ADMIN_PASS"
fi

sudo service apache2 restart
