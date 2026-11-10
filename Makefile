# Makefile — entorno de desarrollo del aplicativo de solicitudes de intervención.
# Ejecuta `make help` (o simplemente `make`) para ver los targets disponibles.

# Use npx so CI (no global install) and local dev both work.
# Invoke the package by its real name (@wordpress/env). Using the bare `wp-env`
# binary name makes npx fetch an unrelated decoy package (wp-env@1.0.1) when the
# dependency is not installed locally, which just prints a redirect message.
# Override with: make up WP_ENV="npx @wordpress/env"
WP_ENV = npx @wordpress/env

# Path of this repository inside the wp-env containers (see .wp-env.json mappings).
PSE_DEV = wp-content/pse-dev

# El destino puede ser un subsitio de un multisitio, y en una red wp-cli manda
# al sitio principal salvo que se le diga a cuál. El wp-env local es de un solo
# sitio, así que PSE_URL va vacío y no se añade nada; contra una red se pasa la
# dirección del subsitio:
#
#   make provision PSE_URL=http://localhost:8958/solicitudes
PSE_URL =
WP_URL = $(if $(PSE_URL),--url=$(PSE_URL),)

# ─── Ayuda ────────────────────────────────────────────────────────────────────

help: ## Muestra esta ayuda
	@echo "Comandos disponibles:"
	@echo ""
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'
	@echo ""

# ─── Instalación y entorno (Docker, wp-env) ───────────────────────────────────

install: ## Instala las dependencias de Composer y npm en el host
	composer install
	npm install

bundle: ## Empaqueta src/Pse → snippets/pse-psicologia-app.bundle.php (Code Snippets)
	php build/pack-snippet.php

# Internal: fail early if Docker is not running.
check-docker:
	@docker version > /dev/null || (echo "" && echo "Error: Docker no está en marcha. Arranca Docker e inténtalo de nuevo." && echo "" && exit 1)

# Internal: abre en el portátil el almacén de documentos del sitio local.
# Internal: start wp-env only if port 8958 does not answer.
start-if-not-running: check-docker
	@if [ "$$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8958)" = "000" ]; then \
		echo "El entorno no está en marcha. Arrancando wp-env..."; \
		$(WP_ENV) start --update; \
	else \
		echo "El entorno ya está en marcha en el puerto 8958; no se arranca de nuevo."; \
	fi

up: check-docker start-if-not-running ## Arranca el entorno de desarrollo y lo provisiona
	@$(MAKE) provision
	@echo ""
	@echo "Entorno listo:"
	@echo "  Sitio:            http://localhost:8958"
	@echo "  Administración:   http://localhost:8958/wp-admin/"
	@echo "  Entorno de tests: http://localhost:8959"
	@echo "  Credenciales:     admin / password"
	@echo "  Demo:             direccion | direccion2 | psicologia | psicologia2 | coordinacion  (password)"

down: ## Para el entorno de desarrollo
	$(WP_ENV) stop

# wp-env v11 replaced "clean" with "reset".
clean: check-docker ## Resetea los entornos (desarrollo y tests) y los vuelve a provisionar
	$(WP_ENV) reset development
	$(WP_ENV) reset tests
	@$(MAKE) provision

destroy: ## Destruye por completo los entornos de wp-env
	$(WP_ENV) destroy

logs: ## Muestra los logs del entorno
	$(WP_ENV) logs

shell: ## Abre una shell bash en el contenedor CLI
	$(WP_ENV) run cli bash

# ─── Provisión (idioma, bundle, snippets, roles, demo) ──

# El orden importa y scripts/check-provision.mjs lo comprueba paso a paso: si
# aquí se añade, se quita o se mueve un paso, hay que tocar también su tupla.
provision: ## Provisiona: idioma, bundle, snippets, roles y datos de demostración
	@echo "Instalando el idioma es_ES..."
	@$(WP_ENV) run cli wp $(WP_URL) language core install es_ES --activate || echo "AVISO: no se pudo instalar o activar es_ES; se continúa con el idioma disponible."
	@$(MAKE) bundle
	@$(MAKE) sync-snippets
	@echo "Asegurando los roles del aplicativo..."
	@$(WP_ENV) run cli wp $(WP_URL) eval-file $(PSE_DEV)/scripts/provision-roles.php
	@echo "Usuarios, centros, zonas y solicitudes de prueba..."
	@$(WP_ENV) run cli wp $(WP_URL) eval-file $(PSE_DEV)/scripts/seed-demo.php

