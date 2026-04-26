SHELL := /bin/bash

DOCKER_COMPOSE := docker compose
BACKEND_SERVICE := backend
FRONTEND_SERVICE := frontend
BACKEND_USER := www-data
LOCAL_UID ?= $(shell id -u)
LOCAL_GID ?= $(shell id -g)

.PHONY: help up down build rebuild ps logs-backend logs-frontend bash-backend sh-backend composer artisan migrate key npm-frontend fix-perms

help:
	@echo "Available commands:"
	@echo "  make up                Start backend, postgres and frontend"
	@echo "  make down              Stop containers"
	@echo "  make build             Build images"
	@echo "  make rebuild           Rebuild and restart backend + postgres + frontend"
	@echo "  make ps                Show container status"
	@echo "  make logs-backend      Tail backend logs"
	@echo "  make logs-frontend     Tail frontend logs"
	@echo "  make bash-backend      Open bash in backend container as www-data"
	@echo "  make sh-backend        Open sh in backend container as www-data"
	@echo "  make composer cmd=...  Run composer inside backend as www-data"
	@echo "  make artisan cmd=...   Run artisan inside backend as www-data"
	@echo "  make migrate           Run php artisan migrate"
	@echo "  make key               Run php artisan key:generate"
	@echo "  make npm-frontend cmd=... Run npm inside frontend container"
	@echo "  make fix-perms         Fix backend ownership on host"

up:
	$(DOCKER_COMPOSE) up -d backend postgres frontend

down:
	$(DOCKER_COMPOSE) down

build:
	$(DOCKER_COMPOSE) build

rebuild:
	$(DOCKER_COMPOSE) up -d --build backend postgres frontend

ps:
	$(DOCKER_COMPOSE) ps

logs-backend:
	$(DOCKER_COMPOSE) logs -f $(BACKEND_SERVICE)

logs-frontend:
	$(DOCKER_COMPOSE) logs -f $(FRONTEND_SERVICE)

bash-backend:
	$(DOCKER_COMPOSE) exec --user $(BACKEND_USER) $(BACKEND_SERVICE) bash

sh-backend:
	$(DOCKER_COMPOSE) exec --user $(BACKEND_USER) $(BACKEND_SERVICE) sh

composer:
	@test -n "$(cmd)" || (echo "Use: make composer cmd='install'" && exit 1)
	$(DOCKER_COMPOSE) exec --user $(BACKEND_USER) $(BACKEND_SERVICE) composer $(cmd)

artisan:
	@test -n "$(cmd)" || (echo "Use: make artisan cmd='migrate'" && exit 1)
	$(DOCKER_COMPOSE) exec --user $(BACKEND_USER) $(BACKEND_SERVICE) php artisan $(cmd)

migrate:
	$(DOCKER_COMPOSE) exec --user $(BACKEND_USER) $(BACKEND_SERVICE) php artisan migrate

key:
	$(DOCKER_COMPOSE) exec --user $(BACKEND_USER) $(BACKEND_SERVICE) php artisan key:generate

npm-frontend:
	@test -n "$(cmd)" || (echo "Use: make npm-frontend cmd='install @huggingface/transformers'" && exit 1)
	$(DOCKER_COMPOSE) exec $(FRONTEND_SERVICE) npm $(cmd)

fix-perms:
	docker run --rm -v "$(PWD)/backend:/work" alpine sh -c 'chown -R $(LOCAL_UID):$(LOCAL_GID) /work'