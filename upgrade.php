<?php
/**
 * Upgrade-Routine: Was das Plugin selbst braucht, richtet es beim ersten Laden einer neuen Version
 * ein – auch nach einem automatischen Update, bei dem kein Setup-Skript läuft. Jeder Schritt prüft
 * selbst, ob er schon erledigt ist, und ist damit beliebig oft ausführbar.
 *
 * Neue Schritte unten anhängen und die Version im Plugin-Kopf erhöhen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		if ( get_option( 'vr_onboarding_version' ) === VITARIGHTS_ONBOARDING_VERSION ) {
			return;
		}
		vitarights_onboarding_upgrade();
		update_option( 'vr_onboarding_version', VITARIGHTS_ONBOARDING_VERSION );
	},
	5
);

function vitarights_onboarding_upgrade() {
	// Rolle für Support/Field Support: darf nur Partner einladen (Frontend /partner-einladen/ und
	// Admin-Seite „Partner“), sonst nur lesen. Admins dürfen es auch. Konten legt ein Admin unter
	// Benutzer → Neu mit der Rolle „Onboarding-Support“ an.
	if ( ! get_role( 'vr_onboarding_support' ) ) {
		add_role( 'vr_onboarding_support', 'Onboarding-Support', array( 'read' => true ) );
	}
	get_role( 'vr_onboarding_support' )->add_cap( VITARIGHTS_ONBOARDING_INVITE_CAP );
	get_role( 'administrator' )->add_cap( VITARIGHTS_ONBOARDING_INVITE_CAP );

	// Einladungsseite für den Support
	if ( ! get_page_by_path( VITARIGHTS_ONBOARDING_INVITE_PAGE ) ) {
		wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_name'    => VITARIGHTS_ONBOARDING_INVITE_PAGE,
				'post_title'   => 'Partner einladen',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:shortcode -->[vitarights_partner_invite]<!-- /wp:shortcode -->',
			)
		);
	}
}
