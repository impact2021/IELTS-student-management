<?php
/**
 * Plugin Name: Impact Websites Student Management
 * Description: Partner-admin invite system for LearnDash. Shared partner dashboard (global pool) so multiple partner admins see the same codes and users. Single-use invite codes, auto-enrol in ALL LearnDash courses, site-wide login enforcement with public registration.
 * Version: 0.7.1
 * Author: Impact Websites
 * License: GPLv2 or later
 *
 * Change in 0.7.1:
 * - Partner dashboard is now GLOBAL: all partner admins see the same invites and managed users (shared company pool).
 * - Revoke action now allows any partner admin (with capability) to revoke any managed student (as long as the student is managed by a partner).
 *
 * Install/Upgrade:
 * - Replace the existing plugin file at:
 *     wp-content/plugins/impact-websites-student-management/impact-websites-student-management.php
 *   then (re)activate the plugin.
 *
 * Notes:
 * - The registration page must be configured in Partnership area -> Settings -> Registration page URL and must be publicly accessible.
 * - The Login page URL must be configured too.
 */

/* Exit if accessed directly */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'IW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IW_PLUGIN_VERSION', '0.7.1' );

// Load required classes
require_once IW_PLUGIN_DIR . 'includes/class-iw-api-client.php';
require_once IW_PLUGIN_DIR . 'includes/class-iw-ajax.php';

class Impact_Websites_Student_Management {
	const CPT_INVITE = 'iw_invite';
	const META_MANAGER = '_iw_invite_manager';
	const META_INVITE_CODE = '_iw_invite_code';
	const META_INVITE_USED = '_iw_invite_used';
	const META_INVITE_USED_BY = '_iw_invite_used_by';
	const META_INVITE_USED_AT = '_iw_invite_used_at';
	const META_USER_MANAGER = '_iw_user_manager';
	const META_USER_EXPIRY = '_iw_user_expiry';
	const META_EXPIRY_NOTICE_SENT = '_iw_expiry_notice_sent';
	const OPTION_KEY = 'iw_student_management_options';
	const CRON_HOOK = 'iw_sm_daily_cron';
	const AJAX_CREATE = 'iw_create_invite';
	const AJAX_REVOKE = 'iw_revoke_student';
	const NONCE_DASH = 'iw_dashboard_nonce';
	const NONCE_REGISTER = 'iw_register_nonce';
	const PARTNER_ROLE = 'partner_admin';
	const CAP_MANAGE = 'manage_partner_invites';

	public function __construct() {
		register_activation_hook( __FILE__, [ $this, 'on_activate' ] );
		register_deactivation_hook( __FILE__, [ $this, 'on_deactivate' ] );

		add_action( 'init', [ $this, 'register_invite_cpt' ] );
		add_action( 'init', [ $this, 'maybe_create_roles' ] );

		// admin settings menu (top-level "Partnership area")
		add_action( 'admin_menu', [ $this, 'add_settings_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );

		// AJAX handlers (frontend)
		add_action( 'wp_ajax_' . self::AJAX_CREATE, [ $this, 'ajax_create_invite' ] );
		add_action( 'wp_ajax_' . self::AJAX_REVOKE, [ $this, 'ajax_revoke_student' ] );

		// Shortcodes
		add_shortcode( 'iw_partner_dashboard', [ $this, 'shortcode_partner_dashboard' ] );
		add_shortcode( 'iw_register_with_code', [ $this, 'shortcode_registration_form' ] );
		add_shortcode( 'iw_my_expiry', [ $this, 'shortcode_my_expiry' ] );
		add_shortcode( 'iw_login', [ $this, 'shortcode_login' ] );

		// registration handler
		add_action( 'init', [ $this, 'maybe_handle_registration_post' ] );

		// enforce login page (with registration-page exemption)
		add_action( 'template_redirect', [ $this, 'enforce_login_required' ], 1 );

		// redirect partner_admin after login
		add_filter( 'login_redirect', [ $this, 'partner_admin_login_redirect' ], 10, 3 );

		// Daily cron
		add_action( self::CRON_HOOK, [ $this, 'daily_expire_check' ] );
		
		// Register extend-membership shortcode
		// Note: Other shortcodes (iw_partner_dashboard, iw_register_with_code, etc.)
		// are already registered above
		add_shortcode( 'extend-membership', [ $this, 'shortcode_extend_membership' ] );
		
		// Initialize and register AJAX handlers
		$this->init_ajax();
		
		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}
	
	/**
	 * Extend Membership Shortcode
	 */
	public function shortcode_extend_membership( $atts = [] ) {
		if ( ! is_user_logged_in() ) {
			return '<p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to extend your membership.</p>';
		}
		
		$template_file = IW_PLUGIN_DIR . 'templates/extend-membership.php';
		if ( ! file_exists( $template_file ) ) {
			return '<p>Template file not found.</p>';
		}
		
		ob_start();
		include $template_file;
		return ob_get_clean();
	}
	
	/**
	 * Initialize AJAX handlers
	 */
	private function init_ajax() {
		$ajax = new IW_AJAX();
		$ajax->register();
	}
	
	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts() {
		// Enqueue custom JavaScript (jQuery is automatically loaded as a dependency)
		wp_enqueue_script( 
			'iw-membership-script', 
			IW_PLUGIN_URL . 'assets/js/membership-script.js', 
			array( 'jquery' ), 
			IW_PLUGIN_VERSION, 
			true 
		);
		
		// Enqueue custom styles
		wp_enqueue_style( 
			'iw-membership-styles', 
			IW_PLUGIN_URL . 'assets/css/membership-styles.css', 
			array(), 
			IW_PLUGIN_VERSION 
		);
		
		// Localize script for AJAX (attached to our plugin script, not jQuery)
		wp_localize_script( 'iw-membership-script', 'iwMembership', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'iw_membership_nonce' ),
			'plansUrl' => home_url( '/membership-plans/' )
		) );
	}

