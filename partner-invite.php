<?php
/**
 * Partner einladen: Der Vitarights-Support legt neue Partner auf /partner-einladen/ an (ersetzt die
 * öffentliche Registrierung über Fluent Forms). Pro Einladung: Abonnent anlegen, Sprache aus dem
 * Kurs setzen, im Kurs einschreiben, Einladungsmail mit Magic Link schicken. Darunter eine
 * Übersicht aller Partner mit Status und „Link erneut senden“.
 *
 * Zugriff hat, wer das Recht vr_invite_partners hat: Rolle „Onboarding-Support“ und Admins
 * (angelegt von scripts/setup-onboarding.sh). Die Seite ist nur auf Deutsch.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VITARIGHTS_ONBOARDING_INVITE_CAP  = 'vr_invite_partners';
const VITARIGHTS_ONBOARDING_INVITE_PAGE = 'partner-einladen';

// Status-Marken (Frontend-Übersicht und Admin-Liste)
const VITARIGHTS_ONBOARDING_STATUS_CSS = '.vr-status{display:inline-block;padding:.2rem .6rem;border-radius:999px;font-size:.85rem;line-height:1.5;white-space:nowrap}'
	. '.vr-status--done{background:#e3f2e8;color:#1e5c34}.vr-status--progress,.vr-status--login{background:#e8ecf7;color:#253a7a}'
	. '.vr-status--open{background:#fdf3e1;color:#7a4b00}.vr-status--none{background:#eee;color:#555}';

/**
 * Kurse zur Auswahl: alle Kurse aller Sprachen (VITARIGHTS_ONBOARDING_COURSES), der erste ist
 * vorausgewählt. Die Sprache ergibt sich aus dem Kurs.
 */
function vitarights_onboarding_invite_courses() {
	return array_merge( ...array_values( vitarights_onboarding_courses() ) );
}

function vitarights_onboarding_course_label( $course_id ) {
	$lang = vitarights_onboarding_course_lang( $course_id ) ?? 'de';
	return get_the_title( $course_id ) . ' (' . VITARIGHTS_ONBOARDING_LANG_LABELS[ $lang ] . ')';
}

// Letzter Login – für den Status „noch nicht eingeloggt“ in der Übersicht
add_action(
	'wp_login',
	function ( $login, $user ) {
		update_user_meta( $user->ID, 'vr_last_login', time() );
	},
	10,
	2
);

/**
 * Einladungsmail von Magic Login in der Sprache des Partners (Betreff und Text tauscht der Filter
 * auf magic_login_settings je nach Sprache aus).
 */
function vitarights_onboarding_send_invite( WP_User $user ) {
	// Magic Login verschickt höchstens eine Mail pro Request und meldet danach trotzdem Erfolg
	if ( did_action( 'magic_login_send_login_link' ) ) {
		return false;
	}
	switch_to_user_locale( $user->ID );
	$sent = \MagicLogin\LoginManager::send_login_link( $user );
	restore_previous_locale();

	if ( $sent ) {
		update_user_meta( $user->ID, 'vr_invited_at', time() );
		update_user_meta( $user->ID, 'vr_invited_by', get_current_user_id() );
	}
	return $sent;
}

/**
 * Partner anlegen (oder vorhandenen Partner erneut einladen), einschreiben und Mail schicken.
 *
 * @return array{status: string, user: WP_User}|WP_Error
 */
function vitarights_onboarding_invite_partner( $first_name, $last_name, $email, $course_id ) {
	if ( '' === $first_name || '' === $last_name ) {
		return new WP_Error( 'name', 'Bitte Vor- und Nachnamen eintragen.' );
	}
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'email', 'Bitte eine gültige E-Mail-Adresse eintragen.' );
	}
	if ( ! in_array( $course_id, vitarights_onboarding_invite_courses(), true ) ) {
		return new WP_Error( 'course', 'Bitte einen Kurs auswählen.' );
	}

	$user   = get_user_by( 'email', $email );
	$status = 'resent';
	if ( $user && ! in_array( 'subscriber', $user->roles, true ) ) {
		return new WP_Error( 'staff', 'Diese E-Mail-Adresse gehört zu einem Mitarbeiter-Konto, nicht zu einem Partner.' );
	}
	if ( ! $user ) {
		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 32 ),
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => $first_name . ' ' . $last_name,
				'role'         => 'subscriber',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		$user   = get_user_by( 'id', $user_id );
		$status = 'created';
	}

	$lang = vitarights_onboarding_course_lang( $course_id );
	update_user_meta( $user->ID, 'locale', 'de' === $lang ? '' : VITARIGHTS_ONBOARDING_LANGS[ $lang ] );

	if ( ! \Tutor\Models\EnrollmentModel::do_enroll( $course_id, 0, $user->ID ) ) {
		return new WP_Error( 'enroll', 'Der Partner wurde angelegt, konnte aber nicht in den Kurs eingeschrieben werden.' );
	}
	if ( ! vitarights_onboarding_send_invite( $user ) ) {
		return new WP_Error( 'mail', 'Der Partner wurde angelegt und eingeschrieben, die Einladungsmail konnte aber nicht verschickt werden.' );
	}

	return array(
		'status' => $status,
		'user'   => $user,
	);
}

