#!/bin/bash

cd `dirname $0`
mkdir /root/.ssh
cp /home/tomh/.ssh/* /root/.ssh
chown -R root.root /root/.ssh
SSHTUNNELCMD='ssh -f -p 14323 pforward@neeskay.bldg.sfs.uwm.edu -L 33306:127.0.0.1:3306 sleep 60'
$SSHTUNNELCMD
./sync_tables.php trackingdata_flex ysi_layout
echo done syncing.
pkill -9 -f "neeskay.bldg.sfs.uwm.edu -[L]"