seed-demo: start-if-not-running ## Rehace los datos de demostración: usuarios, centros, zonas y solicitudes
	@$(WP_ENV) run cli wp $(WP_URL) eval-file $(PSE_DEV)/scripts/seed-demo.php

sync-snippets: ## Sincroniza snippets/*.php con el plugin Code Snippets
	@echo "Sincronizando snippets..."
	@$(WP_ENV) run cli wp $(WP_URL) eval-file $(PSE_DEV)/scripts/sync-snippets.php

snippet-check: start-if-not-running ## Comprueba que los snippets PSE sobreviven al guardado de Code Snippets (doble eval)
	@$(WP_ENV) run cli wp $(WP_URL) eval-file $(PSE_DEV)/scripts/snippet-check.php

# ─── Publicación ──────────────────────────────────────────────────────────────

# Version comes from the CHANGELOG heading, never from the tag: the bundle is
# committed before tagging, so a tag-derived version could never match it.
# The '#' must be escaped: in a variable assignment make would treat it as a
# comment and swallow the rest of the $(shell ...) call.
PSE_VERSION = $(shell grep -m1 -o '^\#\# \[[0-9.]*\]' CHANGELOG.md | sed 's/[^0-9.]//g')

release: ## Etiqueta la versión del CHANGELOG y publica la release en GitHub
	@test -n "$(PSE_VERSION)" || { echo "Sin cabecera '## [X.Y.Z]' en CHANGELOG.md"; exit 1; }
	@git diff --quiet && git diff --cached --quiet || { echo "Árbol sucio: haz commit antes de publicar."; exit 1; }
	@$(MAKE) --no-print-directory bundle
	@git diff --quiet -- snippets/ || { echo "El bundle no estaba regenerado: haz commit de snippets/ y repite."; exit 1; }
	@grep -q "@version $(PSE_VERSION)" snippets/pse-psicologia-app.bundle.php \
		|| { echo "El bundle no lleva la versión $(PSE_VERSION)."; exit 1; }
	@git rev-parse -q --verify "refs/tags/v$(PSE_VERSION)" >/dev/null \
		&& { echo "El tag v$(PSE_VERSION) ya existe: sube la versión en CHANGELOG.md."; exit 1; } || true
	@echo "Publicando v$(PSE_VERSION)..."
	@awk '/^## \[/{if (n++) exit} n' CHANGELOG.md | tail -n +2 > /tmp/pse-release-notes.md
	git tag -a "v$(PSE_VERSION)" -m "v$(PSE_VERSION)"
	git push origin "v$(PSE_VERSION)"
	gh release create "v$(PSE_VERSION)" --title "v$(PSE_VERSION)" --notes-file /tmp/pse-release-notes.md
	@echo "Publicada v$(PSE_VERSION). Los snippets se despliegan después en el sitio de destino."

# ─── Tests ────────────────────────────────────────────────────────────────────

# PHPUnit 9.6 no carga un fichero suelto cuyo nombre no coincida con su clase
# («Class test-foo could not be found»), así que FILE= se traduce a un filtro
# por la clase que el fichero declara; FILTER= lo acota al método.
test: start-if-not-running ## Ejecuta los tests de PHPUnit (admite FILE=... y FILTER=...)
	@CMD="./vendor/bin/phpunit"; PATRON="$(FILTER)"; \
	if [ -n "$(FILE)" ]; then \
		CLASE=$$(grep -m1 -oE '^class +[A-Za-z0-9_]+' "$(FILE)" | awk '{print $$2}'); \
		test -n "$$CLASE" || { echo "No encuentro la clase de test en $(FILE)"; exit 1; }; \
		PATRON="^$$CLASE::.*$(FILTER)"; \
	fi; \
	if [ -n "$$PATRON" ]; then CMD="$$CMD --filter $$PATRON"; fi; \
	$(WP_ENV) run tests-cli --env-cwd=$(PSE_DEV) $$CMD --colors=always

# Los guiones de assets/js con Vitest y jsdom: sin wp-env ni navegador, así que
# entra en `check`. Mientras no haya guiones pasa en vacío (`passWithNoTests`). La cobertura queda en artifacts/coverage-js/.
test-js: ## Ejecuta los tests unitarios de los guiones de assets/js (Vitest)
	npm run test:js

