<?php
/**
 * Plugin Name: Vitarights Onboarding
 * Description: Kleine Anpassungen für das Partner-Onboarding (Tutor LMS + Magic Login).
 * Version:     1.7.5
 * Author:      Vitarights
 * Update URI:  https://github.com/twdnhfr/vitarights-onboarding-plugin
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Muss zur Version im Kopf passen (make release prüft das)
const VITARIGHTS_ONBOARDING_VERSION = '1.7.5';
const VITARIGHTS_ONBOARDING_FILE    = __FILE__;

require __DIR__ . '/partner-invite.php';
require __DIR__ . '/partner-admin.php';
require __DIR__ . '/upgrade.php';
require __DIR__ . '/updater.php';

/**
 * Der Kurs eines Partners, falls er in mehreren eingeschrieben ist (z. B. über den Magic Link
 * versehentlich zusätzlich im Basic-Kurs): abgeschlossene zuerst, dann höchster Fortschritt,
 * dann zuletzt eingeschrieben. Null ohne Einschreibung.
 */
function vitarights_onboarding_course_url( $user_id = 0 ) {
	$course_id = vitarights_onboarding_course_id( $user_id );
	return $course_id ? get_permalink( $course_id ) : null;
}

function vitarights_onboarding_course_id( $user_id = 0 ) {
	if ( ! function_exists( 'tutor_utils' ) ) {
		return null;
	}
	$user_id    = $user_id ? $user_id : get_current_user_id();
	$course_ids = tutor_utils()->get_enrolled_courses_ids_by_user( $user_id );
	if ( ! $course_ids ) {
		return null;
	}

	$ranked = array();
	foreach ( $course_ids as $course_id ) {
		$enrolled = tutor_utils()->is_enrolled( $course_id, $user_id );
		$ranked[] = array(
			'id'        => (int) $course_id,
			'completed' => tutor_utils()->is_completed_course( $course_id, $user_id ) ? 1 : 0,
			'progress'  => (int) tutor_utils()->get_course_completed_percent( $course_id, $user_id ),
			'enrolled'  => $enrolled ? strtotime( $enrolled->post_date_gmt ) : 0,
		);
	}
	usort(
		$ranked,
		function ( $a, $b ) {
			return array( $b['completed'], $b['progress'], $b['enrolled'] ) <=> array( $a['completed'], $a['progress'], $a['enrolled'] );
		}
	);

	return $ranked[0]['id'];
}

/**
 * Magic Login leitet alle nach dem Login fest auf den Basic-Kurs (Einstellung der alten Seite).
 * Partner, die schon eingeschrieben sind, stattdessen in ihren eigenen Kurs – sonst landen
 * Partner aus dem Partner-Kurs im falschen Kurs und schreiben sich dort versehentlich ein.
 */
add_filter(
	'magic_login_redirect',
	function ( $redirect_to, $user ) {
		// Support-Mitarbeiter auf die Einladungsseite statt in einen Kurs
		if ( user_can( $user, VITARIGHTS_ONBOARDING_INVITE_CAP ) ) {
			return home_url( '/' . VITARIGHTS_ONBOARDING_INVITE_PAGE . '/' );
		}
		$course_url = vitarights_onboarding_course_url( $user->ID );
		if ( $course_url ) {
			return $course_url;
		}
		// Noch kein Kurs: Nutzer anderer Sprachen in den Standardkurs ihrer Sprache statt in den deutschen
		$lang   = vitarights_onboarding_locale_lang( vitarights_onboarding_user_locale( $user ) );
		$course = 'de' === $lang ? null : vitarights_onboarding_lang_course( $lang );
		return $course ? get_permalink( $course ) : $redirect_to;
	},
	10000, // nach Magic Login selbst (9999, wenn „Weiterleitung erzwingen“ aktiv ist)
	2
);

/**
 * Die öffentlichen Registrierungsseiten (Fluent Forms 18/19/20) und /magic-login/ gibt es nicht
 * mehr – Partner legt jetzt der Support an. Alte Links und Lesezeichen auf die Startseite.
 * Vor redirect_canonical (Priorität 10), das sonst ähnlich klingende Seiten errät.
 */
add_action(
	'template_redirect',
	function () {
		global $wp;
		if ( ! is_404() ) {
			return;
		}
		$removed = array(
			'onboarding-login'         => '/',
			'partner-onboarding-login' => '/',
			'magic-login'              => '/',
			'onboarding-login-meghivo' => '/' . VITARIGHTS_ONBOARDING_PAGES['start']['hu'] . '/',
		);
		$target = $removed[ trim( $wp->request, '/' ) ] ?? null;
		if ( $target ) {
			wp_safe_redirect( home_url( $target ), 301 );
			exit;
		}
	},
	5
);

add_action(
	'template_redirect',
	function () {
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Die Magic-Link-Seiten sind nur für Gäste: Magic Login blendet sein Formular für
		// Eingeloggte aus, die Seite wäre sonst leer. Eingeloggte landen in ihrem Kurs, der Support
		// auf der Einladungsseite.
		if ( is_page( array_merge( array_values( VITARIGHTS_ONBOARDING_PAGES['login'] ), array_values( VITARIGHTS_ONBOARDING_PAGES['login-copy'] ) ) ) ) {
			if ( current_user_can( VITARIGHTS_ONBOARDING_INVITE_CAP ) ) {
				wp_safe_redirect( home_url( '/' . VITARIGHTS_ONBOARDING_INVITE_PAGE . '/' ) );
				exit;
			}
			wp_safe_redirect( vitarights_onboarding_course_url() ?? home_url( '/' ) );
			exit;
		}

		// Die Startseite ist eine Landingpage für Gäste; eingeschriebene Partner direkt ins Webinar.
		// Ohne Einschreibung (z. B. Admins) bleibt es bei der Startseite.
		if ( is_front_page() ) {
			$course_url = vitarights_onboarding_course_url();
			if ( $course_url ) {
				wp_safe_redirect( $course_url );
				exit;
			}
		}
	}
);

