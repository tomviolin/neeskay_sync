#!/bin/bash

cd `dirname $0`
./sync_tables.php trackingdata_flex ysi_layout
echo done syncing.
