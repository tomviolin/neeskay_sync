FROM ubuntu:latest

MAINTAINER Tom Hansen "tomh@uwm.edu"

#RUN apt-get update ;\
#    apt-get install php8.1-mysql

# copy local content to its container home
#COPY . .
#RUN pwd
# fix timezone issue
#RUN ln -fs /usr/share/zoneinfo/America/Chicago /etc/localtime
#RUN dpkg-reconfigure --frontend noninteractive tzdata
#RUN ./mkphptz.sh

# set crontab
#RUN crontab < ./crontab

# launch container by running cron in foreground
#CMD cron -f
CMD /bin/sleep 99999