/**
 * Eigene Texte je Sprache aus languages/<locale>.php (de_DE, hu_HU, en_US, fr_FR). Deckt ab, was Tutor LMS 4,
 * Tutor Pro (ohne deutsche/ungarische Übersetzung) und Magic Login (Du-Form) Partnern zeigen.
 */
function vitarights_onboarding_strings( $locale = null ) {
	static $cache = array();
	$locale = $locale ? $locale : determine_locale();
	if ( ! isset( $cache[ $locale ] ) ) {
		$file             = __DIR__ . '/languages/' . basename( $locale ) . '.php';
		$cache[ $locale ] = is_readable( $file ) ? require $file : array();
	}
	return $cache[ $locale ];
}

add_filter(
	'gettext',
	function ( $translation, $text, $domain ) {
		$strings = vitarights_onboarding_strings();
		return $strings[ $domain ][ $text ] ?? $translation;
	},
	20,
	3
);

add_filter(
	'gettext_with_context',
	function ( $translation, $text, $context, $domain ) {
		$strings = vitarights_onboarding_strings();
		return $strings[ $domain ][ $context . "\004" . $text ] ?? $translation;
	},
	20,
	4
);

add_filter(
	'ngettext',
	function ( $translation, $single, $plural, $number, $domain ) {
		$strings = vitarights_onboarding_strings();
		$value   = $strings[ $domain ][ $single ] ?? null;
		if ( is_array( $value ) ) {
			return 1 === (int) $number ? $value[0] : $value[1];
		}
		return is_string( $value ) ? $value : $translation;
	},
	20,
	5
);

/**
 * Tutor-Pro-Mailvorlagen in der Sprache des Empfängers. Der Text („message“) ist in Tutor
 * fest auf Englisch hinterlegt und erwartet JSON-kodierten Inhalt.
 */
function vitarights_onboarding_email_templates( $options, $locale ) {
	$overrides = vitarights_onboarding_strings( $locale )['emails'] ?? array();
	foreach ( $overrides as $to_key => $triggers ) {
		foreach ( $triggers as $trigger => $fields ) {
			if ( isset( $fields['message'] ) ) {
				$fields['message'] = wp_json_encode( $fields['message'] );
			}
			$options[ $to_key ][ $trigger ] = array_merge( (array) ( $options[ $to_key ][ $trigger ] ?? array() ), $fields );
		}
	}
	return $options;
}

add_filter(
	'tutor_pro_user_email_template_option',
	function ( $options, $recipient ) {
		return vitarights_onboarding_email_templates( $options, vitarights_onboarding_user_locale( $recipient ) );
	},
	20,
	2
);

add_filter(
	'tutor_pro_email_template_data_option',
	function ( $options ) {
		return vitarights_onboarding_email_templates( $options, determine_locale() );
	},
	20
);

/**
 * Mail „Forgot Password“ in der Sprache des Partners. Tutor Pro baut sie in
 * retrieve_password_notification_email (Priorität PHP_INT_MAX) aus seinen Vorlagedaten, ohne die beiden
 * Filter oben – die Vorlage kommt deshalb kurz vorher direkt in Tutors Objekt und danach wieder heraus.
 * WordPress schaltet dafür zwar auf get_user_locale() um, bei leerem Profil ist das aber die gerade aktive
 * Sprache (z. B. der englischen Login-Seite oder des Admins), nicht die des Partners. Die festen Texte der
 * Vorlage (Button usw.) laufen über gettext und kommen so ebenfalls in seiner Sprache.
 */
add_filter(
	'retrieve_password_notification_email',
	function ( $email, $key, $user_login, $user_data ) {
		$tutor = null;
		foreach ( $GLOBALS['wp_filter']['retrieve_password_notification_email']->callbacks[ PHP_INT_MAX ] ?? array() as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof TUTOR_EMAIL\EmailNotification ) {
				$tutor = $callback['function'][0];
			}
		}
		if ( ! $tutor ) {
			return $email;
		}
		$locale   = vitarights_onboarding_user_locale( $user_data );
		$options  = $tutor->email_options;
		$switched = switch_to_locale( $locale );
		$tutor->email_options = vitarights_onboarding_email_templates( $options ? $options : array(), $locale );
		// Läuft nach Tutor (gleiche Priorität, später angemeldet)
		$restore = function ( $email ) use ( &$restore, $tutor, $options, $switched ) {
			remove_filter( 'retrieve_password_notification_email', $restore, PHP_INT_MAX );
			$tutor->email_options = $options;
			if ( $switched ) {
				restore_previous_locale();
			}
			return $email;
		};
		add_filter( 'retrieve_password_notification_email', $restore, PHP_INT_MAX );
		return $email;
	},
	PHP_INT_MAX - 1,
	4
);

