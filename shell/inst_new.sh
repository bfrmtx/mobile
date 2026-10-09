#!/usr/bin/env bash
killall adu11e_mcp
#
#  web interface installation checks
#
# check if the web interface output directory exists, else exit with an error
DATE=$(date +%Y%m%d_%H%M%S)
base_dir="/www/pages"
outdir="mobile"                     # replace with the actual directory of the new version
infile="new_version_mobile.tgz"     # replace with the actual directory of the new version
if [ ! -d "$base_dir/$outdir" ]; then
    echo "Error: Output directory $base_dir/$outdir does not exist."
    mkdir -p "$base_dir/$outdir"
    echo created / prep "$base_dir/$outdir"
fi
# check if the web interface input file exists, else exit with an error
if [ ! -f "$infile" ]; then
    echo "Error: Input file $infile does not exist."
    exit 1
fi

mv  "$base_dir/$outdir" "$base_dir/${outdir}_backup_$DATE"    # move the old web interface version to a backup before installing the new one
mkdir -p "$base_dir/$outdir"                            # create the output directory for the new web interface version
echo created "$base_dir/$outdir"
#
#
# -C already makes paths relative, so -P (unsupported on Yocto's busybox tar) isn't needed
# tarfile is containing mobile/... so in /www/pages  we create /www/pages/mobile
tar -xzvf "$infile" -C "$base_dir"
# Set directory and regular-file permissions without making files executable.
find "$base_dir/$outdir" -type d -exec chmod 755 {} \;
find "$base_dir/$outdir" -type f -exec chmod 644 {} \;
echo "Installation of new version web tgz completed successfully."
echo now populating the new web database files.
PHP=$(which php)
script_dir="$base_dir/$outdir"/system/ADU-11e
if [ ! -d "$script_dir" ]; then
    echo "Error: Script directory $script_dir does not exist."
    exit 1
fi
DB_DIR="/home/database"
mv "$DB_DIR" "$DB_DIR"_backup_"$DATE"
echo "Database backed up to $DB_DIR"_backup_"$DATE"
mkdir -p "$DB_DIR"
mkdir -p "$DB_DIR/joblists"
$PHP "$script_dir"/create_system.php
chmod 755 "$DB_DIR"
chmod 755 "$DB_DIR/joblists"
chmod 666 "$DB_DIR/joblists"/*
chmod 666 "$DB_DIR"/*
# check if dir "$DB_DIR/joblists/system" exists, if so chmod 666 all files inside
if [ -d "$DB_DIR/joblists/system" ]; then
    chmod 755 "$DB_DIR/joblists/system"
    chmod 666 "$DB_DIR/joblists/system"/*
fi
chmod 666 "$DB_DIR/joblists/system"/*
echo "you can reboot the system now."
