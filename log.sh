#!/bin/bash

LOGFILE=/var/log/syslog
if [ ! -f "$LOGFILE" ]; then
  LOGFILE=/var/log/messages
fi
if [ ! -f "$LOGFILE" ]; then
  echo "File not found: $LOGFILE"
  exit
fi

SUDO=""
if [ ! -r "$LOGFILE" ]; then
  SUDO="sudo"
fi

if [ -z "$2" ]
then
  $SUDO tail -n100 -f "$LOGFILE" | grep --line-buffered "$1" | sed -u 's/^.\{25\}[^:]*: //g' | sed -u 's/\\/\\\\/g' | sed -u 's/#012/\n  /g' | { while read -e LINE; do echo -e "$LINE"; done }
else
  $SUDO tail -n100 -f "$LOGFILE" | grep --line-buffered "$1" | grep -C 6 --line-buffered "$2" | sed -u 's/^.\{25\}[^:]*: //g' | sed -u 's/\\/\\\\/g' | sed -u 's/#012/\n  /g' | { while read -e LINE; do echo -e "$LINE"; done }
fi