/**
 * Abschlussmail an die Kursleitung („… hat den Kurs abgeschlossen“) an den Support: Kursautor ist
 * das Konto „Vitarights“ mit einer Adresse, die es nicht gibt. Antworten gehen an den Partner, und
 * der Button „See Student Report“ fällt weg – er führt ins Tutor-Dashboard, das der Support nicht hat.
 * Die Abschlussmail an den Partner bekommt eine Anrede in seiner Sprache – Tutor setzt nur „{Name},“.
 * Tutor verschickt beide Mails in tutor_course_complete_after (Priorität 10), der Abschluss wird
 * drumherum gemerkt. Dabei gilt die Sprache des Partners – auch wenn ein Admin den Abschluss im Backend
 * auslöst, sonst kämen Tutors feste Beschriftungen („Zertifikat herunterladen“) in dessen Sprache.
 */
const VITARIGHTS_ONBOARDING_SUPPORT_EMAIL = 'onboarding@vitarights.de';

add_action(
	'tutor_course_complete_after',
	function ( $course_id, $user_id ) {
		$locale = vitarights_onboarding_user_locale( $user_id );
		$GLOBALS['vitarights_onboarding_completion'] = array(
			'teacher'  => get_the_author_meta( 'user_email', get_post_field( 'post_author', $course_id ) ),
			'student'  => get_the_author_meta( 'user_email', $user_id ),
			'locale'   => $locale,
			// Nur zurückschalten, wenn wirklich umgeschaltet wurde (wie bei der Einladung)
			'switched' => switch_to_locale( $locale ),
		);
	},
	9,
	2
);

add_action(
	'tutor_course_complete_after',
	function () {
		if ( ! empty( $GLOBALS['vitarights_onboarding_completion']['switched'] ) ) {
			restore_previous_locale();
		}
		unset( $GLOBALS['vitarights_onboarding_completion'] );
	},
	11
);

add_filter(
	'wp_mail',
	function ( $args ) {
		$completion = $GLOBALS['vitarights_onboarding_completion'] ?? null;
		if ( $completion && $args['to'] === $completion['student'] ) {
			$greeting = vitarights_onboarding_strings( $completion['locale'] )['email_greeting'] ?? '';
			if ( $greeting ) {
				$args['message'] = preg_replace_callback(
					'#(<p class="tutor-email-greetings">)\s*(.*?),\s*(</p>)#s',
					fn ( $m ) => $m[1] . sprintf( $greeting, $m[2] ) . $m[3],
					$args['message']
				);
			}
			return $args;
		}
		if ( ! $completion || $args['to'] !== $completion['teacher'] ) {
			return $args;
		}
		$args['to']      = VITARIGHTS_ONBOARDING_SUPPORT_EMAIL;
		// Ohne Report-Button und ohne Anrede („vitarights,“); Tutor gibt „Student info“ unübersetzt aus
		$args['message'] = preg_replace(
			array( '#<div class="tutor-email-buttons[^"]*">.*?</div>#s', '#<p class="tutor-email-greetings">.*?</p>#s' ),
			'',
			$args['message']
		);
		$args['message'] = str_replace( '>Student info<', '>Partner<', $args['message'] );
		$args['headers'] = (array) $args['headers'];
		$args['headers'][] = 'Reply-To: ' . $completion['student'];
		return $args;
	}
);

/**
 * Logo im Kopf der Tutor-Mails: Die eingestellte Medien-ID (tutor_email_template_logo_id) stammt von
 * der alten Seite und existiert nicht mehr – ohne Bild nimmt Tutor-Pro keine Adresse, das Logo wäre
 * kaputt. Dann das Website-Logo.
 */
add_filter(
	'tutor_email_logo_src',
	function ( $url ) {
		return $url ? $url : wp_get_attachment_image_url( (int) get_option( 'site_logo' ), 'full' );
	},
	20
);

/**
 * Schon abgeschlossene Kurse nicht noch einmal abschließen: Tutors „Kurs abschließen“ (Formular und
 * AJAX) und „Als abgeschlossen markieren“ im Backend prüfen das nicht – jeder weitere Klick ergäbe einen
 * zweiten Abschluss mit neuem Zertifikat und neuen Mails. Stattdessen antworten, als hätte es geklappt.
 */
function vitarights_onboarding_already_completed( $user_id ) {
	$course_id = absint( $_POST['course_id'] ?? 0 );
	return $course_id && $user_id && tutor_utils()->is_completed_course( $course_id, $user_id, false );
}

add_action(
	'init',
	function () {
		if ( 'tutor_complete_course' === ( $_POST['tutor_action'] ?? '' ) && vitarights_onboarding_already_completed( get_current_user_id() ) ) {
			wp_safe_redirect( get_permalink( absint( $_POST['course_id'] ) ) );
			exit;
		}
	},
	9
);

add_action(
	'wp_ajax_tutor_complete_course',
	function () {
		if ( vitarights_onboarding_already_completed( get_current_user_id() ) ) {
			wp_send_json( array( 'status_code' => 200, 'success' => true, 'message' => __( 'Course completed successfully', 'tutor' ) ) );
		}
	},
	9
);

