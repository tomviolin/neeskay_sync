FROM php:7.4-cli

MAINTAINER Tom Hansen "tomh@uwm.edu"

RUN apt-get update
RUN apt-get update --fix-missing
RUN apt-get -y install cron openssh-client procps

RUN docker-php-ext-install mysqli

###  TIMEZONE FIX  (AIN'T IT PURRDY?) ###
RUN ln -fs /usr/share/zoneinfo/America/Chicago /etc/localtime
RUN dpkg-reconfigure --frontend noninteractive tzdata
COPY ./mkphptz.sh .
RUN ./mkphptz.sh


COPY . /home/tomh/projects/neeskay

RUN crontab < /home/tomh/projects/neeskay/crontab

WORKDIR /home/tomh/projects/neeskay


CMD cron -f
#CMD ./invoke_sync.sh
