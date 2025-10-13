# SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# Dependencies:
# * make
# * curl: used to fetch composer from the web if not installed
# * tar: for building the archive

app_name=$(notdir $(CURDIR))
build_tools_directory=$(CURDIR)/build/tools
appstore_build_directory=$(CURDIR)/build/artifacts
appstore_package_name=$(appstore_build_directory)/$(app_name)
appstore_sign_dir=$(appstore_build_directory)/sign
cert_dir=$(build_tools_directory)/certificates
composer=$(shell which composer 2> /dev/null)
ifneq (,$(wildcard $(CURDIR)/../nextcloud/occ))
	occ=php $(CURDIR)/../nextcloud/occ
else ifneq (,$(wildcard $(CURDIR)/../../occ))
	occ=php $(CURDIR)/../../occ
endif

# Installs and updates the composer dependencies. If composer is not installed
# a copy is fetched from the web
.PHONY: composer
composer:
ifeq (,$(composer))
	@echo "No composer command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar $(build_tools_directory)
	php $(build_tools_directory)/composer.phar install --prefer-dist
else
	composer install --prefer-dist
endif

# Cleaning
.PHONY: clean
clean:
	rm -rf js/
	rm -rf $(appstore_build_directory)

# Builds the source package for the app store, ignores php and js tests
.PHONY: appstore
appstore:
	rm -rf $(appstore_build_directory)
	mkdir -p $(appstore_sign_dir)/$(app_name)
	cp -r \
		appinfo \
		css \
		composer \
		img \
		js \
		l10n \
		lib \
		templates \
		vendor \
		CHANGELOG.md \
		openapi*.json \
		$(appstore_sign_dir)/$(app_name)

	mkdir -p $(cert_dir)
	if [ -f $(cert_dir)/$(app_name).key ]; then \
		curl -o $(cert_dir)/$(app_name).crt \
			"https://raw.githubusercontent.com/nextcloud/app-certificate-requests/master/$(app_name)/$(app_name).crt"; \
		echo "Signing app files…"; \
		$(occ) integrity:sign-app \
			--privateKey=$(cert_dir)/$(app_name).key\
			--certificate=$(cert_dir)/$(app_name).crt\
			--path=$(appstore_sign_dir)/$(app_name); \
	fi
	tar -czf $(appstore_package_name).tar.gz \
		-C $(appstore_sign_dir) $(app_name)

	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(appstore_package_name).tar.gz | openssl base64; \
	fi
