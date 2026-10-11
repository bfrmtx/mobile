#!/usr/bin/env bash
# create new version from my repository

echo "render quarto? [y/N]"
read -r render_quarto
# all but y Y
if [[ "$render_quarto" == "y" || "$render_quarto" == "Y" ]]; then
    echo "ok, you want to render quarto first"
    exit 0
fi


# Climb from mobile/shell to the top of mobile.

# Initialize target as current directory
TARGET_DIR="$PWD"
tardir='mobile'

# Loop backwards until the current folder name is 'tardir'
while [[ "${TARGET_DIR:t}" != "$tardir" && "$TARGET_DIR" != "/" ]]; do
    TARGET_DIR="${TARGET_DIR:h}"
done

# We found 'mobile', now go up one more level to get the 'top of mobile' (which is "$tardir"/..)
TARGET_DIR="${TARGET_DIR:h}"

# Validate and navigate
if [[ ! -d "$TARGET_DIR" || "$TARGET_DIR" == "/" ]]; then
    echo "Error: Target directory does not exist or is root. Exiting."
    return 1 2>/dev/null || exit 1
else
    cd "$TARGET_DIR"
fi

echo "Navigated to top of $tardir directory: $TARGET_DIR"


# delete any existing new version tgz before creating a new one
rm -f new_version_mobile.tgz
# exclude folder .git, .github, qmd
tar --exclude='.git' --exclude='.github' --exclude='qmd' -czvf new_version_mobile.tgz "$tardir"
echo "Creation of new version mobile tgz completed successfully: new_version_mobile.tgz"
# delete old database templates
rm -f database_templates.tgz
# we tar pages/mobile/system/ADU-11e/database that way that we can unpack it later easily to /home/database
tar -C "mobile/system/ADU-11e" -czvf database_templates.tgz "joblists"
echo "Creation of new database templates tgz completed successfully: database_templates.tgz"