add_action(
	'wp_ajax_tutor_enrolled_course_complete',
	function () {
		$course_id  = absint( $_POST['course_id'] ?? 0 );
		$student_id = absint( $_POST['student_id'] ?? 0 );
		// Nur wer den Kurs bearbeiten darf – sonst ließe sich abfragen, wer abgeschlossen hat
		if ( tutor_utils()->can_user_edit_course( get_current_user_id(), $course_id ) && vitarights_onboarding_already_completed( $student_id ) ) {
			wp_send_json( array( 'status_code' => 200, 'success' => true, 'message' => __( 'Course completed successfully', 'tutor-pro' ) ) );
		}
	},
	9
);

/**
 * Passwort zurücksetzen über WordPress statt über das Tutor-Dashboard: Mit Tutors eigenem Login baut
 * Tutor den Reset-Link in der Mail und „Passwort vergessen?“ aus der Dashboard-Seite + „retrieve-password“.
 * Wir haben bewusst kein Tutor-Dashboard (Seiten-ID -1), daraus wurde nur der Pfad „/retrieve-password?…“
 * ohne Domain – im Mailprogramm ein kaputter Link (Apple Mail: „file://…“), auf der Website eine 404.
 * Ohne Dashboard zeigt der Link deshalb auf wp-login.php?action=lostpassword, und kommt dort ein Link
 * aus einer Tutor-Mail an (reset_key, user_id), geht es weiter zu WordPress' eigenem Reset-Formular.
 */
add_filter(
	'tutor_dashboard_url',
	function ( $url, $sub_url ) {
		if ( 'retrieve-password' !== $sub_url || 'publish' === get_post_status( tutor_utils()->dashboard_page_id() ) ) {
			return $url;
		}
		return wp_lostpassword_url();
	},
	20,
	2
);

add_action(
	'login_form_lostpassword',
	function () {
		$user = get_userdata( absint( $_GET['user_id'] ?? 0 ) );
		if ( ! $user || empty( $_GET['reset_key'] ) ) {
			return;
		}
		$args = array(
			'action' => 'rp',
			'key'    => sanitize_text_field( wp_unslash( $_GET['reset_key'] ) ),
			'login'  => rawurlencode( $user->user_login ),
		);
		wp_safe_redirect( add_query_arg( $args, network_site_url( 'wp-login.php', 'login' ) ) );
		exit;
	}
);

/**
 * Tutors Rundgang („Your learning experience just got a whole lot better“) bewirbt Dashboard-
 * Funktionen, die Partner hier nicht nutzen – für alle als bereits gesehen markieren.
 */
add_filter(
	'get_user_metadata',
	function ( $value, $object_id, $meta_key ) {
		return '_tutor_tour_completed' === $meta_key ? array( 1 ) : $value;
	},
	10,
	3
);

/**
 * Tutor 4.0.9 gibt das „Failed“-Badge fehlerhaft ohne __() aus (Quiz_Attempts_List::
 * render_quiz_attempt_list_badge) – auf Quiz-Seiten genau dieses Markup übersetzen.
 */
add_action(
	'template_redirect',
	function () {
		if ( ! is_singular( 'tutor_quiz' ) ) {
			return;
		}
		$label = __( 'Nicht bestanden', 'vitarights-onboarding' );
		ob_start(
			function ( $html ) use ( $label ) {
				return str_replace( 'tutor-badge-error tutor-badge-rounded">Failed<', 'tutor-badge-error tutor-badge-rounded">' . esc_html( $label ) . '<', $html );
			}
		);
	}
);

/*
 * ---------------------------------------------------------------------------------------------
 * Sprachen DE/HU/EN/FR (ersetzt WPML der alten Seite)
 *
 * - Sprachspezifische Seiten und Kurse bestimmen die Sprache selbst (Tabellen unten).
 * - Sonst gilt: eingeloggt → Profilsprache, Gast → Cookie „vr_lang“ (vom Umschalter), sonst Deutsch.
 * - Der Umschalter [vitarights_lang_switch] führt zum Gegenstück der aktuellen Seite und merkt
 *   sich die Wahl (Cookie bzw. Profilsprache).
 * - Einladung in einen Kurs setzt die Profilsprache auf dessen Sprache.
 * - Eigene Texte des Plugins sind deutsch im Code (__( '…', 'vitarights-onboarding' )), die
 *   Übersetzungen stehen in languages/<locale>.php.
 *
 * Neue Sprache: Eintrag in VITARIGHTS_ONBOARDING_LANGS, ihre Seiten und Kurse in den Tabellen
 * unten, languages/<locale>.php, Seiten/Kurs anlegen (Projekt: setup-onboarding.sh).
 * ---------------------------------------------------------------------------------------------
 */

// Kürzel => Locale; Deutsch ist die Grundsprache der Seite
const VITARIGHTS_ONBOARDING_LANGS = array(
	'de' => 'de_DE',
	'hu' => 'hu_HU',
	'en' => 'en_US',
	'fr' => 'fr_FR',
);

// Für Admin-Seite, Kursauswahl und Export (deutsch)
const VITARIGHTS_ONBOARDING_LANG_LABELS = array(
	'de' => 'Deutsch',
	'hu' => 'Ungarisch',
	'en' => 'Englisch',
	'fr' => 'Französisch',
);

