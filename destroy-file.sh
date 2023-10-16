#!/bin/bash

BLOCK_SIZE=512

if [[ $# -lt 1 ]]
then
    echo "Usage: $0 <file-path>" >&2
    exit 1
fi

if [[ ! -f "$1" ]]
then
    echo "'$1' doesn't appear to be a file." >&2
    exit 2
fi

FILENAME=$(readlink -f "$1")

SIZE=$(du -b "$FILENAME" | cut -f 1)
dd bs=$BLOCK_SIZE count=$SIZE iflag=count_bytes if=/dev/urandom of="$FILENAME"
rm "$FILENAME"
