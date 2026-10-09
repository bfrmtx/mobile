# Readme

**inst_new.sh** <br>
This script is used to install new components for the mobile application.
First calls `killall adu11e_mcp` to terminate the running process before installing new components.<br>


## Usage
```sh
./inst_new.sh 
```

Directories exchanged:

- /home/database
- /www/pages/mobile

Since mcp is not running now, reboot the system simply by calling `reboot` command.
