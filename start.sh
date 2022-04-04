#!/usr/bin/env bash
set -e
printenv > /etc/environment
cat /etc/environment
/usr/sbin/cron -f