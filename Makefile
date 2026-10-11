.PHONY: release zip

VERSION := $(shell sed -n 's/^ \* Version: *//p' vitarights-onboarding.php)
ZIP     := dist/vitarights-onboarding.zip

zip: ## Release-Zip bauen (nur Plugin-Dateien, siehe .gitattributes)
	@mkdir -p dist
	git archive --format=zip --prefix=vitarights-onboarding/ -o $(ZIP) HEAD
	@echo "$(ZIP) (v$(VERSION))"

release: ## v$(VERSION) als GitHub-Release veröffentlichen – Live-Seiten aktualisieren sich danach selbst
	@test -z "$$(git status --porcelain)" || { echo "Arbeitsverzeichnis nicht sauber – erst committen."; exit 1; }
	@grep -q "VITARIGHTS_ONBOARDING_VERSION = '$(VERSION)'" vitarights-onboarding.php || { echo "Version im Plugin-Kopf ($(VERSION)) und VITARIGHTS_ONBOARDING_VERSION weichen ab."; exit 1; }
	@git fetch -q --tags origin
	@! git rev-parse -q --verify "refs/tags/v$(VERSION)" >/dev/null || { echo "v$(VERSION) gibt es schon – Version im Plugin-Kopf erhöhen."; exit 1; }
	git push -q origin HEAD:main
	$(MAKE) -s zip
	gh release create "v$(VERSION)" $(ZIP) --target "$$(git rev-parse HEAD)" --title "v$(VERSION)" --generate-notes
