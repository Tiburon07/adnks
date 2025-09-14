build:
	COMPOSE_BAKE=true docker compose -f docker-compose.yml up --build -d --remove-orphans
	sudo docker image prune -af

up:
	docker compose -f docker-compose.yml up -d

down:
	docker compose -f docker-compose.yml down

down-v:
	docker compose -f docker-compose.yml down -v

show-logs:
	docker compose -f docker-compose.yml logs