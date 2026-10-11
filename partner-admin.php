<?php
/**
 * Admin-Bereich „Partner“: Liste aller Partner mit Suche, Filtern (Status, Kurs, Sprache),
 * Sortierung, CSV-Export und „Link erneut senden“, dazu „Partner hinzufügen“. Gleiche Daten und
 * gleiche Einladung wie /partner-einladen/ (partner-invite.php), Zugriff mit vr_invite_partners
 * (Admins und Rolle „Onboarding-Support“).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VITARIGHTS_ONBOARDING_ADMIN_PAGE = 'vr-partner';
const VITARIGHTS_ONBOARDING_LANG_LABELS = array(
	'de' => 'Deutsch',
	'hu' => 'Ungarisch',
);

add_action(
	'admin_menu',
	function () {
		$list = add_menu_page( 'Partner', 'Partner', VITARIGHTS_ONBOARDING_INVITE_CAP, VITARIGHTS_ONBOARDING_ADMIN_PAGE, 'vitarights_onboarding_admin_list', 'dashicons-groups', '2.1' );
		add_submenu_page( VITARIGHTS_ONBOARDING_ADMIN_PAGE, 'Partner', 'Alle Partner', VITARIGHTS_ONBOARDING_INVITE_CAP, VITARIGHTS_ONBOARDING_ADMIN_PAGE, 'vitarights_onboarding_admin_list' );
		$add = add_submenu_page( VITARIGHTS_ONBOARDING_ADMIN_PAGE, 'Partner hinzufügen', 'Partner hinzufügen', VITARIGHTS_ONBOARDING_INVITE_CAP, VITARIGHTS_ONBOARDING_ADMIN_PAGE . '-add', 'vitarights_onboarding_admin_add' );

		// Aktionen vor jeder Ausgabe (Redirect bzw. CSV-Download)
		add_action( "load-$list", 'vitarights_onboarding_admin_list_actions' );
		add_action( "load-$add", 'vitarights_onboarding_admin_add_submit' );
	}
);

function vitarights_onboarding_admin_url( array $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => VITARIGHTS_ONBOARDING_ADMIN_PAGE ), $args ), admin_url( 'admin.php' ) );
}

/**
 * Filter und Sortierung aus der Anfrage (Liste und CSV-Export nutzen dieselben).
 */
function vitarights_onboarding_admin_query_args() {
	$status = sanitize_key( $_REQUEST['status'] ?? '' );
	$lang   = sanitize_key( $_REQUEST['lang'] ?? '' );
	return array(
		'search'  => sanitize_text_field( wp_unslash( $_REQUEST['s'] ?? '' ) ),
		'status'  => isset( VITARIGHTS_ONBOARDING_STATUSES[ $status ] ) ? $status : '',
		'course'  => absint( $_REQUEST['course'] ?? 0 ),
		'lang'    => isset( VITARIGHTS_ONBOARDING_LANG_LABELS[ $lang ] ) ? $lang : '',
		'orderby' => sanitize_key( $_REQUEST['orderby'] ?? 'registered' ),
		'order'   => 'asc' === strtolower( sanitize_key( $_REQUEST['order'] ?? '' ) ) ? 'asc' : ( empty( $_REQUEST['order'] ) ? null : 'desc' ),
	);
}

function vitarights_onboarding_admin_list_actions() {
	$action = sanitize_key( $_GET['action'] ?? '' );

	if ( 'resend' === $action ) {
		$user_id = absint( $_GET['user'] ?? 0 );
		check_admin_referer( 'vr_resend_' . $user_id );
		$user = get_user_by( 'id', $user_id );
		$ok   = $user && in_array( 'subscriber', $user->roles, true ) && vitarights_onboarding_send_invite( $user );
		$back = remove_query_arg( array( 'action', 'user', '_wpnonce', 'vr_done', 'vr_user' ), wp_get_referer() ? wp_get_referer() : vitarights_onboarding_admin_url() );
		wp_safe_redirect( add_query_arg( array( 'vr_done' => $ok ? 'resent' : 'mail_failed', 'vr_user' => $user_id ), $back ) );
		exit;
	}

	if ( 'export' === $action ) {
		check_admin_referer( 'vr_partner_export' );
		vitarights_onboarding_admin_export( vitarights_onboarding_filter_partners( vitarights_onboarding_partners(), vitarights_onboarding_admin_query_args() ) );
		exit;
	}
}

