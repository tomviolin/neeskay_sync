#!/bin/sh

if [ "x$1" = "x" ]; then
	arg=help
else
	arg=$1
fi

cd ~/projects/neeskay
pgrep=`pgrep sync_tables.sh`

case $1 in

start)
	if [ "x$pgrep" = "x" ]; then
		# not running, start it
		./sync_tables.sh >/dev/null 2>&1 &
		exec $0 status
	else
		echo "sync_tables already running, pid=$pgrep."
	fi
	;;

stop)
	if [ "x$pgrep" = "x" ]; then
		# not running
		echo "sync_tables not running."
	else
		kill $pgrep
		sleep 0.05
		pgrep=`pgrep sync_tables.sh`
		if [ "x$pgrep" = "x" ]; then
			echo "sync_tables stopped."
		else
			echo "sync_tables not stopped!"
			exit 1
		fi
	fi
	;;

status)
	if [ "x$pgrep" = "x" ]; then
		echo "sync_tables is stopped."
	else
		echo "sync_tables is running, pid=$pgrep."
	fi
	;;

watchdog)
	if [ "x$pgrep" = "x" ]; then
		#process not running!
		echo "sync_tables not running, starting..."
		cd ~/projects/neeskay/
		./sync_tables_ctl.sh start
	fi
	;;

*)
	echo "usage: $0 (start|stop|status|watchdog)"
	;;

esac