// Status eines Partners, in der Reihenfolge des Ablaufs (so auch sortiert)
const VITARIGHTS_ONBOARDING_STATUSES = array(
	'open'     => 'Noch nicht eingeloggt',
	'login'    => 'Eingeloggt, noch nicht begonnen',
	'progress' => 'In Bearbeitung',
	'done'     => 'Abgeschlossen',
	'none'     => 'Nicht eingeschrieben',
);

/**
 * Alle Partner (Abonnenten) mit Kurs, Status und Einladungsdaten – in wenigen gesammelten Abfragen,
 * damit Übersicht, Admin-Liste und CSV-Export auch bei ~1.000 Partnern filtern und sortieren können.
 * Den Fortschritt in % rechnet Tutor je Partner einzeln, deshalb nur für angezeigte Zeilen
 * (vitarights_onboarding_status_label()).
 *
 * @return array[] Je Partner: id, name, email, registered, course (0 = keiner), lang, invited_at,
 *                 invited_by, last_login, completed_at, status (Schlüssel aus VITARIGHTS_ONBOARDING_STATUSES)
 */
function vitarights_onboarding_partners() {
	global $wpdb;
	$users = get_users(
		array(
			'role'   => 'subscriber',
			'fields' => array( 'ID', 'display_name', 'user_email', 'user_registered' ),
		)
	);
	if ( ! $users ) {
		return array();
	}
	$ids = implode( ',', array_map( 'intval', wp_list_pluck( $users, 'ID' ) ) );

	$meta = array();
	foreach ( $wpdb->get_results( "SELECT user_id, meta_key, meta_value FROM $wpdb->usermeta WHERE user_id IN ($ids) AND meta_key IN ('vr_invited_at', 'vr_invited_by', 'vr_last_login', 'locale')" ) as $m ) {
		$meta[ $m->user_id ][ $m->meta_key ] = $m->meta_value;
	}

	// Tutor: Einschreibung = Post tutor_enrolled (Autor Partner, Eltern Kurs), Abschluss = Kommentar
	// course_completed, begonnen = erledigte Lektion (User-Meta) oder Quiz-Versuch im Kurs
	$enrolled = array();
	foreach ( $wpdb->get_results( "SELECT post_author, post_parent, post_date_gmt FROM $wpdb->posts WHERE post_type = 'tutor_enrolled' AND post_status = 'completed' AND post_author IN ($ids)" ) as $e ) {
		$enrolled[ $e->post_author ][ (int) $e->post_parent ] = strtotime( $e->post_date_gmt );
	}
	$completed = array();
	foreach ( $wpdb->get_results( "SELECT user_id, comment_post_ID, comment_date_gmt FROM $wpdb->comments WHERE comment_type = 'course_completed' AND comment_agent = 'TutorLMSPlugin' AND user_id IN ($ids)" ) as $c ) {
		$completed[ $c->user_id ][ (int) $c->comment_post_ID ] = strtotime( $c->comment_date_gmt );
	}
	$lesson_course = $wpdb->get_results( "SELECT l.ID, t.post_parent FROM $wpdb->posts l JOIN $wpdb->posts t ON t.ID = l.post_parent WHERE l.post_type = 'lesson' AND t.post_type = 'topics'", OBJECT_K );
	$started       = array();
	foreach ( $wpdb->get_results( "SELECT user_id, meta_key FROM $wpdb->usermeta WHERE user_id IN ($ids) AND meta_key LIKE '\\_tutor\\_completed\\_lesson\\_id\\_%'" ) as $m ) {
		$lesson = (int) substr( $m->meta_key, strlen( '_tutor_completed_lesson_id_' ) );
		if ( isset( $lesson_course[ $lesson ] ) ) {
			$started[ $m->user_id ][ (int) $lesson_course[ $lesson ]->post_parent ] = true;
		}
	}
	foreach ( $wpdb->get_results( "SELECT DISTINCT user_id, course_id FROM {$wpdb->prefix}tutor_quiz_attempts WHERE user_id IN ($ids)" ) as $a ) {
		$started[ $a->user_id ][ (int) $a->course_id ] = true;
	}

	$rows = array();
	foreach ( $users as $user ) {
		$id = (int) $user->ID;
		$m  = $meta[ $id ] ?? array();

		// Kurs wie vitarights_onboarding_course_id(): abgeschlossen vor begonnen vor zuletzt eingeschrieben
		$course = 0;
		$best   = null;
		foreach ( $enrolled[ $id ] ?? array() as $course_id => $time ) {
			$rank = array( isset( $completed[ $id ][ $course_id ] ), isset( $started[ $id ][ $course_id ] ), $time );
			if ( null === $best || $rank > $best ) {
				$best   = $rank;
				$course = $course_id;
			}
		}

		$completed_at = $course ? ( $completed[ $id ][ $course ] ?? 0 ) : 0;
		if ( ! $course ) {
			$status = 'none';
		} elseif ( $completed_at ) {
			$status = 'done';
		} elseif ( isset( $started[ $id ][ $course ] ) ) {
			$status = 'progress';
		} else {
			$status = empty( $m['vr_last_login'] ) ? 'open' : 'login';
		}

		$rows[] = array(
			'id'           => $id,
			'name'         => $user->display_name,
			'email'        => $user->user_email,
			'registered'   => strtotime( $user->user_registered . ' UTC' ),
			'course'       => $course,
			'lang'         => vitarights_onboarding_locale_lang( $m['locale'] ?? '' ),
			'invited_at'   => (int) ( $m['vr_invited_at'] ?? 0 ),
			'invited_by'   => (int) ( $m['vr_invited_by'] ?? 0 ),
			'last_login'   => (int) ( $m['vr_last_login'] ?? 0 ),
			'completed_at' => $completed_at,
			'status'       => $status,
		);
	}
	return $rows;
}

