<?php
/**
 * Partner-Liste im Admin (partner-admin.php) – Daten aus vitarights_onboarding_partners().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Vitarights_Onboarding_Partner_List_Table extends WP_List_Table {

	/** @var array Alle Partner vor dem Statusfilter (für die Zähler der Status-Links) */
	private $before_status = array();

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'partner',
				'plural'   => 'partner',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'name'       => 'Partner',
			'course'     => 'Kurs',
			'lang'       => 'Sprache',
			'invited'    => 'Eingeladen',
			'last_login' => 'Letzter Login',
			'status'     => 'Status',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'name'       => array( 'name', false ),
			'invited'    => array( 'invited', true ),
			'last_login' => array( 'last_login', true ),
			'status'     => array( 'status', false ),
		);
	}

	protected function get_table_classes() {
		return array_merge( parent::get_table_classes(), array( 'vr-partner-table' ) );
	}

	public function prepare_items() {
		$args     = vitarights_onboarding_admin_query_args();
		$per_page = 25;

		// Status-Links zählen innerhalb von Suche, Kurs und Sprache
		$this->before_status = vitarights_onboarding_filter_partners( vitarights_onboarding_partners(), array_merge( $args, array( 'status' => '' ) ) );
		$rows                = $args['status']
			? array_values( array_filter( $this->before_status, fn( $row ) => $row['status'] === $args['status'] ) )
			: $this->before_status;

		$this->set_pagination_args(
			array(
				'total_items' => count( $rows ),
				'per_page'    => $per_page,
			)
		);
		$this->items           = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per_page, $per_page );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
	}

	protected function get_views() {
		$args    = vitarights_onboarding_admin_query_args();
		$counts  = array_count_values( wp_list_pluck( $this->before_status, 'status' ) );
		$base    = remove_query_arg( array( 'status', 'paged', 'vr_done', 'vr_user' ) );
		$link    = function ( $status, $label, $count ) use ( $args, $base ) {
			$current = $status === $args['status'];
			return sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $status ? add_query_arg( 'status', $status, $base ) : $base ),
				$current ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				$count
			);
		};
		$views = array( 'all' => $link( '', 'Alle', count( $this->before_status ) ) );
		foreach ( VITARIGHTS_ONBOARDING_STATUSES as $status => $label ) {
			if ( ! empty( $counts[ $status ] ) || $status === $args['status'] ) {
				$views[ $status ] = $link( $status, $label, $counts[ $status ] ?? 0 );
			}
		}
		return $views;
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$args = vitarights_onboarding_admin_query_args();
		echo '<div class="alignleft actions">';
		echo '<label for="vr-filter-course" class="screen-reader-text">Nach Kurs filtern</label>';
		echo '<select name="course" id="vr-filter-course"><option value="">Alle Kurse</option>';
		foreach ( vitarights_onboarding_invite_courses() as $course_id ) {
			printf( '<option value="%d"%s>%s</option>', (int) $course_id, selected( $args['course'], $course_id, false ), esc_html( vitarights_onboarding_course_label( $course_id ) ) );
		}
		echo '</select>';
		echo '<label for="vr-filter-lang" class="screen-reader-text">Nach Sprache filtern</label>';
		echo '<select name="lang" id="vr-filter-lang"><option value="">Alle Sprachen</option>';
		foreach ( VITARIGHTS_ONBOARDING_LANG_LABELS as $lang => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $lang ), selected( $args['lang'], $lang, false ), esc_html( $label ) );
		}
		echo '</select>';
		submit_button( 'Filtern', '', 'filter_action', false );
		echo '</div>';
	}

	public function no_items() {
		echo 'Keine Partner gefunden.';
	}

	protected function column_name( $row ) {
		return '<strong>' . esc_html( $row['name'] ) . '</strong><br><small>' . esc_html( $row['email'] ) . '</small>';
	}

	protected function column_course( $row ) {
		return $row['course'] ? esc_html( vitarights_onboarding_course_label( $row['course'] ) ) : '–';
	}

	protected function column_lang( $row ) {
		return esc_html( VITARIGHTS_ONBOARDING_LANG_LABELS[ $row['lang'] ] );
	}

	protected function column_invited( $row ) {
		if ( ! $row['invited_at'] ) {
			return '<small>registriert ' . esc_html( wp_date( 'j. M Y', $row['registered'] ) ) . '</small>';
		}
		$by = $row['invited_by'] ? get_userdata( $row['invited_by'] ) : null;
		return esc_html( wp_date( 'j. M Y', $row['invited_at'] ) ) . ( $by ? '<br><small>von ' . esc_html( $by->display_name ) . '</small>' : '' );
	}

	protected function column_last_login( $row ) {
		return $row['last_login'] ? esc_html( wp_date( 'j. M Y, H:i', $row['last_login'] ) ) : '–';
	}

	protected function column_status( $row ) {
		return vitarights_onboarding_status_badge( $row );
	}

	protected function handle_row_actions( $row, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}
		$actions = array(
			'resend' => sprintf(
				'<a href="%s">Link erneut senden</a>',
				esc_url( wp_nonce_url( vitarights_onboarding_admin_url( array( 'action' => 'resend', 'user' => $row['id'] ) ), 'vr_resend_' . $row['id'] ) )
			),
		);
		if ( current_user_can( 'edit_user', $row['id'] ) ) {
			$actions['edit'] = '<a href="' . esc_url( get_edit_user_link( $row['id'] ) ) . '">Profil</a>';
		}
		return $this->row_actions( $actions );
	}
}
