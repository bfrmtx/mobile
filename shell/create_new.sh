#!/usr/bin/env bash
# create new version from running ADU
killall adu11e_mcp
#
base_dir="/home" 
indir="database"  # replace with the actual directory of the new version
extra_file="apps/adu11e_mcp"  # relative to $base_dir, like $indir
# skip_dirs='lost+found'  # replace with actual directories to skip NOT ON YOCTO
outfile="new_version_databases.tgz"  # replace with the actual directory where the new version should be created
# -C already makes paths relative, so -P (unsupported on Yocto's busybox tar) isn't needed
tar -czvf "$outfile" -C "$base_dir" "$indir" "$extra_file"
echo "Creation of new version databases tgz completed successfully: $outfile"
#
# now the web interface 
#
base_dir="/www/pages"
indir="mobile"  # new web interface version
outfile="new_version_web.tgz"
tar -czvf "$outfile" -C "$base_dir" "$indir"
echo "Creation of new version web tgz completed successfully: $outfile"
