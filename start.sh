#!/usr/bin/env bash
set -e
printenv > /etc/environment
/usr/sbin/cron -f