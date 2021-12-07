#!/bin/bash
cat > /usr/local/etc/php/php.ini << _END_
[date]
date.timezone=`cat /etc/timezone`
_END_

