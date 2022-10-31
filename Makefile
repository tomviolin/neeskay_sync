
all: build run

build:
	docker build --no-cache -t neeskay_sync .

run:
	docker kill neeskay_sync || echo ""
	docker rm neeskay_sync || echo ""
	docker run -d --name neeskay_sync --restart always neeskay_sync -v /home/tomh/.ssh:/home/tomh/.ssh
	#docker run -it --name neeskay_sync --rm -v /home/tomh/.ssh:/home/tomh/.ssh neeskay_sync

