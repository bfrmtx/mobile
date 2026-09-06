#!/usr/bin/env bash
# restore old version
killall adu11e_mcp

indir="/home/database_backup"  # replace with the actual backup directory
skip_dirs='lost+found'  # replace with actual directories to skip
outdir="/home/database"  # replace with the actual directory of the old version

# perform the restore, excluding specified directories
rsync -av --exclude="$skip_dirs" "$indir/" "$outdir/"
echo "Restore completed successfully."
echo "you can reboot the system now."