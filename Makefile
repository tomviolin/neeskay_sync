
all: build run

build:
	docker build -t neeskay_sync .

run:
	docker kill neeskay_sync || echo ""
	docker rm neeskay_sync || echo ""
	docker run -d --name neeskay_sync --restart always neeskay_sync
	#docker run -it --name neeskay_sync --rm -v /home/tomh/.ssh:/home/tomh/.ssh neeskay_sync