/**
 * Partner filtern und sortieren.
 *
 * @param array $args search (Name/E-Mail), status, course, lang, orderby (name|invited|last_login|status|registered), order (asc|desc)
 */
function vitarights_onboarding_filter_partners( array $rows, array $args ) {
	$search = trim( (string) ( $args['search'] ?? '' ) );
	$rows   = array_filter(
		$rows,
		function ( $row ) use ( $args, $search ) {
			return ( '' === $search || false !== mb_stripos( $row['name'] . ' ' . $row['email'], $search ) )
				&& ( empty( $args['status'] ) || $row['status'] === $args['status'] )
				&& ( empty( $args['course'] ) || $row['course'] === (int) $args['course'] )
				&& ( empty( $args['lang'] ) || $row['lang'] === $args['lang'] );
		}
	);

	$orderby = $args['orderby'] ?? 'registered';
	$order   = array_keys( VITARIGHTS_ONBOARDING_STATUSES );
	$key     = function ( $row ) use ( $orderby, $order ) {
		switch ( $orderby ) {
			case 'name':
				return mb_strtolower( $row['name'] );
			case 'invited':
				return $row['invited_at'] ? $row['invited_at'] : $row['registered'];
			case 'last_login':
				return $row['last_login'];
			case 'status':
				return array_search( $row['status'], $order, true );
			default:
				return $row['registered'];
		}
	};
	$desc = 'desc' === strtolower( $args['order'] ?? ( 'name' === $orderby || 'status' === $orderby ? 'asc' : 'desc' ) );
	usort(
		$rows,
		function ( $a, $b ) use ( $key, $desc ) {
			$cmp = $key( $a ) <=> $key( $b );
			return $desc ? -$cmp : $cmp;
		}
	);
	return $rows;
}

/**
 * Status als Text, mit Fortschritt bzw. Abschlussdatum (Fortschritt fragt Tutor einzeln ab).
 */
function vitarights_onboarding_status_label( array $row ) {
	$label = VITARIGHTS_ONBOARDING_STATUSES[ $row['status'] ];
	if ( 'done' === $row['status'] ) {
		return $label . ' am ' . wp_date( 'j. M Y', $row['completed_at'] );
	}
	if ( 'progress' === $row['status'] ) {
		return $label . ' – ' . vitarights_onboarding_partner_progress( $row ) . ' %';
	}
	return $label;
}