/**
 * CSV für Excel: UTF-8 mit BOM, Semikolon als Trenner.
 */
function vitarights_onboarding_admin_export( array $rows ) {
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="partner-' . wp_date( 'Y-m-d' ) . '.csv"' );

	$out  = fopen( 'php://output', 'w' );
	$date = function ( $time ) {
		return $time ? wp_date( 'd.m.Y H:i', $time ) : '';
	};
	fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array( 'Name', 'E-Mail', 'Kurs', 'Sprache', 'Status', 'Fortschritt (%)', 'Abgeschlossen am', 'Eingeladen am', 'Eingeladen von', 'Letzter Login', 'Registriert am' ), ';' );
	foreach ( $rows as $row ) {
		$by = $row['invited_by'] ? get_userdata( $row['invited_by'] ) : null;
		fputcsv(
			$out,
			array(
				$row['name'],
				$row['email'],
				$row['course'] ? vitarights_onboarding_course_label( $row['course'] ) : '',
				VITARIGHTS_ONBOARDING_LANG_LABELS[ $row['lang'] ],
				VITARIGHTS_ONBOARDING_STATUSES[ $row['status'] ],
				vitarights_onboarding_partner_progress( $row ),
				$date( $row['completed_at'] ),
				$date( $row['invited_at'] ),
				$by ? $by->display_name : '',
				$date( $row['last_login'] ),
				$date( $row['registered'] ),
			),
			';'
		);
	}
	fclose( $out );
}

function vitarights_onboarding_admin_list() {
	require_once __DIR__ . '/class-partner-list-table.php';
	$table = new Vitarights_Onboarding_Partner_List_Table();
	$table->prepare_items();
	$args = vitarights_onboarding_admin_query_args();

	echo '<div class="wrap"><h1 class="wp-heading-inline">Partner</h1> ';
	echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . VITARIGHTS_ONBOARDING_ADMIN_PAGE . '-add' ) ) . '" class="page-title-action">Partner hinzufügen</a> ';
	$export = wp_nonce_url( vitarights_onboarding_admin_url( array_filter( array_merge( $args, array( 'action' => 'export' ) ) ) ), 'vr_partner_export' );
	echo '<a href="' . esc_url( $export ) . '" class="page-title-action">CSV exportieren</a>';
	if ( '' !== $args['search'] ) {
		echo '<span class="subtitle">Suchergebnisse für: <strong>' . esc_html( $args['search'] ) . '</strong></span>';
	}
	echo '<hr class="wp-header-end">';

	vitarights_onboarding_admin_notice();
	$table->views();

	echo '<form method="get">';
	echo '<input type="hidden" name="page" value="' . esc_attr( VITARIGHTS_ONBOARDING_ADMIN_PAGE ) . '">';
	if ( $args['status'] ) {
		echo '<input type="hidden" name="status" value="' . esc_attr( $args['status'] ) . '">';
	}
	$table->search_box( 'Partner suchen', 'vr-partner' );
	$table->display();
	echo '</form></div>';
}