	/* Activation: schedule cron and ensure CPT/roles exist */
	public function on_activate() {
		$this->register_invite_cpt();
		$this->maybe_create_roles();
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
		flush_rewrite_rules();
	}

	public function on_deactivate() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
		flush_rewrite_rules();
	}

	/* Register CPT used to store single-use codes and their used metadata */
	public function register_invite_cpt() {
		$labels = [
			'name'           => 'IW Invites',
			'singular_name'  => 'IW Invite',
		];
		register_post_type(
			self::CPT_INVITE,
			[
				'labels'       => $labels,
				'public'       => false,
				'show_ui'      => false,
				'show_in_menu' => false,
				'supports'     => [ 'title' ],
			]
		);
	}

	/* Ensure Partner Admin role and compatibility (grant cap to legacy impact_manager) */
	public function maybe_create_roles() {
		if ( ! get_role( self::PARTNER_ROLE ) ) {
			add_role( self::PARTNER_ROLE, 'Partner Admin', [ 'read' => true ] );
		}
		$roles = [ 'administrator', self::PARTNER_ROLE, 'impact_manager' ];
		foreach ( $roles as $r ) {
			$role = get_role( $r );
			if ( $role ) {
				$role->add_cap( self::CAP_MANAGE );
			}
		}
	}

	/* Add top-level admin menu called "Partnership area" for primary admin */
	public function add_settings_menu() {
		add_menu_page(
			'Partnership area',
			'Partnership area',
			'manage_options',
			'iw-partnership-area',
			[ $this, 'settings_page_html' ],
			'dashicons-networking',
			58
		);
		add_submenu_page( 'iw-partnership-area', 'Settings', 'Settings', 'manage_options', 'iw-partnership-area' );
	}

	public function register_settings() {
		register_setting( 'iw_student_management', self::OPTION_KEY, [ 'sanitize_callback' => [ $this, 'sanitize_options' ] ] );
		add_settings_section( 'iw_sm_main', 'Main settings', null, 'iw-student-management' );
		add_settings_field( 'default_days', 'Default invite length (days)', [ $this, 'field_default_days' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'default_partner_limit', 'Max students per partner (0 = unlimited)', [ $this, 'field_partner_limit' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'expiry_action', 'Action on user expiry', [ $this, 'field_expiry_action' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'notify_days_before', 'Notify partners this many days before expiry', [ $this, 'field_notify_days_before' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'post_register_redirect', 'Post-registration redirect URL (site)', [ $this, 'field_post_register_redirect' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'post_login_subscriber_redirect', 'Post-login redirect URL for subscribers', [ $this, 'field_post_login_subscriber_redirect' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'post_login_partner_redirect', 'Post-login redirect URL for partner admins', [ $this, 'field_post_login_partner_redirect' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'post_login_norole_redirect', 'Post-login redirect URL for users with no role', [ $this, 'field_post_login_norole_redirect' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'login_page_url', 'Login page URL (required for site-wide access control)', [ $this, 'field_login_page_url' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'registration_page_url', 'Registration page URL (public)', [ $this, 'field_registration_page_url' ], 'iw-student-management', 'iw_sm_main' );
	}

	public function sanitize_options( $vals ) {
		$vals = (array) $vals;
		$vals['default_days'] = isset( $vals['default_days'] ) ? intval( $vals['default_days'] ) : 30;
		$vals['default_partner_limit'] = isset( $vals['default_partner_limit'] ) ? intval( $vals['default_partner_limit'] ) : 10;
		$vals['expiry_action'] = in_array( $vals['expiry_action'] ?? '', [ 'delete_user', 'remove_enrollment' ], true ) ? $vals['expiry_action'] : 'delete_user';
		$vals['notify_days_before'] = isset( $vals['notify_days_before'] ) ? intval( $vals['notify_days_before'] ) : 7;
		$vals['post_register_redirect'] = ! empty( $vals['post_register_redirect'] ) ? esc_url_raw( trim( $vals['post_register_redirect'] ) ) : '';
		$vals['post_login_subscriber_redirect'] = ! empty( $vals['post_login_subscriber_redirect'] ) ? esc_url_raw( trim( $vals['post_login_subscriber_redirect'] ) ) : '';
		$vals['post_login_partner_redirect'] = ! empty( $vals['post_login_partner_redirect'] ) ? esc_url_raw( trim( $vals['post_login_partner_redirect'] ) ) : '';
		$vals['post_login_norole_redirect'] = ! empty( $vals['post_login_norole_redirect'] ) ? esc_url_raw( trim( $vals['post_login_norole_redirect'] ) ) : '';
		$vals['login_page_url'] = ! empty( $vals['login_page_url'] ) ? esc_url_raw( trim( $vals['login_page_url'] ) ) : '';
		$vals['registration_page_url'] = ! empty( $vals['registration_page_url'] ) ? esc_url_raw( trim( $vals['registration_page_url'] ) ) : '';
		return $vals;
	}

	public function field_default_days() {
		$options = get_option( self::OPTION_KEY, [] );
		$val = $options['default_days'] ?? 30;
		echo '<input type="number" name="' . self::OPTION_KEY . '[default_days]" value="' . esc_attr( $val ) . '" min="1" max="365" />';
	}

	public function field_partner_limit() {
		$options = get_option( self::OPTION_KEY, [] );
		$val = $options['default_partner_limit'] ?? 10;
		echo '<input type="number" name="' . self::OPTION_KEY . '[default_partner_limit]" value="' . esc_attr( $val ) . '" min="0" />';
		echo '<p class="description">Set 0 for unlimited. This is the global max students for the shared company pool.</p>';
	}

	public function field_expiry_action() {
		$options = get_option( self::OPTION_KEY, [] );
		$val = $options['expiry_action'] ?? 'delete_user';
		echo '<select name="' . self::OPTION_KEY . '[expiry_action]">';
		echo '<option value="delete_user"' . selected( $val, 'delete_user', false ) . '>Delete user</option>';
		echo '<option value="remove_enrollment"' . selected( $val, 'remove_enrollment', false ) . '>Remove LearnDash enrollments (keep WP user)</option>';
		echo '</select>';
	}

	public function field_notify_days_before() {
		$options = get_option( self::OPTION_KEY, [] );
		$val = $options['notify_days_before'] ?? 7;
		echo '<input type="number" name="' . self::OPTION_KEY . '[notify_days_before]" value="' . esc_attr( $val ) . '" min="0" />';
		echo '<p class="description">Set 0 to disable advance notifications.</p>';
	}

	public function field_post_register_redirect() {
		$options = get_option( self::OPTION_KEY, [] );
		$val = $options['post_register_redirect'] ?? '';
		$placeholder = home_url( '/my-account' );
		echo '<input type="text" style="width:60%;" name="' . self::OPTION_KEY . '[post_register_redirect]" id="iw_post_register_redirect" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
		echo '<p class="description">Full URL to redirect newly-registered users to after automatic login (site-wide). Leave blank to send users to the homepage.</p>';
	}

	public function field_post_login_subscriber_redirect() {
		$options = get_option( self::OPTION_KEY, [] );
		$val = $options['post_login_subscriber_redirect'] ?? '';
		$placeholder = home_url( '/my-account' );
		echo '<input type="text" style="width:60%;" name="' . self::OPTION_KEY . '[post_login_subscriber_redirect]" id="iw_post_login_subscriber_redirect" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
		echo '<p class="description">Full URL to redirect subscribers to after login. Leave blank to use default WordPress behavior.</p>';
	}

	public function field_post_login_partner_redirect() {
		$options = get_option( self::OPTION_KEY, [] );
		$val = $options['post_login_partner_redirect'] ?? '';
		$placeholder = home_url( '/partner-dashboard/' );
		echo '<input type="text" style="width:60%;" name="' . self::OPTION_KEY . '[post_login_partner_redirect]" id="iw_post_login_partner_redirect" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
		echo '<p class="description">Full URL to redirect partner admins to after login. Leave blank to use /partner-dashboard/.</p>';
	}

	public function field_post_login_norole_redirect() {
		$options = get_option( self::OPTION_KEY, [] );
		$val = $options['post_login_norole_redirect'] ?? '';
		$placeholder = home_url( '/extend-my-membership/' );
		echo '<input type="text" style="width:60%;" name="' . self::OPTION_KEY . '[post_login_norole_redirect]" id="iw_post_login_norole_redirect" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
		echo '<p class="description">Full URL to redirect users with no role to after login (e.g., expired users who need to extend membership). Leave blank to use /extend-my-membership/.</p>';
	}

	public function field_login_page_url() {
		$options = get_option( self::OPTION_KEY, [] );
		$val = $options['login_page_url'] ?? '';
		$placeholder = home_url( '/login/' );
		echo '<input type="text" style="width:60%;" name="' . self::OPTION_KEY . '[login_page_url]" id="iw_login_page_url" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
		echo '<p class="description">Full URL of the page that contains the [iw_login] shortcode. Example: https://example.com/login</p>';
	}

	public function field_registration_page_url() {
		$options = get_option( self::OPTION_KEY, [] );
		$val = $options['registration_page_url'] ?? '';
		$placeholder = home_url( '/register/' );
		echo '<input type="text" style="width:60%;" name="' . self::OPTION_KEY . '[registration_page_url]" id="iw_registration_page_url" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
		echo '<p class="description">Full URL of the page that contains the [iw_register_with_code] shortcode. This page must be publicly accessible so students can register.</p>';
	}

	public function settings_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>Partnership area - IW Student Management Settings</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'iw_student_management' );
				do_settings_sections( 'iw-student-management' );
				submit_button();
				?>
			</form>
		</div>
		<script>
		jQuery(document).ready(function($) {
			// Auto-fill empty URL fields with placeholder values on focus
			$('#iw_post_register_redirect, #iw_post_login_subscriber_redirect, #iw_post_login_partner_redirect, #iw_post_login_norole_redirect, #iw_login_page_url, #iw_registration_page_url').on('focus', function() {
				if ($(this).val() === '') {
					$(this).val($(this).attr('placeholder'));
				}
			});
		});
		</script>
		<?php
	}

	/* AJAX: create up to 10 invite codes at once (no expiry set) */
	public function ajax_create_invite() {
		if ( ! is_user_logged_in() || ! current_user_can( self::CAP_MANAGE ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		if ( empty( $_POST['iw_dash_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['iw_dash_nonce'] ), self::NONCE_DASH ) ) {
			wp_send_json_error( 'Invalid request', 400 );
		}

		$partner_id = get_current_user_id();
		$quantity = isset( $_POST['quantity'] ) ? intval( $_POST['quantity'] ) : 1;
		$quantity = max( 1, min( 10, $quantity ) ); // limit 1..10

		$codes = [];
		for ( $i = 0; $i < $quantity; $i++ ) {
			$code = $this->generate_code();
			$post_id = wp_insert_post( [
				'post_type'   => self::CPT_INVITE,
				'post_title'  => 'Invite ' . $code,
				'post_status' => 'publish',
			] );
			if ( is_wp_error( $post_id ) ) {
				continue;
			}
			// store creator for audit but dashboard is global
			update_post_meta( $post_id, self::META_MANAGER, $partner_id );
			update_post_meta( $post_id, self::META_INVITE_CODE, $code );
			$codes[] = $code;
		}

		if ( empty( $codes ) ) {
			wp_send_json_error( 'Could not create invites' );
		}

		wp_send_json_success( [ 'codes' => $codes ] );
	}

	/* AJAX: revoke student (global — any partner admin can revoke any managed student) */
	public function ajax_revoke_student() {
		if ( ! is_user_logged_in() || ! current_user_can( self::CAP_MANAGE ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		if ( empty( $_POST['iw_dash_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['iw_dash_nonce'] ), self::NONCE_DASH ) ) {
			wp_send_json_error( 'Invalid request', 400 );
		}
		$student_id = isset( $_POST['student_id'] ) ? intval( $_POST['student_id'] ) : 0;
		if ( ! $student_id ) {
			wp_send_json_error( 'Missing student ID', 400 );
		}
		$mgr = intval( get_user_meta( $student_id, self::META_USER_MANAGER, true ) );
		if ( ! $mgr ) {
			wp_send_json_error( 'This user is not managed by a partner', 403 );
		}

		update_user_meta( $student_id, self::META_USER_EXPIRY, time() );
		$this->remove_user_enrollments( $student_id );
		wp_update_user( [ 'ID' => $student_id, 'role' => 'expired' ] );

		wp_send_json_success( 'revoked' );
	}

	private function generate_code( $length = 8 ) {
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$max = strlen( $chars ) - 1;
		$code = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$code .= $chars[ random_int( 0, $max ) ];
		}
		return $code;
	}

	/* Helper: format dd/mm/YYYY */
	private function format_date( $ts ) {
		if ( ! $ts ) {
			return '';
		}
		return date_i18n( 'd/m/Y', intval( $ts ) );
	}

	/* Partner dashboard (shortcode)
	   GLOBAL: lists all invites and all managed users across the company. */
	public function shortcode_partner_dashboard() {
		if ( ! is_user_logged_in() ) {
			return '<p>Please log in to access the partner dashboard.</p>';
		}
		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			return '<p>You do not have permission to access this page.</p>';
		}

		// invites for all partners (global pool)
		$invites = get_posts( [
			'post_type'   => self::CPT_INVITE,
			'numberposts' => -1,
			'orderby'     => 'post_date',
			'order'       => 'DESC',
		] );

		// All students with subscriber role
		$now = time();
		$users = get_users( [
			'role' => 'subscriber',
			'fields' => 'all_with_meta',
		] );
		$all_students = $users; // All subscribers are included in the list

		// limit: use global partner limit (site setting). This is for the shared pool.
		$options = get_option( self::OPTION_KEY, [] );
		$global_limit = intval( $options['default_partner_limit'] ?? 0 );
		$active_count = count( $all_students );
		$slots_left = ( $global_limit === 0 ) ? 'Unlimited' : max( 0, $global_limit - $active_count );

		ob_start();
		$dash_nonce = wp_create_nonce( self::NONCE_DASH );
		?>
		<div id="iw-partner-dashboard">
			<h2>Create up to 10 invite codes</h2>
			<form id="iw-create-invite-form">
				<input type="hidden" name="iw_dash_nonce" value="<?php echo esc_attr( $dash_nonce ); ?>" />
				<label>How many codes do you want to create? <input type="number" name="quantity" value="1" min="1" max="10" /></label>
				<label>How many days' access should each code allow? <select name="days">
					<option value="30">30</option>
					<option value="60">60</option>
					<option value="90">90</option>
				</select></label>
				<button type="submit" class="button button-primary">Create codes</button>
			</form>

			<h2>Your codes</h2>
			<table class="widefat">
				<thead><tr><th>Code</th><th>Status</th><th>Used by</th><th>Activated on</th></tr></thead>
				<tbody>
				<?php
				if ( empty( $invites ) ) {
					echo '<tr><td colspan="4">No codes yet.</td></tr>';
				} else {
					foreach ( $invites as $inv ) {
						$code = get_post_meta( $inv->ID, self::META_INVITE_CODE, true );
						$used = get_post_meta( $inv->ID, self::META_INVITE_USED, true );
						$used_by = get_post_meta( $inv->ID, self::META_INVITE_USED_BY, true );
						$used_at = intval( get_post_meta( $inv->ID, self::META_INVITE_USED_AT, true ) );
						if ( $used ) {
							$u = get_userdata( intval( $used_by ) );
							$used_by_text = $u ? esc_html( $u->user_login ) . ' (' . esc_html( $u->user_email ) . ')' : 'User ID: ' . intval( $used_by );
							$used_at_text = $used_at ? esc_html( $this->format_date( $used_at ) ) : '';
							
							// Determine status based on user state
							if ( $u ) {
								$exp = intval( get_user_meta( $u->ID, self::META_USER_EXPIRY, true ) );
								if ( in_array( 'expired', $u->roles ) ) {
									$used_label = '<span style="color:red;font-weight:bold;">Revoked</span>';
								} elseif ( $exp && $exp <= $now ) {
									$used_label = '<span style="color:gray;font-weight:bold;">Expired</span>';
								} else {
									$used_label = '<span style="color:green;font-weight:bold;">Active</span>';
								}
							} else {
								$used_label = '<span style="color:gray;font-weight:bold;">Expired</span>';
							}
						} else {
							$used_label = '<span style="color:orange;">Available</span>';
							$used_by_text = '-';
							$used_at_text = '-';
						}
						echo '<tr>';
						echo '<td>' . esc_html( $code ) . '</td>';
						echo '<td>' . $used_label . '</td>';
						echo '<td>' . $used_by_text . '</td>';
						echo '<td>' . $used_at_text . '</td>';
						echo '</tr>';
					}
				}
				?>
				</tbody>
			</table>

			<h2>Active students (<?php echo intval( $active_count ); ?>)</h2>
			<p>Slots left: <strong><?php echo is_numeric( $slots_left ) ? intval( $slots_left ) : esc_html( $slots_left ); ?></strong></p>
			<table class="widefat">
				<thead><tr><th>Username</th><th>Email</th><th>Expires</th><th>Action</th></tr></thead>
				<tbody>
				<?php
				if ( empty( $all_students ) ) {
					echo '<tr><td colspan="4">No students found.</td></tr>';
				} else {
					foreach ( $all_students as $s ) {
						$exp = intval( get_user_meta( $s->ID, self::META_USER_EXPIRY, true ) );
						$exp_text = $exp ? $this->format_date( $exp ) : 'No expiry';
						echo '<tr id="iw-student-' . intval( $s->ID ) . '">';
						echo '<td>' . esc_html( $s->user_login ) . '</td>';
						echo '<td>' . esc_html( $s->user_email ) . '</td>';
						echo '<td>' . esc_html( $exp_text ) . '</td>';
						echo '<td><button class="button iw-revoke" data-student="' . intval( $s->ID ) . '">Revoke</button></td>';
						echo '</tr>';
					}
				}
				?>
				</tbody>
			</table>
		</div>

		<script>
		(function(){
			const form = document.getElementById('iw-create-invite-form');
			form.addEventListener('submit', function(e){
				e.preventDefault();
				const data = new FormData(form);
				data.append('action','<?php echo self::AJAX_CREATE; ?>');
				fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
					method:'POST',
					credentials:'same-origin',
					body:data
				}).then(r=>r.json()).then(d=>{
					if(d.success){
						alert('Codes created:\n' + d.data.codes.join('\n') + '\n\nCopy them to share with students.');
						location.reload();
					}else{
						alert('Error creating codes: '+(d.data||d));
					}
				});
			});

			document.querySelectorAll('.iw-revoke').forEach(function(btn){
				btn.addEventListener('click', function(){
					if(!confirm('Revoke access for this student? This will immediately remove their course access.')) return;
					const student = this.getAttribute('data-student');
					const data = new FormData();
					data.append('action','<?php echo self::AJAX_REVOKE; ?>');
					data.append('student_id', student);
					data.append('iw_dash_nonce', '<?php echo $dash_nonce; ?>');
					fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
						method:'POST',
						credentials:'same-origin',
						body:data
					}).then(r=>r.json()).then(d=>{
						if(d.success){
							alert('Student access revoked.');
							location.reload();
						}else{
							alert('Error revoking student: ' + (d.data||d));
						}
					});
				});
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/* Registration form (unchanged) */
	public function shortcode_registration_form() {
		if ( is_user_logged_in() ) {
			return '<p>You are already logged in.</p>';
		}
		$prefill = isset( $_GET['invite'] ) ? esc_attr( wp_unslash( $_GET['invite'] ) ) : '';
		ob_start();
		?>
		<style>
		.iw-table { width:100%; max-width:920px; border-collapse:collapse; margin-bottom:1em; font-family: Arial, sans-serif; }
		.iw-table thead th { background:#f8f9fa; color:#333333; padding:14px; text-align:left; font-size:16px; border:1px solid #e6e6e6; }
		.iw-table td, .iw-table th { padding:12px; border:1px solid #e6e6e6; vertical-align:middle; }
		.iw-table td:nth-child(even) { background:#f7f7f7; }
		.iw-table td:first-child, .iw-table th:first-child { background:#f8f9fa; color:#333333; font-weight:600; }
		.iw-input { width:100%; max-width:520px; padding:10px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box; height:40px; }
		.iw-submit { background:#0073aa; color:#fff; border:none; padding:10px 16px; border-radius:4px; cursor:pointer; }
		.iw-note { color:#666; margin:6px 0 0; font-size:13px; }
		</style>

		<form method="post" id="iw-register-with-code" style="max-width:960px;margin:0 auto;">
			<table class="iw-table" role="presentation">
				<thead>
					<tr><th colspan="2">Create your account</th></tr>
				</thead>
				<tbody>
				<tr>
					<th style="width:35%;">Invite Code (single-use)</th>
					<td><input name="invite_code" value="<?php echo $prefill; ?>" required class="iw-input" /></td>
				</tr>
				<tr>
					<th>First Name</th>
					<td><input name="first_name" required class="iw-input" /></td>
				</tr>
				<tr>
					<th>Last Name</th>
					<td><input name="last_name" required class="iw-input" /></td>
				</tr>
				<tr>
					<th>Username</th>
					<td><input name="user_login" required class="iw-input" /></td>
				</tr>
				<tr>
					<th>Email</th>
					<td><input name="user_email" type="email" required class="iw-input" /></td>
				</tr>
				<tr>
					<th>Password</th>
					<td>
						<input name="user_pass" type="password" required class="iw-input" />
						<p class="iw-note">This password will be used for your future logins. The invite code is single-use and will not be needed after registration.</p>
					</td>
				</tr>
				<tr>
					<td colspan="2" style="padding-top:10px;">
						<input type="hidden" name="iw_register_nonce" value="<?php echo wp_create_nonce( self::NONCE_REGISTER ); ?>"/>
						<button type="submit" class="iw-submit">Create account</button>
					</td>
				</tr>
				</tbody>
			</table>
		</form>
		<?php
		return ob_get_clean();
	}

	/* Handle registration POST: codes are single-use; on use we mark invite used and store user details */
	public function maybe_handle_registration_post() {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		if ( empty( $_POST['invite_code'] ) ) {
			return;
		}
		if ( empty( $_POST['iw_register_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['iw_register_nonce'] ), self::NONCE_REGISTER ) ) {
			wp_die( 'Invalid request' );
		}

		$user_login = sanitize_user( wp_unslash( $_POST['user_login'] ) );
		$user_email = sanitize_email( wp_unslash( $_POST['user_email'] ) );
		$user_pass = wp_unslash( $_POST['user_pass'] );
		$first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
		$last_name = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
		$code = sanitize_text_field( wp_unslash( $_POST['invite_code'] ) );
		
		// Validate required fields
		if ( empty( $first_name ) || empty( $last_name ) ) {
			wp_die( 'First name and last name are required.' );
		}

		// find invite post by code
		$invite_post = $this->find_invite_by_code( $code );
		if ( ! $invite_post ) {
			wp_die( 'Invalid or already-used invite code.' );
		}

		$inv_id = $invite_post->ID;
		$manager_id = intval( get_post_meta( $inv_id, self::META_MANAGER, true ) );

		// partner limit check (global pool)
		$options = get_option( self::OPTION_KEY, [] );
		$global_limit = intval( $options['default_partner_limit'] ?? 0 );
		if ( $global_limit > 0 ) {
			$active_count = $this->count_active_managed_users( null ); // count global
			if ( $active_count >= $global_limit ) {
				wp_die( 'The partner pool has reached its active account limit.' );
			}
		}

		// create user
		$user_id = wp_create_user( $user_login, $user_pass, $user_email );
		if ( is_wp_error( $user_id ) ) {
			wp_die( 'Could not create user: ' . $user_id->get_error_message() );
		}

		$user = new WP_User( $user_id );
		$user->set_role( 'subscriber' );
		
		// Save first name and last name (already validated as required)
		update_user_meta( $user_id, 'first_name', $first_name );
		update_user_meta( $user_id, 'last_name', $last_name );

		// set expiry on user (default behavior still available)
		$invite_expiry_ts = 0;
		if ( ! empty( $options['default_days'] ) ) {
			$invite_expiry_ts = time() + ( intval( $options['default_days'] ) * DAY_IN_SECONDS );
		}
		update_user_meta( $user_id, self::META_USER_MANAGER, $manager_id ?: 0 ); // store creator or 0
		update_user_meta( $user_id, self::META_USER_EXPIRY, $invite_expiry_ts );
		delete_user_meta( $user_id, self::META_EXPIRY_NOTICE_SENT );

		// mark invite used and store user info (DO NOT delete invite)
		update_post_meta( $inv_id, self::META_INVITE_USED, 1 );
		update_post_meta( $inv_id, self::META_INVITE_USED_BY, $user_id );
		update_post_meta( $inv_id, self::META_INVITE_USED_AT, time() );

		// enroll into ALL courses
		$this->enroll_user_in_all_courses( $user_id );

		// notify partner (creator) if present
		$this->notify_partner_invite_used( $manager_id, $user_id, $code, $invite_expiry_ts );

		// auto-login and redirect
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, false );
		do_action( 'wp_login', $user->user_login, $user );

		$redirect = ! empty( $options['post_register_redirect'] ) ? $options['post_register_redirect'] : home_url( '/' );
		$redirect_url = wp_validate_redirect( $redirect, home_url( '/' ) );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/* Find invite post by code, but do not return invites already used */
	private function find_invite_by_code( $code ) {
		$posts = get_posts( [
			'post_type'      => self::CPT_INVITE,
			'meta_key'       => self::META_INVITE_CODE,
			'meta_value'     => $code,
			'posts_per_page' => 1,
		] );
		if ( empty( $posts ) ) {
			return false;
		}
		$inv = $posts[0];
		$used = get_post_meta( $inv->ID, self::META_INVITE_USED, true );
		if ( $used ) {
			// already used -> treat as not found
			return false;
		}
		return $inv;
	}

	/* Count active users managed by partners.
	   If $manager_id is null, counts across all partners (global). */
	private function count_active_managed_users( $manager_id = null ) {
		$now = time();
		if ( $manager_id ) {
			$users = get_users( [
				'meta_key'   => self::META_USER_MANAGER,
				'meta_value' => $manager_id,
				'fields'     => 'ID',
			] );
		} else {
			$users = get_users( [
				'meta_query' => [
					[
						'key' => self::META_USER_MANAGER,
						'compare' => 'EXISTS',
					],
				],
				'fields' => 'ID',
			] );
		}
		$active = 0;
		foreach ( $users as $uid ) {
			$exp = intval( get_user_meta( $uid, self::META_USER_EXPIRY, true ) );
			if ( ! $exp || $exp > $now ) {
				$active++;
			}
		}
		return $active;
	}

	/* Enroll into ALL LearnDash courses */
	private function enroll_user_in_all_courses( $user_id ) {
		$posts = get_posts( [ 'post_type' => 'sfwd-courses', 'posts_per_page' => -1 ] );
		if ( empty( $posts ) ) {
			return;
		}
		foreach ( $posts as $p ) {
			$course_id = $p->ID;
			if ( function_exists( 'ld_update_course_access' ) ) {
				ld_update_course_access( $user_id, $course_id );
			} elseif ( function_exists( 'learndash_enroll_user' ) ) {
				learndash_enroll_user( $user_id, $course_id );
			} else {
				$key = 'course_' . $course_id . '_access';
				update_user_meta( $user_id, $key, time() );
			}
		}
	}

	/* Remove enrollments */
	private function remove_user_enrollments( $user_id ) {
		$posts = get_posts( [ 'post_type' => 'sfwd-courses', 'posts_per_page' => -1 ] );
		foreach ( $posts as $c ) {
			if ( function_exists( 'ld_update_course_access' ) ) {
				ld_update_course_access( $user_id, $c->ID, true );
			} else {
				$key = 'course_' . $c->ID . '_access';
				delete_user_meta( $user_id, $key );
			}
		}
	}

	/* Daily cron: notify and expire users (unchanged) */
	public function daily_expire_check() {
		$options = get_option( self::OPTION_KEY, [] );
		$action = $options['expiry_action'] ?? 'delete_user';
		$notify_days = intval( $options['notify_days_before'] ?? 7 );
		$now = time();

		// advance notifications
		if ( $notify_days > 0 ) {
			$threshold = $now + ( $notify_days * DAY_IN_SECONDS );
			$users = get_users( [
				'meta_query' => [
					[
						'key' => self::META_USER_EXPIRY,
						'value' => 0,
						'compare' => '>',
						'type' => 'NUMERIC',
					],
				],
				'fields' => 'ID',
			] );

			foreach ( $users as $uid ) {
				$exp = intval( get_user_meta( $uid, self::META_USER_EXPIRY, true ) );
				$notice_sent = intval( get_user_meta( $uid, self::META_EXPIRY_NOTICE_SENT, true ) );
				if ( $exp && $exp > $now && $exp <= $threshold && ( ! $notice_sent || $notice_sent < $exp ) ) {
					$manager_id = intval( get_user_meta( $uid, self::META_USER_MANAGER, true ) );
					if ( $manager_id ) {
						$this->notify_partner_user_expiring( $manager_id, $uid, $exp );
						update_user_meta( $uid, self::META_EXPIRY_NOTICE_SENT, time() );
					}
				}
			}
		}

		// expire users
		$users = get_users( [
			'meta_query' => [
				[
					'key' => self::META_USER_EXPIRY,
					'value' => 0,
					'compare' => '>',
					'type' => 'NUMERIC',
				],
			],
			'fields' => 'ID',
		] );
		foreach ( $users as $uid ) {
			$exp = intval( get_user_meta( $uid, self::META_USER_EXPIRY, true ) );
			if ( $exp && $exp <= $now ) {
				$manager_id = intval( get_user_meta( $uid, self::META_USER_MANAGER, true ) );
				// Get user data before deletion for notification
				$user_data = get_userdata( $uid );
				if ( 'delete_user' === $action ) {
					if ( $manager_id ) {
						$this->notify_partner_user_expired( $manager_id, $uid, $exp, $user_data );
					}
					wp_delete_user( $uid );
				} elseif ( 'remove_enrollment' === $action ) {
					$this->remove_user_enrollments( $uid );
					wp_update_user( [ 'ID' => $uid, 'role' => 'expired' ] );
					if ( $manager_id ) {
						$this->notify_partner_user_expired( $manager_id, $uid, $exp, $user_data );
					}
				}
			}
		}
	}

	/* Notify partner when invite used */
	private function notify_partner_invite_used( $partner_id, $user_id, $invite_code, $expiry_ts ) {
		$partner = get_userdata( $partner_id );
		$user = get_userdata( $user_id );
		if ( ! $partner || ! $user ) {
			return;
		}
		$to = $partner->user_email;
		if ( empty( $to ) ) {
			return;
		}
		$subject = sprintf( 'Invite used: %s', $invite_code );
		$expiry_text = $expiry_ts ? $this->format_date( $expiry_ts ) : 'N/A';
		$message = "Hello " . $partner->display_name . ",\n\n";
		$message .= sprintf( "Access code %s was used to create a new account.\n\n", $invite_code );
		$message .= "New user details:\n";
		$message .= "Username: " . $user->user_login . "\n";
		$message .= "Email: " . $user->user_email . "\n";
		$message .= "Expires: " . $expiry_text . "\n\n";
		$message .= "They have been enrolled in all courses.\n\n";
		$message .= "Regards,\nImpact Websites";
		wp_mail( $to, $subject, $message );
	}

	private function notify_partner_user_expiring( $partner_id, $user_id, $expiry_ts ) {
		$partner = get_userdata( $partner_id );
		$user = get_userdata( $user_id );
		if ( ! $partner || ! $user ) {
			return;
		}
		$to = $partner->user_email;
		if ( empty( $to ) ) {
			return;
		}
		$subject = sprintf( 'User expiring soon: %s', $user->user_login );
		$message = "Hello " . $partner->display_name . ",\n\n";
		$message .= sprintf( "Reminder: the user %s (%s) you manage is expiring on %s.\n\n", $user->user_login, $user->user_email, $this->format_date( $expiry_ts ) );
		$message .= "Regards,\nImpact Websites";
		wp_mail( $to, $subject, $message );
	}

	private function notify_partner_user_expired( $partner_id, $user_id, $expiry_ts, $user_data = null ) {
		$partner = get_userdata( $partner_id );
		if ( ! $partner ) {
			return;
		}
		$to = $partner->user_email;
		if ( empty( $to ) ) {
			return;
		}
		// Use provided user data or user_id as fallback
		$user_identifier = $user_data ? $user_data->user_login : "User ID {$user_id}";
		$subject = sprintf( 'User expired: %s', $user_identifier );
		$message = "Hello " . $partner->display_name . ",\n\n";
		$message .= "The user {$user_identifier} expired on " . $this->format_date( $expiry_ts ) . " and has been removed/updated according to site policy.\n\n";
		$message .= "Regards,\nImpact Websites";
		wp_mail( $to, $subject, $message );
	}

	/* Shortcode: show logged-in user's expiry */
	public function shortcode_my_expiry( $atts = [] ) {
		if ( ! is_user_logged_in() ) {
			return '<p>Please log in to see your membership expiry.</p>';
		}
		$user_id = get_current_user_id();
		$expiry_ts = intval( get_user_meta( $user_id, self::META_USER_EXPIRY, true ) );
		if ( ! $expiry_ts ) {
			return '<p>No membership expiry is set for your account.</p>';
		}
		$now = time();
		$expiry_text = $this->format_date( $expiry_ts );
		$seconds_left = $expiry_ts - $now;
		$days_left = ( $seconds_left > 0 ) ? ceil( $seconds_left / DAY_IN_SECONDS ) : 0;

		if ( $expiry_ts <= $now ) {
			$html  = '<div class="iw-expiry-notice iw-expired">';
			$html .= '<p><strong>Your membership expired on:</strong> ' . esc_html( $expiry_text ) . '.</p>';
			$html .= '<p>If you think this is an error or need access extended, please contact your partner admin or the site administrator.</p>';
			$html .= '</div>';
			return $html;
		}

		$html  = '<div class="iw-expiry-notice">';
		$html .= '<p><strong>Your membership expires on:</strong> ' . esc_html( $expiry_text ) . '.</p>';
		$html .= '<p><strong>Days remaining:</strong> ' . intval( $days_left ) . '</p>';
		$html .= '</div>';

		return $html;
	}

	/* Shortcode: login page (styled table) with lost password link */
	public function shortcode_login() {
		if ( is_user_logged_in() ) {
			return '<p>You are already logged in.</p>';
		}

		$options = get_option( self::OPTION_KEY, [] );
		$default_redirect = ! empty( $options['post_register_redirect'] ) ? $options['post_register_redirect'] : home_url( '/' );
		$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : $default_redirect;
		$lost_password_url = wp_lostpassword_url();

		ob_start();
		?>
		<style>
		.iw-login-table { width:100%; max-width:720px; border-collapse:collapse; margin:0 auto 1em; font-family: Arial, sans-serif; }
		.iw-login-table thead th { background:#f8f9fa; color:#333333; padding:14px; text-align:left; font-size:18px; border:1px solid #e6e6e6; }
		.iw-login-table td, .iw-login-table th { padding:12px; border:1px solid #e6e6e6; vertical-align:middle; }
		.iw-login-table td:nth-child(even) { background:#f7f7f7; }
		.iw-login-table td:first-child, .iw-login-table th:first-child { background:#f8f9fa; color:#333333; font-weight:600; }
		.iw-input { width:100%; max-width:480px; padding:10px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box; height:40px; }
		.iw-submit { background:#0073aa; color:#fff; border:none; padding:10px 16px; border-radius:4px; cursor:pointer; }
		.iw-note { color:#666; margin-bottom:10px; font-size:14px; text-align:center; }
		.iw-links { margin-top:8px; text-align:center; }
		.iw-links a { color:#0073aa; text-decoration:underline; }
		</style>

		<div class="iw-note">Please log in to access the site. If you do not have an account, ask your partner admin for an invite code.</div>

		<form name="loginform" id="loginform" action="<?php echo esc_url( wp_login_url() ); ?>" method="post" style="max-width:760px;margin:0 auto;">
			<table class="iw-login-table" role="presentation">
				<thead>
					<tr><th colspan="2">Site Login</th></tr>
				</thead>
				<tbody>
					<tr>
						<th style="width:35%;">Username or Email</th>
						<td><input name="log" id="user_login" class="iw-input" type="text" value="" size="20" required></td>
					</tr>
					<tr>
						<th>Password</th>
						<td><input name="pwd" id="user_pass" class="iw-input" type="password" value="" size="20" required></td>
					</tr>
					<tr>
						<th>Remember me</th>
						<td><label><input name="rememberme" type="checkbox" value="forever"> Keep me signed in</label></td>
					</tr>
					<tr>
						<td colspan="2" style="text-align:left;padding-top:10px;">
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
							<input type="submit" name="wp-submit" id="wp-submit" class="iw-submit" value="Log In" />
						</td>
					</tr>
					<tr>
						<td colspan="2" style="text-align:center;">
							<div class="iw-links">
								<a href="<?php echo esc_url( $lost_password_url ); ?>">Lost your password?</a>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</form>
		<?php
		return ob_get_clean();
	}

	/* Redirect users after login based on their role */
	public function partner_admin_login_redirect( $redirect_to, $request, $user ) {
		// Check if user is valid WP_User object
		if ( ! is_wp_error( $user ) && isset( $user->roles ) && is_array( $user->roles ) ) {
			$options = get_option( self::OPTION_KEY, [] );
			
			// Check for partner admin role
			if ( in_array( self::PARTNER_ROLE, $user->roles ) ) {
				$partner_redirect = ! empty( $options['post_login_partner_redirect'] ) ? $options['post_login_partner_redirect'] : home_url( '/partner-dashboard/' );
				return $partner_redirect;
			}
			
			// Check for subscriber role
			if ( in_array( 'subscriber', $user->roles ) ) {
				$subscriber_redirect = ! empty( $options['post_login_subscriber_redirect'] ) ? $options['post_login_subscriber_redirect'] : '';
				if ( ! empty( $subscriber_redirect ) ) {
					return $subscriber_redirect;
				}
			}
			
			// Check for users with no role (empty roles array)
			if ( empty( $user->roles ) ) {
				$norole_redirect = ! empty( $options['post_login_norole_redirect'] ) ? $options['post_login_norole_redirect'] : home_url( '/extend-my-membership/' );
				return $norole_redirect;
			}
		}
		return $redirect_to;
	}

	/* Enforce login required: allow configured login and registration pages */
	public function enforce_login_required() {
		// allow logged-in users
		if ( is_user_logged_in() ) {
			return;
		}

		// allow admin area so admins can manage the site
		if ( is_admin() ) {
			return;
		}

		// allow AJAX and REST
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		// allow wp-login.php
		$req_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		if ( false !== strpos( $req_uri, 'wp-login.php' ) ) {
			return;
		}

		$options = get_option( self::OPTION_KEY, [] );
		$login_url = ! empty( $options['login_page_url'] ) ? $options['login_page_url'] : '';
		$registration_url = ! empty( $options['registration_page_url'] ) ? $options['registration_page_url'] : '';

		// If no login page configured, don't enforce
		if ( empty( $login_url ) ) {
			return;
		}

		// Build current URL safely
		$current_url = ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		// Parse URLs and compare host+path only
		$login_parts = wp_parse_url( $login_url );
		$current_parts = wp_parse_url( $current_url );
		$login_host = strtolower( $login_parts['host'] ?? '' );
		$current_host = strtolower( $current_parts['host'] ?? '' );
		$login_path = rtrim( $login_parts['path'] ?? '/', '/' );
		$current_path = rtrim( $current_parts['path'] ?? '/', '/' );

		// if current is login page -> allow
		if ( $login_host && $current_host && $login_host === $current_host && $login_path === $current_path ) {
			return;
		}

		// if current is registration page (configured) -> allow
		if ( ! empty( $registration_url ) ) {
			$reg_parts = wp_parse_url( $registration_url );
			$reg_host = strtolower( $reg_parts['host'] ?? '' );
			$reg_path = rtrim( $reg_parts['path'] ?? '/', '/' );
			if ( $reg_host && $reg_path && $reg_host === $current_host && $reg_path === $current_path ) {
				return;
			}
		}

		// redirect to login page, preserving target
		$target = add_query_arg( 'redirect_to', rawurlencode( $current_url ), $login_url );
		wp_safe_redirect( $target );
		exit;
	}
}

new Impact_Websites_Student_Management();
