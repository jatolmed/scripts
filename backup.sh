#!/bin/bash

# Función de traducción de tiempos:
function secs2str () {
	n=("segundo" "minuto" "hora" "día" "semana")
	m=(60 60 24 7)
	let t=0
	let t=$1 2>/dev/null
	r=""
	for i in $(seq 0 3); do
		let v=$t%${m[$i]}
		if [ $v -ne 0 ] || [ $i -eq 0 ]; then
			if [ $v -eq 1 ]; then
				r="$v ${n[$i]}, $r"
			else
				r="$v ${n[$i]}s, $r"
			fi
		fi
		let t/=${m[$i]}
	done
	if [ $t -ne 0 ]; then
		if [ $t -eq 1 ]; then
			r="$t ${n[4]}, $r"
		else
			r="$t ${n[4]}s, $r"
		fi
	fi
	echo ${r%,\ }
}

function mount_dst () {
	# Dispositivo asignado:
	line=$(/sbin/blkid -o full -s TYPE -l -t LABEL=\"$DSTFS_LABEL\")
	dev=${line%%:\ *}
	sys=${line##*TYPE=\"}
	sys=${sys%%\"*}

	# Si el dispositivo no existe se sale:
	if [ -z "$dev" ] || [ ! -b "$dev" ]; then
		date +%Y-%m-%d\ %T >&2
		echo "El dispositivo de destino con etiqueta '$DSTFS_LABEL' no se encuentra o es inaccesible." >&2
		exit
	fi

	# Se monta si no lo está:
	dstdir=$(df --output=target "$dev" | tail -n 1)
	if [ -z "$dstdir" ] || [ "${dev#$dstdir}" != "$dev" ]; then
		aux="/mnt/$DSTFS_LABEL"
		dstdir="$aux"
		let i=1
		while [ -e "$dstdir" ]; do
			dstdir="$aux.$i"
			let i+=1
		done
		mkdir "$dstdir"
		mount -t "$sys" "$dev" "$dstdir"
	fi
	echo "$dstdir"
}

# Variables de configuración
export DSTFS_LABEL="Tortilla"
DSTFS_DIR="CURRENT"

DSTFS_DIR="/${DSTFS_DIR#/}"
DSTFS_DIR=${DSTFS_DIR%/}

if [ "$(whoami)" != "root" ]; then
	date +%Y-%m-%d\ %T >&2
	echo "Este script debe ejecutarse como administrador." >&2
	exit
fi

# Directorio de origen:
srcdir=$(readlink -e "${1%/}")
shift
excludes=()
includes=()
let nexcludes=0
let nincludes=0
let excluding=0
let including=0
while [ -n "$1" ]; do
	if [ "$1" == "-e" ]; then
		let excluding=1
		let including=0
		shift
	fi
	if [ "$1" == "-i" ]; then
		let excluding=0
		let including=1
		shift
	fi
	if [ $excluding -eq 1 ]; then
		excluded=$(readlink -e "$1" 2> /dev/null)
		if [ -n "$excluded" ] && [ "${srcdir#$excluded}" == "${srcdir}" ]; then
			excludes[$nexcludes]="$excluded"
			let nexcludes+=1
		fi
	fi
	shift
done

# Comprobación de acceso:
if [ -z "$srcdir" ] || [ ! -d "$srcdir" ] || [ ! -r "$srcdir" ]; then
	date +%Y-%m-%d\ %T >&2
	echo "El directorio de origen, '$srcdir' no se encuentra o es inaccesible." >&2
	exit
fi

# Directorio de destino:
mntdir=$(mount_dst)
dstdir=$mntdir$DSTFS_DIR

if [ ! -e "$dstdir" ]; then
	mkdir -p "$dstdir"
fi

# Comprobación de acceso:
if [ ! -d "$dstdir" ] || [ ! -r "$dstdir" ] || [ ! -w "$dstdir" ]; then
	date +%Y-%m-%d\ %T >&2
	echo "El directorio de destino, '$dstdir' no se encuentra o es inaccesible." >&2
	exit
fi

# Creación del directorio de backup
# y establecimiento de los permisos
if [ ! -d "$dstdir$srcdir" ]; then
	mkdir -p "$dstdir$srcdir"
	subdir="${srcdir%/}"
	while [ -n "$subdir" ]; do
		chown --reference="$subdir" "$dstdir$subdir"
		chmod --reference="$subdir" "$dstdir$subdir"
		subdir="${subdir%/*}"
	done
fi

# Archivos de registro:
logfile="$dstdir/backup.log"
lastfile="$dstdir/last.log"

date +'%Y-%m-%d %T:' > "$lastfile"
echo "Origen: $srcdir" >> "$lastfile"
echo "Destino: $dstdir" >> "$lastfile"

# Se eliminan los archivos de respaldo que no existan en el directorio de origen:
let terased=$(date +%s)
let nerased=0
if [ -d "$dstdir$srcdir" ]; then
	while read f; do
		if [ -n "${f#$dstdir}" ] && [ ! -e "${f#$dstdir}" ]; then
			rm -rf "$f"
			echo "Borrado: '$f'." >> "$lastfile"
			let nerased+=1
		fi
	done< <(find -P "$dstdir$srcdir" -depth)
fi
let terased=$(date +%s)-$terased

# Se actualiza el directorio de destino:
let tcopied=$(date +%s)
let ncopied=0
while read f; do
	dst="$dstdir$f"
	let toexclude=0
	for i in $(seq 0 $((nexcludes-1))); do
		if [ "${f#${excludes[$i]}}" != "$f" ]; then
			let toexclude=1
			break
		fi
	done
	if [ $toexclude -eq 0 ]; then
		if [ -d "$f" ]; then
			if [ ! -e "$dst" ]; then
				mkdir "$dst"
			fi
			if [ -d "$dst" ]; then
				chown --reference="$f" "$dst"
				chmod --reference="$f" "$dst"
			else
				echo "No se pudo sobreescribir '$dst'." >> "$lastfile"
			fi
		elif [ -f "$f" ]; then
			if [[ ! -e "$dst" || ( -f "$dst" && "$f" -nt "$dst" ) ]]; then
				cp -vuP --preserve=all "$f" "$dst" >> "$lastfile"
				let ncopied+=1
			elif [ -e "$dst" ]; then
				echo "No se pudo sobreescribir '$dst'." >> "$lastfile"
			fi
		fi
	fi
done< <(find -P "$srcdir")

let tcopied=$(date +%s)-$tcopied

# Se registra lo sucedido:
date +'%Y-%m-%d %T:' | tee -a "$logfile" >&2
echo "Borrados $nerased archivos de '$dstdir$srcdir' en $(secs2str $terased)." | tee -a "$logfile" >&2
echo "Copiados $ncopied archivos de '$srcdir' en $(secs2str $tcopied)." | tee -a "$logfile" >&2