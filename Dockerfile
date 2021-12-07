FROM php:7.4-cli

MAINTAINER Tom Hansen "tomh@uwm.edu"

###  TIMEZONE FIX  (AIN'T IT PURRDY?) ###
RUN ln -fs /usr/share/zoneinfo/America/Chicago /etc/localtime
RUN dpkg-reconfigure --frontend noninteractive tzdata
COPY ./mkphptz.sh .
RUN ./mkphptz.sh

RUN apt-get update
RUN apt-get -y install cron openssh-client procps


COPY . /home/tomh/projects/neeskay

RUN crontab < /home/tomh/projects/neeskay/crontab

RUN docker-php-ext-install mysqli

WORKDIR /home/tomh/projects/neeskay


CMD cron -f
#CMD ./invoke_sync.sh
