BUILD_ID := $(shell date +"%s%N")
COMPOSER_BIN ?= $(shell which composer)
ESBUILD_TARGET_DIRECTORY ?= docs/build/assets
PHP_BIN ?= $(shell which php)

CSS_SOURCES := $(wildcard resources/css/*.css)
MD_SOURCES := \
	$(wildcard docs/pages/*.md) \
	$(wildcard docs/pages/*/*.md) \
	$(wildcard docs/pages/*/*/*.md) \
	$(wildcard docs/pages/*/*/*/*.md) \
	$(wildcard docs/pages/*/*/*/*/*.md)
PHP_SOURCES := \
	$(wildcard app/*.php) \
	$(wildcard app/*/*.php)
TS_SOURCES := \
	$(wildcard resources/ts/*.js) \
	$(wildcard resources/ts/*.jsx) \
	$(wildcard resources/ts/*.ts) \
	$(wildcard resources/ts/*.tsx) \
	$(wildcard resources/ts/*/*.js) \
	$(wildcard resources/ts/*/*.jsx) \
	$(wildcard resources/ts/*/*.ts) \
	$(wildcard resources/ts/*/*.tsx) \
	$(wildcard resources/ts/*/*/*.js) \
	$(wildcard resources/ts/*/*/*.jsx) \
	$(wildcard resources/ts/*/*/*.ts) \
	$(wildcard resources/ts/*/*/*.tsx)

CSS_ENTRYPOINTS := $(wildcard resources/css/docs-*.css)
TS_ENTRYPOINTS := \
	$(wildcard resources/ts/docs/controller_*.ts) \
	$(wildcard resources/ts/docs/global_*.ts)

# -----------------------------------------------------------------------------
# Real targets
# -----------------------------------------------------------------------------

config.ini: config.ini.example
	cp config.ini.example config.ini;
	sed -i 's/build_id = "A"/build_id = "$(BUILD_ID)"/g' config.ini;

docs/artifact.tar: docs/build
	tar \
		--dereference --hard-dereference \
		-cvf "docs/artifact.tar" \
		--exclude=.git \
		--exclude=.github \
		--directory "docs/build" .

docs/build: config.ini esbuild vendor $(MD_SOURCES) $(PHP_SOURCES)
	${PHP_BIN} ./bin/resonance.php static-pages:build;
	cp resources/images/favicon.ico docs/build/favicon.ico;

node_modules: yarn.lock
	yarnpkg install --check-files --frozen-lockfile --non-interactive;
	touch node_modules;

tools/php-cs-fixer/vendor/bin/php-cs-fixer:
	$(MAKE) -C tools/php-cs-fixer vendor

vendor: composer.lock
	${PHP_BIN} ${COMPOSER_BIN} install --no-interaction --prefer-dist --optimize-autoloader;
	touch vendor;

yarn.lock: package.json
	yarnpkg install;
	touch yarn.lock;

# -----------------------------------------------------------------------------
# Phony targets
# -----------------------------------------------------------------------------

.PHONY: esbuild
esbuild: $(CSS_SOURCES) node_modules
	rm -rf $(ESBUILD_TARGET_DIRECTORY)/*;

	./node_modules/.bin/esbuild \
		--asset-names="./[name]_$(BUILD_ID)" \
		--bundle \
		--define:__APP_ENV=\"$(APP_ENV)\" \
		--define:__BUILD_ID=\"$(BUILD_ID)\" \
		--define:__CACHE_BUST_QS=\"$(CACHE_BUST_QS)\" \
		--define:__STATIC_PATH=\"$(STATIC_PATH)\" \
		--define:__WEBSOCKET_URL=\"$(WEBSOCKET_URL)\" \
		--define:global=globalThis \
		--define:process.env.MIX_NODE_ENV=\"$(NODE_ENV)\" \
		--define:process.env.MIX_SENTRY_DSN=\"test\" \
		--entry-names="./[name]_$(BUILD_ID)" \
		--format=esm \
		--log-limit=0 \
		--loader:.frag=text \
		--loader:.jpg=file \
		--loader:.otf=file \
		--loader:.png=file \
		--loader:.svg=file \
		--loader:.ttf=file \
		--loader:.vert=text \
		--loader:.webp=file \
		--loader:.woff2=file \
		--metafile=esbuild-meta-docs.json \
		--minify \
		--outdir=./$(ESBUILD_TARGET_DIRECTORY) \
		--platform=browser \
		--sourcemap \
		--splitting \
		--target=es2022,safari16 \
		--tree-shaking=true \
		--tsconfig=tsconfig.json \
		$(CSS_ENTRYPOINTS) \
		$(TS_ENTRYPOINTS) \
	;

.PHONY: fmt
fmt: php-cs-fixer

.PHONY: php-cs-fixer
php-cs-fixer: tools/php-cs-fixer/vendor/bin/php-cs-fixer
	./tools/php-cs-fixer/vendor/bin/php-cs-fixer --allow-risky=yes fix

.PHONY: psalm
psalm: tools/psalm/vendor/bin/psalm vendor
	./tools/psalm/vendor/bin/psalm \
		--no-cache \
		--show-info=true \
		--root=$(CURDIR)

.PHONY: psalm.watch
psalm.watch: node_modules vendor
	./node_modules/.bin/nodemon \
		--ext ini,php \
		--signal SIGTERM \
		--watch ./app \
		--watch ./config.schema.php \
		--watch ./constants.php \
		--watch ./resonance \
		--watch ./src \
		--exec '$(MAKE) psalm || exit 1'

.PHONY: ssg
ssg: docs/build

.PHONY: ssg.watch
ssg.watch: node_modules
	./node_modules/.bin/nodemon \
		--ext css,ini,md,php,ts \
		--signal SIGTERM \
		--watch ./app \
		--watch ./docs/pages \
		--watch ./resonance \
		--watch ./resources \
		--exec '$(MAKE) ssg || exit 1'

.PHONY: ssg.serve
ssg.serve: ssg node_modules
	./node_modules/.bin/esbuild --serve=8080 --servedir=docs/build

.PHONY: tsc
tsc: node_modules
	./node_modules/.bin/tsc --noEmit

.PHONY: tsc.watch
tsc.watch: node_modules
	./node_modules/.bin/tsc --noEmit --watch
