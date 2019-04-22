#!/bin/bash
cp dbdump.sql olddbdump.sql
sqlite3 localDB.sqlite  > dbdump.sql <<EOF
.dump
.quit
EOF
diff olddbdump.sql dbdump.sql

