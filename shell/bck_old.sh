#!/usr/bin/env bash
# backup old version
# kill the running instance of adu11e_mcp before backing up
# avoid write access conflicts by killing the running instance first
killall adu11e_mcp

# move to _backup
outdir="/home/database_backup"  # replace with the actual backup directory
skip_dirs='lost+found'  # replace with actual directories to skip
indir="/home/database"  # replace with the actual directory of the old version
mkdir -p "$outdir"

# perform the backup, excluding specified directories
rsync -av --exclude="$skip_dirs" "$indir/" "$outdir/"
echo "Backup completed successfully."
echo "next step is to call inst_new.sh"
echo "DO NOT CALL BCK_OLD.SH AGAIN !!!!!"
# also consider backing up the web interface if needed
web_outdir="/home/web_interface_backup"  # replace with the actual backup directory for the web interface
web_skip_dirs='lost+found'  # replace with actual directories to skip for the web interface
web_indir="/www/pages/mobile"  # replace with the actual directory of the old web interface version
mkdir -p "$web_outdir"
rsync -av --exclude="$web_skip_dirs" "$web_indir/" "$web_outdir/"
echo "Web interface backup completed successfully."