# Xdebug solo está en los contenedores si se arrancaron con --xdebug, así que
# medir la cobertura pasa por reiniciar el entorno con él y dejarlo luego como
# estaba: con Xdebug puesto todo va más lento. En CI no se restaura, que la
# máquina se tira al acabar. El clover sale en coverage.xml (lo sube Codecov).
coverage: ## Mide la cobertura de src/Pse (reinicia wp-env con Xdebug)
	@$(WP_ENV) start --xdebug=coverage
	@$(WP_ENV) run tests-cli --env-cwd=$(PSE_DEV) ./vendor/bin/phpunit \
		--coverage-filter src/Pse --coverage-text --coverage-clover coverage.xml; \
	ESTADO=$$?; \
	[ -n "$$CI" ] || $(WP_ENV) start > /dev/null; \
	exit $$ESTADO

# ─── Lint y calidad de código ─────────────────────────────────────────────────

lint: ## Comprueba el estilo del código con PHPCS
	./vendor/bin/phpcs

# phpcbf exits 1 when it fixed something, so tolerate exit codes 0/1.
check-public: ## Comprueba que no se filtra nada que no pueda ir a un repositorio público
	node scripts/check-public.mjs

fix: ## Corrige automáticamente el estilo del código con PHPCBF
	./vendor/bin/phpcbf || [ $$? -eq 1 ]

# El workflow de PHPMD llama a este mismo target pidiendo SARIF; si el formato
# y los extras no fueran variables, `make phpmd PHPMD_FORMAT=sarif
# PHPMD_EXTRA=--reportfile-sarif ...` seguiría escupiendo texto y el fichero de
# informe no llegaría a existir.
PHPMD_FORMAT = text
PHPMD_EXTRA =

# error_reporting hides phpmd's own deprecation notices on PHP >= 8.4 hosts.
# `.local` queda fuera como vendor: es el cajón de exportaciones y capturas del
# entorno local, no código nuestro. Un `--exclude` por patrón y anclado entre
# barras: PHPMD compara con la ruta absoluta, así que `wp` a secas excluye el
# repo entero, que vive en `.../wp-*/` (con PHPMD 2 no se analizaba nada), y la
# lista separada por comas de PHPMD 2 ya no vale. Los avisos que ya había están
# en phpmd.baseline.xml, que PHPMD aplica solo: falla con los avisos nuevos.
# Para rehacerla: `make phpmd PHPMD_EXTRA=--update-baseline`.
phpmd: ## Ejecuta PHP Mess Detector con las reglas de phpmd.xml
	@php -d error_reporting='E_ALL & ~E_DEPRECATED' ./vendor/bin/phpmd analyze . --format $(PHPMD_FORMAT) --ruleset phpmd.xml --exclude "*/vendor/*" --exclude "*/node_modules/*" --exclude "*/wp/*" --exclude "*/.local/*" $(PHPMD_EXTRA)

check-provision: ## Comprueba que la provisión propaga los fallos obligatorios
	node scripts/check-provision.mjs

check: lint phpmd check-public check-provision check-skills test-js test ## Ejecuta lint, phpmd, check-public, check-provision, check-skills, test-js y tests

# ─── Plugin Check ─────────────────────────────────────────────────────────────
#
# Plugin Check son las comprobaciones con las que WordPress.org revisa un
# plugin: escapado tardío, saneado, consultas directas, i18n, encolados. Este
# repositorio **no es un plugin** (ADR-0001) y no va a serlo, pero el código sí
# es el mismo que corre dentro de WordPress, así que las comprobaciones valen.
#
# Para poder pasarlas se monta un envoltorio **desechable**: una carpeta de
# plugin con una cabecera de mentira y los snippets dentro, que se crea, se
# revisa y se borra. Nada de eso entra en el repositorio ni se despliega.
#
# Dos comprobaciones quedan fuera, y por escrito:
#
#   plugin_readme     No hay readme.txt ni lo va a haber: esto no se sube al
#                     directorio de WordPress.org. AGENTS.md lo prohíbe.
#   offloading_files  Las librerías se cargan desde jsDelivr con SRI, y es una
#                     decisión tomada (ADR-0002), no un descuido.
#
# **Los avisos también tumban.** No se pasa `--ignore-warnings`: con esas dos
# exclusiones el código sale a cero avisos, así que tragárselos solo serviría
# para que el primero que aparezca no lo vea nadie. Y por eso el recuento mira
# ERROR **y** WARNING: quitar la bandera sin contar los avisos habría sido no
# quitarla.