// Seiten je Rolle und Sprache (Slugs). 'login-copy' ist eine zweite deutsche Login-Seite der alten
// Seite, ihre Gegenstücke sind die normalen Login-Seiten.
const VITARIGHTS_ONBOARDING_PAGES = array(
	'start'      => array(
		'de' => 'start',
		'hu' => 'kezdolap',
		'en' => 'welcome',
		'fr' => 'bienvenue',
	),
	'login'      => array(
		'de' => 'partner-magic-login',
		'hu' => 'magic-link',
		'en' => 'login-link',
		'fr' => 'lien-de-connexion',
	),
	'login-copy' => array(
		'de' => 'magic-login-copy',
		'hu' => 'magic-link',
		'en' => 'login-link',
		'fr' => 'lien-de-connexion',
	),
);

// Kurse je Sprache (Slugs). Der erste ist der Standardkurs der Sprache: Ziel des Umschalters,
// vorausgewählt bei der Einladung, nach dem Login ohne Einschreibung.
const VITARIGHTS_ONBOARDING_COURSES = array(
	'de' => array( 'partner-onboarding-basic-webinar', 'vitarights-basic-webinar' ),
	'hu' => array( 'vitarights-basic-webinar-hu' ),
	'en' => array( 'vitarights-basic-webinar-en' ),
	'fr' => array( 'vitarights-basic-webinar-fr' ),
);

/**
 * Kurs-IDs je Sprache (nur Kurse, die es gibt): [ 'de' => [ 111590, 111037 ], … ].
 */
function vitarights_onboarding_courses() {
	static $courses = null;
	if ( null === $courses ) {
		$courses = array();
		foreach ( VITARIGHTS_ONBOARDING_COURSES as $lang => $slugs ) {
			foreach ( $slugs as $slug ) {
				$course = get_page_by_path( $slug, OBJECT, 'courses' );
				if ( $course ) {
					$courses[ $lang ][] = (int) $course->ID;
				}
			}
		}
	}
	return $courses;
}

function vitarights_onboarding_lang_course( $lang ) {
	return vitarights_onboarding_courses()[ $lang ][0] ?? null;
}

function vitarights_onboarding_course_lang( $course_id ) {
	foreach ( vitarights_onboarding_courses() as $lang => $ids ) {
		if ( in_array( (int) $course_id, $ids, true ) ) {
			return $lang;
		}
	}
	return null;
}

/**
 * Locale eines Benutzers. Anders als get_user_locale() fällt ein leeres Profil (= Website-Sprache)
 * auf die Website-Sprache zurück und nicht auf die gerade aktive Sprache – sonst bekäme z. B. ein
 * deutscher Partner die Einladung in der Sprache dessen, der gerade einlädt.
 */
function vitarights_onboarding_user_locale( $user ) {
	$user_id = $user instanceof WP_User ? $user->ID : (int) $user;
	$locale  = $user_id ? get_user_meta( $user_id, 'locale', true ) : '';
	return $locale ? $locale : vitarights_onboarding_site_locale();
}

/**
 * Website-Sprache, festgehalten beim Laden des Plugins – vor jeder Umschaltung. Nicht aus der Option
 * WPLANG: Bei einer Installation aus dem deutschen Paket (wp core download --locale=de_DE, so auf
 * Linevast) ist sie leer und die Sprache kommt aus dem Paket; get_locale() kennt beide Quellen.
 */
function vitarights_onboarding_site_locale() {
	static $locale = null;
	if ( null === $locale ) {
		$locale = get_locale();
	}
	return $locale;
}
vitarights_onboarding_site_locale();

function vitarights_onboarding_locale_lang( $locale ) {
	return array_search( $locale, VITARIGHTS_ONBOARDING_LANGS, true ) ?: 'de';
}

/**
 * URL einer Seite (Rolle aus VITARIGHTS_ONBOARDING_PAGES) in einer Sprache; die deutsche
 * Startseite ist die Startseite der Website.
 */
function vitarights_onboarding_page_url( $role, $lang ) {
	if ( 'start' === $role && 'de' === $lang ) {
		return home_url( '/' );
	}
	$slug = VITARIGHTS_ONBOARDING_PAGES[ $role ][ $lang ] ?? VITARIGHTS_ONBOARDING_PAGES[ $role ]['de'];
	return home_url( '/' . $slug . '/' );
}

/**
 * Kurs-ID eines Kurses, Themas, einer Lektion oder eines Tests (über die Eltern-Kette).
 */
function vitarights_onboarding_course_of( $post ) {
	for ( $i = 0; $post && $i < 4; $i++ ) {
		if ( 'courses' === $post->post_type ) {
			return (int) $post->ID;
		}
		$post = $post->post_parent ? get_post( $post->post_parent ) : null;
	}
	return 0;
}

/**
 * Feste Sprache eines Inhalts ('de', 'hu', …) oder null, wenn er sprachneutral ist.
 */
function vitarights_onboarding_content_lang( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return null;
	}
	if ( 'page' === $post->post_type ) {
		foreach ( VITARIGHTS_ONBOARDING_PAGES as $slugs ) {
			$lang = array_search( $post->post_name, $slugs, true );
			if ( false !== $lang ) {
				return $lang;
			}
		}
		return null;
	}
	return vitarights_onboarding_course_lang( vitarights_onboarding_course_of( $post ) );
}

/**
 * Bevorzugte Sprache des Besuchers unabhängig von der aktuellen Seite.
 */
function vitarights_onboarding_preferred_lang() {
	if ( is_user_logged_in() ) {
		return vitarights_onboarding_locale_lang( vitarights_onboarding_user_locale( get_current_user_id() ) );
	}
	$cookie = sanitize_key( $_COOKIE['vr_lang'] ?? '' );
	return isset( VITARIGHTS_ONBOARDING_LANGS[ $cookie ] ) ? $cookie : 'de';
}

