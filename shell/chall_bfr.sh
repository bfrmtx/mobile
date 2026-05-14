#!/usr/bin/zsh
### RUN THIS SCRIPT as sudo chall_bfr.sh
# put your username here, because the script runs at root (sudo)
#
# resolve paths from script location (portable; independent of current working directory)
SCRIPT_DIR="${0:A:h}"
APP_DIR="${SCRIPT_DIR:h}"
cd "$APP_DIR" || {
  echo "Error: could not change directory to application path: $APP_DIR"
  exit 1
}

# detect common web-root prefixes based on APP_DIR
web_root=""
for candidate in /srv/http /var/www /var/www/html /usr/share/nginx/html; do
  case "$APP_DIR/" in
    "$candidate"/*|"$candidate"/)
      web_root="$candidate"
      break
      ;;
  esac
done
if [ -n "$web_root" ]; then
  echo "Detected web root: $web_root"
else
  echo "Warning: no common web root detected from app path: $APP_DIR"
fi

acl_user="bfr"
www_user=$(grep -E "^(www-data|http)" /etc/passwd | cut -d: -f1)
if [ -z "$www_user" ]; then
  echo "No web server user found. Please ensure you have a web server installed and running."
  echo using www-data as default
  www_user="www-data"
fi
FIND="$(which find)"
# now we we make the standard permissions for a web server, which is 755 for directories and 644 for files
"$FIND" "$APP_DIR" -type d -exec chmod 755 {} \;
"$FIND" "$APP_DIR" -type f -exec chmod 644 {} \;
# change the owner to the web server user
chown -R "$www_user:$www_user" "$APP_DIR"
# the TRICK is to acl for the current user (portable: dirs need x, files should not)
# the capital X option does not work reliably, so we need to use a workaround with find:
"$FIND" "$APP_DIR" -type d -exec setfacl -m 'u:'"$acl_user"':rwx' {} \;
"$FIND" "$APP_DIR" -type d -exec setfacl -d -m 'u:'"$acl_user"':rwx' {} \;
"$FIND" "$APP_DIR" -type f -exec setfacl -m 'u:'"$acl_user"':rw' {} \;

# apply the same permissions to known database roots if they exist
database_dirs=(
  "/home/database"
  "/home/$acl_user/adu_database"
)

found_database_dir=0
for db_dir in "${database_dirs[@]}"; do
  if [ ! -d "$db_dir" ]; then
    continue
  fi

  found_database_dir=1
  chmod 755 "$db_dir"
  "$FIND" "$db_dir/" -type d -exec chmod 755 {} \;
  "$FIND" "$db_dir/" -type f -exec chmod 644 {} \;
  chown "$www_user:$www_user" "$db_dir"
  echo setting acl for user "$acl_user" on "$db_dir"
  "$FIND" "$db_dir/" -type d -exec setfacl -m 'u:'"$acl_user"':rwx' {} \;
  "$FIND" "$db_dir/" -type d -exec setfacl -d -m 'u:'"$acl_user"':rwx' {} \;
  "$FIND" "$db_dir/" -type f -exec setfacl -m 'u:'"$acl_user"':rw' {} \;
  echo "Permissions for $db_dir have been set."
done

if [ "$found_database_dir" -eq 0 ]; then
  echo "Error: no database directory found. Checked: /home/database and /home/$acl_user/adu_database"
  exit 1
fi

echo "Permissions and ownership have been set successfully."

