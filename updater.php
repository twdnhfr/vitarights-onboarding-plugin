<?php
/**
 * Updates aus den GitHub-Releases von twdnhfr/vitarights-onboarding-plugin über Plugin Update
 * Checker (lib/plugin-update-checker, v5.7, MIT). WordPress spielt neue Versionen automatisch ein
 * (Hintergrund-Updates), „Jetzt aktualisieren“ geht zusätzlich. Installiert wird nur das
 * Release-Zip (make release), nie der Quellcode.
 *
 * Aktiv nur in Produktion (WP_ENVIRONMENT_TYPE): lokal und auf Staging ist das Plugin
 * schreibgeschützt eingebunden und kommt aus dem Projekt-Repo.
 *
 * Das Repo ist öffentlich, ein Token ist nicht nötig. Optional in der wp-config.php, z. B. falls
 * sich viele Seiten eine IP teilen (GitHub erlaubt ohne Token 60 Abfragen pro Stunde und IP):
 *   define( 'VR_ONBOARDING_GITHUB_TOKEN', 'github_pat_…' );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Der Update-Checker (einmal je Request angelegt), null außerhalb der Produktion.
 */
function vitarights_onboarding_updater() {
	static $checker = null;
	if ( null !== $checker || 'production' !== wp_get_environment_type() ) {
		return $checker;
	}
	require_once __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php';
	$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/twdnhfr/vitarights-onboarding-plugin/',
		VITARIGHTS_ONBOARDING_FILE,
		'vitarights-onboarding'
	);
	if ( defined( 'VR_ONBOARDING_GITHUB_TOKEN' ) && VR_ONBOARDING_GITHUB_TOKEN ) {
		$checker->setAuthentication( VR_ONBOARDING_GITHUB_TOKEN );
	}
	$checker->getVcsApi()->enableReleaseAssets(
		'/^vitarights-onboarding\.zip$/',
		\YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api::REQUIRE_RELEASE_ASSETS
	);
	return $checker;
}

if ( ! vitarights_onboarding_updater() ) {
	return;
}

// Automatische Updates für dieses Plugin immer an (unabhängig vom Schalter in der Plugin-Liste)
add_filter(
	'auto_update_plugin',
	function ( $update, $item ) {
		return isset( $item->plugin ) && plugin_basename( VITARIGHTS_ONBOARDING_FILE ) === $item->plugin ? true : $update;
	},
	10,
	2
);