function vitarights_onboarding_current_lang() {
	return vitarights_onboarding_locale_lang( determine_locale() );
}

function vitarights_onboarding_switch_to( $lang ) {
	$locale = VITARIGHTS_ONBOARDING_LANGS[ $lang ] ?? 'de_DE';
	if ( get_locale() !== $locale ) {
		switch_to_locale( $locale );
	}
}

function vitarights_onboarding_remember_lang( $lang ) {
	setcookie( 'vr_lang', $lang, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	$_COOKIE['vr_lang'] = $lang;
	if ( is_user_logged_in() ) {
		update_user_meta( get_current_user_id(), 'locale', 'de' === $lang ? '' : VITARIGHTS_ONBOARDING_LANGS[ $lang ] );
	}
}

// Umschalter-Klick (?vr_lang=hu): merken und ohne Parameter weiterleiten
add_action(
	'init',
	function () {
		$lang = sanitize_key( $_GET['vr_lang'] ?? '' );
		if ( is_admin() || ! isset( VITARIGHTS_ONBOARDING_LANGS[ $lang ] ) ) {
			return;
		}
		vitarights_onboarding_remember_lang( $lang );
		wp_safe_redirect( remove_query_arg( 'vr_lang' ) );
		exit;
	},
	0
);

// Früh (für alles vor der Abfrage, z. B. Formular-Verarbeitung): bevorzugte Sprache
add_action(
	'init',
	function () {
		if ( ! is_admin() ) {
			vitarights_onboarding_switch_to( vitarights_onboarding_preferred_lang() );
		}
	},
	1
);

// Nach der Abfrage: sprachspezifische Seiten/Kurse haben Vorrang; Gäste merken sich die Sprache
add_action(
	'wp',
	function () {
		if ( is_admin() ) {
			return;
		}
		// Startseite: Gäste mit anderer Sprache auf die Startseite ihrer Sprache (vor dem Merken
		// unten, sonst würde die deutsche Startseite die Wahl gleich wieder auf Deutsch setzen)
		$preferred = vitarights_onboarding_preferred_lang();
		if ( is_front_page() && ! is_user_logged_in() && 'de' !== $preferred ) {
			wp_safe_redirect( vitarights_onboarding_page_url( 'start', $preferred ) );
			exit;
		}
		$lang = vitarights_onboarding_content_lang( get_queried_object() );
		if ( $lang ) {
			vitarights_onboarding_switch_to( $lang );
			if ( ! is_user_logged_in() && $preferred !== $lang ) {
				vitarights_onboarding_remember_lang( $lang );
			}
		}
	},
	0
);

/**
 * URL des Gegenstücks der aktuellen Seite in der Sprache $lang: dieselbe Seite bzw. der
 * Standardkurs der Sprache, sonst deren Startseite.
 */
function vitarights_onboarding_counterpart_url( $lang ) {
	$post = get_queried_object();
	$url  = null;

	if ( $post instanceof WP_Post && 'page' === $post->post_type ) {
		foreach ( VITARIGHTS_ONBOARDING_PAGES as $role => $slugs ) {
			if ( in_array( $post->post_name, $slugs, true ) ) {
				$url = vitarights_onboarding_page_url( $role, $lang );
				break;
			}
		}
	} elseif ( $post instanceof WP_Post && vitarights_onboarding_course_of( $post ) ) {
		$target = vitarights_onboarding_lang_course( $lang );
		$url    = $target ? get_permalink( $target ) : null;
	}

	return add_query_arg( 'vr_lang', $lang, $url ? $url : vitarights_onboarding_page_url( 'start', $lang ) );
}

add_shortcode(
	'vitarights_lang_switch',
	function () {
		$current = vitarights_onboarding_current_lang();
		$links   = array();
		foreach ( array_keys( VITARIGHTS_ONBOARDING_LANGS ) as $lang ) {
			$label   = strtoupper( $lang );
			$links[] = $lang === $current
				? '<span class="vr-lang__current" aria-current="true">' . esc_html( $label ) . '</span>'
				: '<a href="' . esc_url( vitarights_onboarding_counterpart_url( $lang ) ) . '" hreflang="' . esc_attr( $lang ) . '" lang="' . esc_attr( $lang ) . '">' . esc_html( $label ) . '</a>';
		}
		return '<nav class="vr-lang" aria-label="' . esc_attr__( 'Sprache', 'vitarights-onboarding' ) . '">' . implode( '<span class="vr-lang__sep" aria-hidden="true">|</span>', $links ) . '</nav>';
	}
);

add_action(
	'wp_head',
	function () {
		echo '<style>'
			// Sprachumschalter
			. '.vr-lang{display:flex;gap:.5em;align-items:center;font-size:.9rem;letter-spacing:.05em}.vr-lang a{text-decoration:none;color:inherit;opacity:.6}.vr-lang a:hover{opacity:1}.vr-lang__current{font-weight:600}.vr-lang__sep{opacity:.35}'
			// Kursbild auf der Kursseite: fester 3:2-Rahmen (Format des deutschen Kursbilds), andere
			// Formate (z. B. das hochkante Produktbild der EN/FR-Kurse) eingepasst statt in voller Höhe
			. '.tutor-course-thumbnail{aspect-ratio:3/2;background:#f3f5fa;overflow:hidden}'
			. '.tutor-course-thumbnail img{display:block;width:100%;height:100%;object-fit:contain}'
			// Startseite (scripts/front-page.php): Hero schließt an den Header an, Überzeile, Schritt-Nummern
			. '.page-template-page-no-title main{margin-top:0!important;padding-top:0!important}'
			. '.vr-eyebrow{margin:0;color:#253a7a;font-size:.8rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase}'
			. '.vr-hero-image img{display:block;width:100%;height:auto;max-height:440px;object-fit:contain}'
			. '.vr-step-title{font-weight:600}'
			// Seitentitel im Kopfbereich im Blau des Signets
			. 'header .wp-block-site-title a{color:#253a7a}'
			// Nutzer-Buttons der Startseite (eigene Klassen, unabhängig von den Theme-Button-Stilen)
			. '.vr-user-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:var(--wp--preset--spacing--40)}'
			. '.vr-btn{display:inline-block;padding:.95rem 2.1rem;border:2px solid #253a7a;border-radius:8px;font-size:1.05rem;line-height:1.2;text-decoration:none;transition:background-color .15s,color .15s}'
			. '.vr-btn--primary{background:#253a7a;color:#fff}.vr-btn--primary:hover{background:#1b2c5e;border-color:#1b2c5e;color:#fff}'
			. '.vr-btn--outline{background:transparent;color:#253a7a}.vr-btn--outline:hover{background:#253a7a;color:#fff}'
			. '.vr-user-note{margin-top:1rem;color:#5b6275;font-size:.9rem}'
			. '.vr-step-number{display:flex;align-items:center;justify-content:center;width:2.4rem;height:2.4rem;margin:0;border-radius:50%;background:#253a7a;color:#fff;font-weight:600}'
			. '</style>';
	}
);

// Datumsformat der Sprache – die Website-Einstellung („j. F Y“) ist deutsch. Steht als
// 'date_format' in languages/<locale>.php, ohne Eintrag bleibt die Einstellung.
add_filter(
	'option_date_format',
	function ( $format ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $format;
		}
		return vitarights_onboarding_strings()['date_format'] ?? $format;
	}
);

