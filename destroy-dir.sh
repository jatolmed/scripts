#!/bin/bash

DIRECTORIO=$(readlink -f "$1")
TIEMPO_IMPRIMIR=100
TAMANYO_BLOQUE=512

I_WANNA_CANCEL=

function get_millis() {
    momento=$(date +"%s|%N")
    segundos=$(echo $momento | cut -f 1 -d "|")
    nano=$(echo $momento | cut -f 2 -d "|")
    while [[ ${nano:0:1} -eq 0 ]]
    do
        nano=${nano:1}
    done
    echo $((segundos*1000+nano/1000000))
}

function unidades() {
    let bytes=$1
    if [[ $bytes -gt 1000000000 ]]
    then
        echo $((bytes/1000000000)) GB
    elif [[ $bytes -gt 1000000 ]]
    then
        echo $((bytes/1000000)) MB
    elif [[ $bytes -gt 1000 ]]
    then
        echo $((bytes/1000)) KB
    else
        echo $bytes B
    fi
}

function cancel() {
    I_WANNA_CANCEL+=1
}

trap cancel SIGINT

echo "Borrando $DIRECTORIO..."

let ultima_impresion=$(get_millis)
let total_bytes=0
while read data
do
    bytes=${data%%:*}
    path=$(readlink -f "${data#*:}")
    if [[ "${path#$DIRECTORIO}" != "$path" ]]
    then
        dd bs=$TAMANYO_BLOQUE count=$bytes iflag=count_bytes if=/dev/urandom of="$path" 2>/dev/null
        let total_bytes+=$bytes
        let tiempo=$(get_millis)-$ultima_impresion
        if [[ $tiempo -gt $TIEMPO_IMPRIMIR ]]
        then
            destruidos=$(unidades $total_bytes)
            impresion="$destruidos destruidos: $path"
            let numero_espacios=$(tput cols)-${#impresion}
            espacios=""
            for i in $(seq 1 $numero_espacios)
            do
                espacios+=" "
            done
            impresion+=$espacios
            echo -en "\r${impresion:0:$(tput cols)}"
            let ultima_impresion=$(get_millis)
        fi
        if [[ -n "$I_WANNA_CANCEL" ]]
        then
            echo
            echo "Cancelled."
            exit 127
        fi
    fi
done< <(find "$DIRECTORIO" -mount -type f -printf "%s:%p\n")
