# Vitarights Onboarding (WordPress-Plugin)

Plugin für das Vitarights-Partner-Onboarding (Tutor LMS + Magic Login): Partner einladen
(`/partner-einladen/` und Admin-Seite „Partner“), Partnerübersicht mit CSV-Export, Weiterleitungen
nach dem Login, Sprachen DE/HU, Texte und Mails.

Das Projekt drumherum (Docker, Deploy, Setup-Skripte, Doku) liegt in einem separaten, privaten
Repo; dort ist dieses Repo als Submodul unter `plugin/vitarights-onboarding` eingebunden.

## Lizenz

GPL-2.0-or-later (`LICENSE`). Enthält Plugin Update Checker (MIT, `lib/plugin-update-checker/license.txt`).

## Release

1. Version im Plugin-Kopf **und** in `VITARIGHTS_ONBOARDING_VERSION` erhöhen (`vitarights-onboarding.php`).
2. Committen, dann `make release`.

`make release` prüft, dass alles committet ist und die Version neu ist. Dann pusht es `main` und
erstellt das GitHub-Release `vX.Y.Z` mit `vitarights-onboarding.zip` (nur Plugin-Dateien, ohne
`Makefile`/`README.md`, siehe `.gitattributes`). Braucht die GitHub-CLI `gh`.

## Updates auf der Live-Seite

`updater.php` (Plugin Update Checker v5.7 in `lib/`) holt neue Releases aus diesem öffentlichen
Repo, WordPress spielt sie automatisch ein. Aktiv nur bei `WP_ENVIRONMENT_TYPE` = `production`;
lokal und auf Staging ist das Plugin schreibgeschützt eingebunden.

Ein Token ist nicht nötig. Optional, falls sich viele Seiten eine IP teilen (GitHub erlaubt ohne
Token 60 Abfragen pro Stunde und IP), in der `wp-config.php`:

```php
define( 'VR_ONBOARDING_GITHUB_TOKEN', 'github_pat_…' );
```

Token dann als Fine-grained token nur für dieses Repository mit *Contents: Read-only*.

Wer dieses Repo oder das Konto kontrolliert, kann Code auf die Live-Seite bringen – Zwei-Faktor-
Anmeldung bei GitHub ist Pflicht.

## Upgrade-Routine

Ein Update tauscht nur Dateien. Was das Plugin selbst braucht (Rolle „Onboarding-Support“,
Seite `/partner-einladen/`), richtet `upgrade.php` beim ersten Laden einer neuen Version ein.
Neue Schritte dort anhängen, sie müssen wiederholbar sein.