PLUGIN_CHECK_SLUG = pse-psicologia
PLUGIN_CHECK_DIR = .pse-plugin-check

check-plugin: start-if-not-running bundle ## Pasa WordPress Plugin Check sobre el código de los snippets
	@npx wp-env run cli wp plugin install plugin-check --activate --color > /dev/null 2>&1 || true
	@rm -rf $(PLUGIN_CHECK_DIR)
	@mkdir -p $(PLUGIN_CHECK_DIR)/$(PLUGIN_CHECK_SLUG)
	@cp snippets/*.php $(PLUGIN_CHECK_DIR)/$(PLUGIN_CHECK_SLUG)/
	@php build/plugin-check-wrapper.php $(PLUGIN_CHECK_DIR)/$(PLUGIN_CHECK_SLUG)/$(PLUGIN_CHECK_SLUG).php
	@npx wp-env run cli sh -c "rm -rf wp-content/plugins/$(PLUGIN_CHECK_SLUG) && cp -R wp-content/pse-dev/$(PLUGIN_CHECK_DIR)/$(PLUGIN_CHECK_SLUG) wp-content/plugins/$(PLUGIN_CHECK_SLUG)" > /dev/null
	@echo "Pasando WordPress Plugin Check..."
	@INFORME=$$(mktemp); \
	npx wp-env run cli wp plugin check $(PLUGIN_CHECK_SLUG) \
		--exclude-checks=plugin_readme,offloading_files \
		--color 2>&1 | tee "$$INFORME"; \
	ERRORES=$$(sed 's/\x1B\[[0-9;]*[mK]//g' "$$INFORME" | grep -cE '\b(ERROR|WARNING)\b' || true); \
	rm -f "$$INFORME"; \
	npx wp-env run cli rm -rf wp-content/plugins/$(PLUGIN_CHECK_SLUG) > /dev/null 2>&1 || true; \
	rm -rf $(PLUGIN_CHECK_DIR); \
	if [ "$$ERRORES" -gt 0 ]; then \
		echo "Plugin Check: $$ERRORES aviso(s) o error(es). El comando sale con 0 aunque los haya, así que lo que manda es este recuento."; \
		exit 1; \
	fi; \
	echo "Plugin Check: sin errores ni avisos."

# ─── Skills de agentes ────────────────────────────────────────────────────────
#
# Las canónicas viven en .agents/skills/ y .claude/skills/ lleva una COPIA, no
# un enlace: en Windows los enlaces simbólicos no funcionan sin habilitarlos a
# mano, y `gh skill` tampoco enlaza, copia. El precio de duplicar es que las dos
# pueden divergir, así que `make check` lo comprueba.

skills-sync: ## Iguala .claude/skills/ con las canónicas de .agents/skills/
	@rm -rf .claude/skills
	@mkdir -p .claude/skills
	@for d in .agents/skills/*/; do cp -R "$$d" ".claude/skills/$$(basename $$d)"; done
	@echo "Skills sincronizadas: $$(ls -1 .claude/skills | wc -l | tr -d ' ')"

check-skills: ## Comprueba que las dos copias de las skills son iguales
	@salida=0; \
	for d in .agents/skills/*/ .claude/skills/*/; do \
		n=$$(basename "$$d"); \
		if [ ! -d ".agents/skills/$$n" ] || [ ! -d ".claude/skills/$$n" ]; then \
			echo "Skills: $$n está en una carpeta y no en la otra."; salida=1; \
		elif ! diff -r ".agents/skills/$$n" ".claude/skills/$$n" > /dev/null 2>&1; then \
			echo "Skills: $$n no coincide entre .agents/ y .claude/."; salida=1; \
		fi; \
	done; \
	if [ "$$salida" != "0" ]; then echo "Ejecute: make skills-sync"; exit 1; fi; \
	echo "Skills: las dos copias coinciden."

# ─── WordPress Playground (local, sin Docker) ─────────────────────────────────

playground: ## Arranca WordPress Playground local (sin Docker) con el repo montado
	npx @wp-playground/cli@latest server \
		--blueprint=blueprint-local.json \
		--mount=.:/wordpress/wp-content/pse-dev \
		--mount=./scripts/mu-plugins:/wordpress/wp-content/mu-plugins \
		--login

# Set help as the default target if no target is specified
.DEFAULT_GOAL := help
