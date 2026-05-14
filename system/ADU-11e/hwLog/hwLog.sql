CREATE TABLE "hwStatus" (
	"id" INTEGER PRIMARY KEY AUTOINCREMENT,
	"date_time"	INTEGER,
	"batt_state"	INTEGER,
	"batt_voltage_1"	REAL,
	"batt_voltage_2"	REAL,
	"temperature_system"	REAL,
	"temperature_sensor"	REAL,
	"free_disk_space_mb"	REAL,
	"selftest_status"	INTEGER,
	"recording_status"	INTEGER
);
