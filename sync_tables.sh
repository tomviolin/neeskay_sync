#!/bin/bash
cd ~/projects/neeskay
while true; do
	sleep 20
	# echo "[`date`]" >> sync_tables.log
	./invoke_sync.sh
done
