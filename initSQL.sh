#!/bin/bash
rm localDB.sqlite
sqlite3 localDB.sqlite  <<EOF
.read $1
.quit
EOF