function vitarights_onboarding_admin_notice() {
	$done = sanitize_key( $_GET['vr_done'] ?? '' );
	$who  = get_user_by( 'id', absint( $_GET['vr_user'] ?? 0 ) );
	if ( ! $who || ! $done ) {
		return;
	}
	$texts = array(
		'created'     => array( 'success', '%s wurde angelegt und hat die Einladung per E-Mail erhalten.' ),
		'resent'      => array( 'success', '%s hat einen neuen Login-Link per E-Mail erhalten.' ),
		'mail_failed' => array( 'error', 'Die Mail an %s konnte nicht verschickt werden.' ),
	);
	if ( isset( $texts[ $done ] ) ) {
		list( $type, $text ) = $texts[ $done ];
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			sprintf( esc_html( $text ), '<strong>' . esc_html( $who->display_name . ' <' . $who->user_email . '>' ) . '</strong>' )
		);
	}
}

// Ergebnis von „Partner hinzufügen“ (Fehler bleiben auf der Seite, Erfolg per Redirect zur Liste)
$GLOBALS['vitarights_onboarding_admin_error'] = null;

function vitarights_onboarding_admin_add_submit() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}
	check_admin_referer( 'vr_invite' );
	$result = vitarights_onboarding_invite_partner(
		sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
		sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
		sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
		absint( $_POST['course'] ?? 0 )
	);
	if ( is_wp_error( $result ) ) {
		$GLOBALS['vitarights_onboarding_admin_error'] = $result->get_error_message();
		return;
	}
	wp_safe_redirect( vitarights_onboarding_admin_url( array( 'vr_done' => $result['status'], 'vr_user' => $result['user']->ID ) ) );
	exit;
}

function vitarights_onboarding_admin_add() {
	$error = $GLOBALS['vitarights_onboarding_admin_error'];
	$val   = function ( $key ) use ( $error ) {
		return $error ? sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) ) : '';
	};
	$selected = $error ? absint( $_POST['course'] ?? 0 ) : VITARIGHTS_ONBOARDING_INVITE_COURSES[0];
	?>
	<div class="wrap">
		<h1>Partner hinzufügen</h1>
		<?php if ( $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>
		<p>Der Partner wird im gewählten Kurs eingeschrieben und erhält eine E-Mail mit seinem persönlichen Login-Link, beim ungarischen Kurs auf Ungarisch. Gibt es die E-Mail-Adresse schon, wird er zusätzlich eingeschrieben und bekommt einen neuen Link.</p>
		<form method="post">
			<?php wp_nonce_field( 'vr_invite' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="vr-first">Vorname</label></th>
					<td><input id="vr-first" name="first_name" class="regular-text" required autocomplete="off" value="<?php echo esc_attr( $val( 'first_name' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="vr-last">Nachname</label></th>
					<td><input id="vr-last" name="last_name" class="regular-text" required autocomplete="off" value="<?php echo esc_attr( $val( 'last_name' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="vr-email">E-Mail-Adresse</label></th>
					<td><input id="vr-email" type="email" name="email" class="regular-text" required autocomplete="off" value="<?php echo esc_attr( $val( 'email' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="vr-course">Kurs</label></th>
					<td>
						<select id="vr-course" name="course">
							<?php foreach ( VITARIGHTS_ONBOARDING_INVITE_COURSES as $course_id ) : ?>
								<option value="<?php echo (int) $course_id; ?>" <?php selected( $selected, $course_id ); ?>><?php echo esc_html( vitarights_onboarding_course_label( $course_id ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">Bestimmt auch die Sprache von Einladungsmail und Oberfläche.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Einladung senden' ); ?>
		</form>
	</div>
	<?php
}

add_action(
	'admin_head',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, VITARIGHTS_ONBOARDING_ADMIN_PAGE ) ) {
			return;
		}
		echo '<style>' . VITARIGHTS_ONBOARDING_STATUS_CSS // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '.vr-partner-table .column-status{width:15rem}.vr-partner-table .column-lang{width:7rem}'
			. '.vr-partner-table .column-invited,.vr-partner-table .column-last_login{width:11rem}'
			. '.vr-partner-table td small{color:#646970}'
			. '</style>';
	}
);
