CREATE TABLE "gpsStatus" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
	"date_time"	INTEGER,
	"latitude"	INTEGER,
	"longitude"	INTEGER,
	"elevation"	REAL,
	"sats_tracked"	INTEGER,
	"sync_state"	INTEGER
)