#!/usr/bin/env bash
killall adu11e_mcp
#
base_dir="/home"
outdir="database"  # replace with the actual directory of the new version
infile="new_version_databases.tgz"  # replace with the actual directory of the new version
# check if the output directory exists, else exit with an error
if [ ! -d "$base_dir/$outdir" ]; then
    echo "Error: Output directory $base_dir/$outdir does not exist."
    exit 1
fi
# check if the input file exists, else exit with an error
if [ ! -f "$infile" ]; then
    echo "Error: Input file $infile does not exist."
    exit 1
fi
# -C already makes paths relative, so -P (unsupported on Yocto's busybox tar) isn't needed
tar -xzvf "$infile" -C "$base_dir"
echo "Installation of new version databases tgz completed successfully."
#
#
#
#  web interface installation checks
#

# check if the web interface output directory exists, else exit with an error
base_dir="/www/pages"
outdir="mobile"  # replace with the actual directory of the new version
infile="new_version_web.tgz"  # replace with the actual directory of the new version
if [ ! -d "$base_dir/$outdir" ]; then
    echo "Error: Output directory $base_dir/$outdir does not exist."
    exit 1
fi
# check if the web interface input file exists, else exit with an error
if [ ! -f "$infile" ]; then
    echo "Error: Input file $infile does not exist."
    exit 1
fi
mv -rf "$base_dir/$outdir" "$base_dir/${outdir}_backup"  # move the old web interface version to a backup before installing the new one
mkdir -p "$base_dir/$outdir"  # create the output directory for the new web interface version
#
#
# -C already makes paths relative, so -P (unsupported on Yocto's busybox tar) isn't needed
tar -xzvf "$infile" -C "$base_dir"
echo "Installation of new version web tgz completed successfully."
echo "you can reboot the system now."