// Magic Login: Betreff und Einladungsmail in der aktuellen Sprache (Deutsch steht in der DB)
add_filter(
	'option_magic_login_settings',
	function ( $settings ) {
		if ( ( is_admin() && ! wp_doing_ajax() ) || ! is_array( $settings ) ) {
			return $settings;
		}
		$overrides = vitarights_onboarding_strings()['magic_login_settings'] ?? array();
		return array_merge( $settings, $overrides );
	}
);

/**
 * Den „Login“-Eintrag der Kopfnavigation (→ /partner-magic-login/) nur Gästen zeigen.
 */
add_filter(
	'render_block_core/navigation-link',
	function ( $content, $block ) {
		$url = $block['attrs']['url'] ?? '';
		if ( false === strpos( $url, '/partner-magic-login/' ) ) {
			return $content;
		}
		if ( is_user_logged_in() ) {
			return '';
		}
		// Login-Seite und Beschriftung in der aktuellen Sprache
		$lang    = vitarights_onboarding_current_lang();
		$content = str_replace( home_url( '/partner-magic-login/' ), vitarights_onboarding_page_url( 'login', $lang ), $content );
		return str_replace( '>Login<', '>' . esc_html__( 'Login', 'vitarights-onboarding' ) . '<', $content );
	},
	10,
	2
);

// Bleibt danach kein Eintrag übrig, die ganze Navigation weglassen – sonst zeigt sie mobil
// einen Menü-Button, der ein leeres Menü öffnet
add_filter(
	'render_block_core/navigation',
	function ( $content ) {
		if ( is_user_logged_in() && false === strpos( $content, 'wp-block-navigation-item' ) ) {
			return '';
		}
		return $content;
	}
);

/**
 * Footer wie auf vitarights.com (Firmensitz, Vertriebsniederlassung, Kontakt, Rechtliches) –
 * als Shortcode, damit Überschriften und Linktexte der Sprache folgen. Die Rechtsseiten liegen
 * auf vitarights.com (nur Deutsch).
 */
add_shortcode(
	'vitarights_footer',
	function () {
		$l = array(
			__( 'Firmensitz', 'vitarights-onboarding' ),
			__( 'Vertriebsniederlassung', 'vitarights-onboarding' ),
			__( 'Kontakt', 'vitarights-onboarding' ),
			__( 'Rechtliches', 'vitarights-onboarding' ),
			__( 'Impressum', 'vitarights-onboarding' ),
			__( 'Datenschutz', 'vitarights-onboarding' ),
			__( 'Cookie Richtlinien', 'vitarights-onboarding' ),
			__( 'All Rights Reserved.', 'vitarights-onboarding' ),
		);

		$col = function ( $title, $body ) {
			return '<div class="vr-footer__col"><h2 class="vr-footer__title">' . esc_html( $title ) . '</h2>' . $body . '</div>';
		};

		return '<div class="vr-footer"><div class="vr-footer__inner">'
			. $col( $l[0], '<p>Vitarights Innovations GmbH<br>Bussardweg 3<br>41468 Neuss</p>' )
			. $col( $l[1], '<p>Vitarights Innovations GmbH<br>Hertelsbrunnenring 2<br>67657 Kaiserslautern</p>' )
			. $col( $l[2], '<p><a href="tel:+4921313878500">+49 2131 38785-0</a></p><p><a href="mailto:info@vitarights.de">info@vitarights.de</a></p>' )
			. $col(
				$l[3],
				'<p><a href="https://vitarights.com/impressum/">' . esc_html( $l[4] ) . '</a></p>'
				. '<p><a href="https://vitarights.com/datenschutzerklaerung/">' . esc_html( $l[5] ) . '</a></p>'
				. '<p><a href="https://vitarights.com/cookie-richtlinie-eu/">' . esc_html( $l[6] ) . '</a></p>'
			)
			. '</div><div class="vr-footer__bar">© ' . esc_html( $l[7] ) . ' ' . gmdate( 'Y' ) . '</div></div>';
	}
);