function vitarights_onboarding_partner_progress( array $row ) {
	if ( 'done' === $row['status'] ) {
		return 100;
	}
	return $row['course'] ? (int) tutor_utils()->get_course_completed_percent( $row['course'], $row['id'] ) : 0;
}

/**
 * „eingeladen 11. Okt. 2026 von …“ bzw. „registriert …“ (Partner von vor der Einladungsseite).
 */
function vitarights_onboarding_invited_label( array $row ) {
	if ( ! $row['invited_at'] ) {
		return 'registriert ' . wp_date( 'j. M Y', $row['registered'] );
	}
	$by = $row['invited_by'] ? get_userdata( $row['invited_by'] ) : null;
	return 'eingeladen ' . wp_date( 'j. M Y', $row['invited_at'] ) . ( $by ? ' von ' . $by->display_name : '' );
}

function vitarights_onboarding_status_badge( array $row ) {
	return '<span class="vr-status vr-status--' . esc_attr( $row['status'] ) . '">' . esc_html( vitarights_onboarding_status_label( $row ) ) . '</span>';
}

// Ergebnis der Formularverarbeitung für die Ausgabe (Fehler bleiben auf der Seite, Erfolg per Redirect)
$GLOBALS['vitarights_onboarding_invite_error'] = null;

add_action(
	'template_redirect',
	function () {
		if ( ! is_page( VITARIGHTS_ONBOARDING_INVITE_PAGE ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/partner-magic-login/' ) );
			exit;
		}
		if ( ! current_user_can( VITARIGHTS_ONBOARDING_INVITE_CAP ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		nocache_headers();

		$page = get_permalink();
		if ( isset( $_POST['vr_invite'] ) && check_admin_referer( 'vr_invite' ) ) {
			$result = vitarights_onboarding_invite_partner(
				sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
				sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
				sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
				absint( $_POST['course'] ?? 0 )
			);
			if ( is_wp_error( $result ) ) {
				$GLOBALS['vitarights_onboarding_invite_error'] = $result->get_error_message();
				return;
			}
			wp_safe_redirect( add_query_arg( array( 'vr_done' => $result['status'], 'vr_user' => $result['user']->ID ), $page ) );
			exit;
		}

		if ( isset( $_POST['vr_resend'] ) ) {
			$user_id = absint( $_POST['vr_resend'] );
			check_admin_referer( 'vr_resend_' . $user_id );
			$user = get_user_by( 'id', $user_id );
			if ( ! $user || ! in_array( 'subscriber', $user->roles, true ) ) {
				$GLOBALS['vitarights_onboarding_invite_error'] = 'Diesen Partner gibt es nicht.';
				return;
			}
			if ( ! vitarights_onboarding_send_invite( $user ) ) {
				$GLOBALS['vitarights_onboarding_invite_error'] = 'Die Mail an ' . $user->user_email . ' konnte nicht verschickt werden.';
				return;
			}
			// Suche und Seite der Übersicht beibehalten
			$back = wp_get_referer() ? wp_get_referer() : $page;
			wp_safe_redirect( add_query_arg( array( 'vr_done' => 'resent', 'vr_user' => $user_id ), remove_query_arg( array( 'vr_done', 'vr_user' ), $back ) ) );
			exit;
		}
	}
);

add_shortcode(
	'vitarights_partner_invite',
	function () {
		if ( ! current_user_can( VITARIGHTS_ONBOARDING_INVITE_CAP ) ) {
			return '';
		}
		$page  = get_permalink();
		$error = $GLOBALS['vitarights_onboarding_invite_error'];
		$html  = '<div class="vr-invite">';

		// Meldung
		$done = sanitize_key( $_GET['vr_done'] ?? '' );
		$who  = get_user_by( 'id', absint( $_GET['vr_user'] ?? 0 ) );
		if ( $error ) {
			$html .= '<p class="vr-notice vr-notice--error" role="alert">' . esc_html( $error ) . '</p>';
		} elseif ( $who && in_array( $done, array( 'created', 'resent' ), true ) ) {
			$text  = 'created' === $done
				? '%s wurde angelegt und hat die Einladung per E-Mail erhalten.'
				: '%s hat einen neuen Login-Link per E-Mail erhalten.';
			$html .= '<p class="vr-notice vr-notice--ok" role="status">' . sprintf( esc_html( $text ), '<strong>' . esc_html( $who->display_name . ' <' . $who->user_email . '>' ) . '</strong>' ) . '</p>';
		}

		// Formular (nach einem Fehler mit den eingegebenen Werten)
		$val      = function ( $key ) use ( $error ) {
			return $error ? sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) ) : '';
		};
		$selected = $error ? absint( $_POST['course'] ?? 0 ) : vitarights_onboarding_invite_courses()[0];
		$options  = '';
		foreach ( vitarights_onboarding_invite_courses() as $course_id ) {
			$options .= '<option value="' . $course_id . '"' . selected( $selected, $course_id, false ) . '>' . esc_html( vitarights_onboarding_course_label( $course_id ) ) . '</option>';
		}
		$html .= '<form class="vr-invite__form" method="post" action="' . esc_url( $page ) . '">'
			. wp_nonce_field( 'vr_invite', '_wpnonce', true, false )
			. '<p><label for="vr-first">Vorname</label><input id="vr-first" name="first_name" required autocomplete="off" value="' . esc_attr( $val( 'first_name' ) ) . '"></p>'
			. '<p><label for="vr-last">Nachname</label><input id="vr-last" name="last_name" required autocomplete="off" value="' . esc_attr( $val( 'last_name' ) ) . '"></p>'
			. '<p class="vr-invite__wide"><label for="vr-email">E-Mail-Adresse</label><input id="vr-email" type="email" name="email" required autocomplete="off" value="' . esc_attr( $val( 'email' ) ) . '"></p>'
			. '<p class="vr-invite__wide"><label for="vr-course">Kurs</label><select id="vr-course" name="course" aria-describedby="vr-course-hint">' . $options . '</select>'
			. '<small id="vr-course-hint" class="vr-invite__field-hint">Bestimmt auch die Sprache von Einladungsmail und Oberfläche.</small></p>'
			. '<p class="vr-invite__wide"><button class="vr-btn vr-btn--primary" type="submit" name="vr_invite" value="1">Einladung senden</button></p>'
			. '<p class="vr-invite__wide vr-invite__hint">Der Partner wird im gewählten Kurs eingeschrieben und erhält eine E-Mail mit seinem persönlichen Login-Link – in der Sprache des Kurses. Gibt es die E-Mail-Adresse schon, wird nur zusätzlich eingeschrieben und ein neuer Link verschickt.</p>'
			. '</form>';

		// Übersicht
		$search = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
		$paged  = max( 1, absint( $_GET['seite'] ?? 1 ) );
		$rows   = vitarights_onboarding_filter_partners( vitarights_onboarding_partners(), array( 'search' => $search ) );
		$total  = count( $rows );

		$html .= '<h2 class="vr-invite__title">Partner</h2>'
			. '<form class="vr-invite__search" method="get" action="' . esc_url( $page ) . '">'
			. '<input type="search" name="q" placeholder="Name oder E-Mail" aria-label="Partner suchen" value="' . esc_attr( $search ) . '">'
			. '<button class="vr-btn vr-btn--outline" type="submit">Suchen</button>'
			. ( '' !== $search ? '<a href="' . esc_url( $page ) . '">Zurücksetzen</a>' : '' )
			. '</form>';

		if ( ! $total ) {
			return $html . '<p>' . ( '' !== $search ? 'Kein Partner gefunden.' : 'Noch keine Partner angelegt.' ) . '</p></div>';
		}

		$html .= '<table class="vr-invite__table"><thead><tr><th>Partner</th><th>Kurs</th><th>Status</th></tr></thead><tbody>';
		foreach ( array_slice( $rows, ( $paged - 1 ) * 25, 25 ) as $row ) {
			$html .= '<tr>'
				. '<td data-label="Partner"><strong>' . esc_html( $row['name'] ) . '</strong><br><small>' . esc_html( $row['email'] ) . '</small></td>'
				. '<td data-label="Kurs">' . ( $row['course'] ? esc_html( vitarights_onboarding_course_label( $row['course'] ) ) : '–' ) . '<br><small>' . esc_html( vitarights_onboarding_invited_label( $row ) ) . '</small></td>'
				. '<td data-label="Status">' . vitarights_onboarding_status_badge( $row )
				. '<form method="post" action="' . esc_url( $page ) . '">' . wp_nonce_field( 'vr_resend_' . $row['id'], '_wpnonce', true, false )
				. '<button class="vr-link-btn" type="submit" name="vr_resend" value="' . $row['id'] . '">Link erneut senden</button></form></td>'
				. '</tr>';
		}
		$html .= '</tbody></table>';

		$pages = (int) ceil( $total / 25 );
		if ( $pages > 1 ) {
			$link  = function ( $n ) use ( $page, $search ) {
				return esc_url( add_query_arg( array_filter( array( 'q' => $search, 'seite' => $n > 1 ? $n : null ) ), $page ) );
			};
			$html .= '<nav class="vr-invite__pages">'
				. ( $paged > 1 ? '<a href="' . $link( $paged - 1 ) . '">← Zurück</a>' : '<span></span>' )
				. '<span>Seite ' . $paged . ' von ' . $pages . ' · ' . $total . ' Partner</span>'
				. ( $paged < $pages ? '<a href="' . $link( $paged + 1 ) . '">Weiter →</a>' : '<span></span>' )
				. '</nav>';
		}

		return $html . '</div>';
	}
);

add_action(
	'wp_head',
	function () {
		if ( ! is_page( VITARIGHTS_ONBOARDING_INVITE_PAGE ) ) {
			return;
		}
		echo '<style>'
			. '.vr-invite{--vr-blue:#253a7a;--vr-muted:#5b6275;--vr-line:#dde1ec}'
			. '.vr-notice{padding:.9rem 1.1rem;border-radius:8px;border-left:4px solid}'
			. '.vr-notice--ok{background:#eef7f0;border-color:#2e7d4a}.vr-notice--error{background:#fbeeee;border-color:#b3261e}'
			. '.vr-invite__form{display:grid;grid-template-columns:1fr 1fr;gap:0 1.25rem;margin:1.5rem 0 3rem;padding:1.5rem;border:1px solid var(--vr-line);border-radius:14px}'
			. '.vr-invite__form p{margin:0 0 1rem}.vr-invite__wide{grid-column:1/-1}'
			. '.vr-invite label{display:block;margin-bottom:.35rem;font-size:.9rem;font-weight:600}'
			. '.vr-invite input,.vr-invite select{width:100%;box-sizing:border-box;padding:.7rem .8rem;border:1px solid #b9c0d4;border-radius:8px;font:inherit;background:#fff}'
			. '.vr-invite__hint{margin:0!important;color:var(--vr-muted);font-size:.9rem}'
			. '.vr-invite__field-hint{display:block;margin-top:.35rem;color:var(--vr-muted);font-size:.85rem}'
			. '.vr-invite__title{margin-bottom:1rem}'
			. '.vr-invite__search{display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;margin-bottom:1.25rem}.vr-invite__search input{flex:1 1 16rem;width:auto}.vr-invite__search .vr-btn{padding:.7rem 1.4rem}'
			. '.vr-invite__table{width:100%;border-collapse:collapse;font-size:.95rem}'
			. '.vr-invite__table th{text-align:left;font-size:.8rem;letter-spacing:.06em;text-transform:uppercase;color:var(--vr-muted);border-bottom:2px solid var(--vr-line);padding:.6rem .5rem}'
			. '.vr-invite__table td{border-bottom:1px solid var(--vr-line);padding:.75rem .5rem;vertical-align:top}'
			. '.vr-invite__table small{color:var(--vr-muted);overflow-wrap:anywhere}'
			. '.vr-invite__table td form{margin-top:.4rem}.vr-invite__table .vr-link-btn{font-size:.85rem}'
			. VITARIGHTS_ONBOARDING_STATUS_CSS
			. '.vr-link-btn{background:none;border:0;padding:0;color:var(--vr-blue);font:inherit;text-decoration:underline;cursor:pointer;white-space:nowrap}'
			. '.vr-invite__pages{display:flex;justify-content:space-between;gap:1rem;margin-top:1.25rem;font-size:.9rem}'
			. '@media (max-width:700px){.vr-invite__form{grid-template-columns:1fr;padding:1rem}'
			. '.vr-invite__table thead{display:none}.vr-invite__table tr{display:block;padding:.75rem 0;border-bottom:1px solid var(--vr-line)}'
			. '.vr-invite__table td{display:block;border:0;padding:.2rem 0}.vr-invite__table td[data-label]:not([data-label="Partner"])::before{content:attr(data-label) ": ";color:var(--vr-muted);font-size:.85rem}}'
			. '</style>';
	}
);
