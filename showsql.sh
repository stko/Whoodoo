#!/bin/bash
sqlite3 localDB.sqlite  <<EOF
.dump
.quit
EOF