add_action(
	'wp_head',
	function () {
		echo '<style>'
			. '.vr-footer{background:#f7f4ee;color:#46506a}'
			. '.vr-footer__inner{display:grid;grid-template-columns:repeat(4,1fr);gap:2rem;max-width:var(--wp--style--global--wide-size,1340px);margin:0 auto;padding:4rem var(--wp--style--root--padding-right,1.5rem) 3rem var(--wp--style--root--padding-left,1.5rem)}'
			. '.vr-footer__title{margin:0 0 1.1rem;color:#1b2338;font-size:1rem;font-weight:600;letter-spacing:0}'
			. '.vr-footer p{margin:0 0 .8rem;font-size:.95rem;line-height:1.6}'
			. '.vr-footer a{color:inherit;text-decoration:none}.vr-footer a:hover{color:#253a7a;text-decoration:underline}'
			. '.vr-footer__bar{background:#1b2338;color:#e8eaf0;text-align:center;padding:.9rem 1rem;font-size:.85rem;letter-spacing:.06em}'
			. '@media (max-width:900px){.vr-footer__inner{grid-template-columns:repeat(2,1fr)}}'
			. '@media (max-width:520px){.vr-footer__inner{grid-template-columns:1fr;padding-top:2.5rem}}'
			. '</style>';
	}
);

/**
 * Startseite für Eingeloggte: Gast-Buttons (Login-Link) ausblenden – die führen eingeloggt ins
 * Leere – und stattdessen „Zum Webinar“ (Support: „Partner einladen“) / „Abmelden“ zeigen.
 */
add_filter(
	'render_block',
	function ( $content, $block ) {
		$class = $block['attrs']['className'] ?? '';
		if ( is_user_logged_in() && false !== strpos( $class, 'vr-guest-only' ) ) {
			return '';
		}
		return $content;
	},
	10,
	2
);

add_shortcode(
	'vitarights_user_actions',
	function () {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$lang   = vitarights_onboarding_current_lang();
		$l      = array(
			__( 'Zum Webinar', 'vitarights-onboarding' ),
			__( 'Abmelden', 'vitarights-onboarding' ),
			/* translators: %s: Name */
			__( 'Angemeldet als %s.', 'vitarights-onboarding' ),
		);
		$course = vitarights_onboarding_course_url() ?? get_permalink( vitarights_onboarding_lang_course( $lang ) );
		$logout = wp_logout_url( vitarights_onboarding_page_url( 'start', $lang ) );
		if ( current_user_can( VITARIGHTS_ONBOARDING_INVITE_CAP ) ) {
			$course = home_url( '/' . VITARIGHTS_ONBOARDING_INVITE_PAGE . '/' );
			$l[0]   = 'Partner einladen'; // Support-Oberfläche ist nur deutsch
		}

		return '<div class="vr-user-actions">'
			. '<a class="vr-btn vr-btn--primary" href="' . esc_url( $course ) . '">' . esc_html( $l[0] ) . '</a>'
			. '<a class="vr-btn vr-btn--outline" href="' . esc_url( $logout ) . '">' . esc_html( $l[1] ) . '</a>'
			. '</div><p class="vr-user-note">' . esc_html( sprintf( $l[2], wp_get_current_user()->display_name ) ) . '</p>';
	}
);

/**
 * {{EXPIRES_WITH_INTERVAL}} in den Login-Mails: Magic Login macht daraus „14 tag(e)“ bzw. in
 * anderen Sprachen „14 day(s)“ – hier sprachlich korrekt aus der eingestellten Laufzeit.
 */
add_filter(
	'magic_login_email_placeholders',
	function ( $placeholders ) {
		$settings = get_option( 'magic_login_settings' );
		$minutes  = (int) ( $settings['token_ttl'] ?? 0 );
		if ( $minutes <= 0 ) {
			return $placeholders;
		}

		if ( 0 === $minutes % 1440 ) {
			$n = $minutes / 1440;
			/* translators: %d: Anzahl Tage */
			$text = _n( '%d Tag', '%d Tage', $n, 'vitarights-onboarding' );
		} elseif ( 0 === $minutes % 60 ) {
			$n = $minutes / 60;
			/* translators: %d: Anzahl Stunden */
			$text = _n( '%d Stunde', '%d Stunden', $n, 'vitarights-onboarding' );
		} else {
			$n = $minutes;
			/* translators: %d: Anzahl Minuten */
			$text = _n( '%d Minute', '%d Minuten', $n, 'vitarights-onboarding' );
		}
		$placeholders['{{EXPIRES_WITH_INTERVAL}}'] = sprintf( $text, $n );

		return $placeholders;
	}
);
