#!/bin/bash

BIN="/usr/local/bin"

if [[ $# -ne 1 ]]
then
    echo "Usage:   $0 <path to node root>" >&2
    echo "Example: $0 /opt/node-v18.16.1-linux-x64" >&2
    exit 1
fi

if [[ ! -f "$1/bin/node" ]]
then
    echo "$1 is not a valid node root" >&2
    exit 2
fi

if [[ ! -f "$1/bin/npm" ]]
then
    echo "$1 is not a valid node root" >&2
    exit 2
fi

unlink "$BIN/node"
unlink "$BIN/npm"
if [[ -L "$BIN/npx" ]]
then
    unlink "$BIN/npx"
fi

ln -s "$1/bin/node" "$BIN"
ln -s "$1/bin/npm" "$BIN"
if [[ -f "$1/bin/npx" ]]
then
    ln -s "$1/bin/npx" "$BIN"
fi
