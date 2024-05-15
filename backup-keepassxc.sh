#!/bin/bash

if [[ $# -lt 1 ]]
then
    echo "Usage: $0 <database-file>" >&2
    exit 0
fi

DB_FILE=$(readlink -f "$1")

if [[ ! -f "$DB_FILE" ]]
then
    echo "'$1' is not a file." >&2
    exit 1
fi

EXPORT_XML="$DB_FILE.xml"

echo "Exporting '$1'"
keepassxc-cli export --format xml "$DB_FILE" > "$EXPORT_XML"
EXPORT_RESULT=$?

if [[ $EXPORT_RESULT -ne 0 ]]
then
    echo "Export failed." >&2
    exit $EXPORT_RESULT
fi

echo "Encrypting to '${1}'.xml.gpg..."
gpg --output "${EXPORT_XML}.gpg" --symmetric --no-symkey-cache "$EXPORT_XML"
CRYPT_RESULT=$?

if [[ $CRYPT_RESULT -ne 0 ]]
then
    echo "Ecrypt failed." >&2
fi

XML_SIZE=$(du -b "$EXPORT_XML" | cut -f 1)
head -c $XML_SIZE /dev/urandom > "$EXPORT_XML"
rm "$EXPORT_XML"
