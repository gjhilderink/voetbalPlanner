#!/usr/bin/env bash
# Maakt een zip van de WordPress-plugin, klaar om te uploaden.
set -euo pipefail

map="$(cd "$(dirname "$0")" && pwd)"
plugin="voetbalplanner-ticketshop"

versie=$(grep -m1 '^ \* Version:' "$map/$plugin/$plugin.php" | awk '{print $3}')
doel="$map/dist/$plugin-$versie.zip"

mkdir -p "$map/dist"
rm -f "$doel"

# Vanuit de bovenliggende map zippen, zodat de pluginmap in de zip zit; anders
# belandt alles los in wp-content/plugins.
if command -v zip >/dev/null 2>&1; then
    (cd "$map" && zip -rq "$doel" "$plugin" -x '*.DS_Store')
elif command -v php >/dev/null 2>&1; then
    # Op Windows staat zip er meestal niet. Niet uitwijken naar
    # Compress-Archive: die zet backslashes in de bestandsnamen en dan pakt de
    # server de plugin verkeerd uit.
    php "$map/bouw.php" "$map/$plugin" "$doel"
else
    echo "Geen zip en geen php gevonden." >&2
    exit 1
fi

echo "$doel"
