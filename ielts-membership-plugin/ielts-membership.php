<?php
/**
 * Plugin Name: Impact Websites Student Management
 * Description: Partner-admin invite system for LearnDash. Shared partner dashboard (global pool) so multiple partner admins see the same codes and users. Single-use invite codes, auto-enrol in ALL LearnDash courses, site-wide login enforcement with public registration.
 * Version: 2.1
 * Author: Impact Websites
 * License: GPLv2 or later
 *
 * Change in 2.1:
 * - Fixed login redirect issue: When already logged in users visit login page with redirect_to parameter, they are now automatically redirected to the intended destination instead of showing a message.
 * - Changed admin menu label from "Documentation" to "Docs 2025".
 *
 * Change in 2.0:
 * - Fixed [iw_my_expiry] shortcode to properly display account management form with ability to update email, name, and change password.
 * - Changed admin menu label from "Docs" to "Documentation".
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
define( 'IW_PLUGIN_VERSION', '2.1' );

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
	const META_INVITE_DAYS = '_iw_invite_days';
	const META_USER_MANAGER = '_iw_user_manager';
	const META_USER_EXPIRY = '_iw_user_expiry';
	const META_EXPIRY_NOTICE_SENT = '_iw_expiry_notice_sent';
	const META_LAST_LOGIN = '_iw_last_login';
	const OPTION_KEY = 'iw_student_management_options';
	const CRON_HOOK = 'iw_sm_daily_cron';
	const AJAX_CREATE = 'iw_create_invite';
	const AJAX_REVOKE = 'iw_revoke_student';
	const AJAX_DELETE_CODE = 'iw_delete_code';
	const AJAX_UPDATE_EXPIRY = 'iw_update_expiry';
	const AJAX_REENROL = 'iw_reenrol_student';
	const AJAX_CREATE_USER = 'iw_create_user_manually';
	const DEFAULT_REENROL_DAYS = 30;
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
		add_action( 'wp_ajax_' . self::AJAX_DELETE_CODE, [ $this, 'ajax_delete_code' ] );
		add_action( 'wp_ajax_' . self::AJAX_UPDATE_EXPIRY, [ $this, 'ajax_update_expiry' ] );
		add_action( 'wp_ajax_' . self::AJAX_REENROL, [ $this, 'ajax_reenrol_student' ] );
		add_action( 'wp_ajax_' . self::AJAX_CREATE_USER, [ $this, 'ajax_create_user_manually' ] );

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
		
		// Override login errors to keep them within plugin
		add_filter( 'wp_login_failed', [ $this, 'handle_login_failed' ], 10, 2 );
		add_filter( 'authenticate', [ $this, 'handle_login_errors' ], 30, 3 );

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
		
		// Add user profile fields for expiry date editing in admin
		add_action( 'show_user_profile', [ $this, 'show_expiry_field' ] );
		add_action( 'edit_user_profile', [ $this, 'show_expiry_field' ] );
		add_action( 'personal_options_update', [ $this, 'save_expiry_field' ] );
		add_action( 'edit_user_profile_update', [ $this, 'save_expiry_field' ] );
		
		// Track last login time
		add_action( 'wp_login', [ $this, 'track_last_login' ], 10, 2 );
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
		add_submenu_page( 'iw-partnership-area', 'Docs 2025', 'Docs 2025', 'manage_options', 'iw-partnership-docs', [ $this, 'docs_page_html' ] );
	}

	public function register_settings() {
		register_setting( 'iw_student_management', self::OPTION_KEY, [ 'sanitize_callback' => [ $this, 'sanitize_options' ] ] );
		add_settings_section( 'iw_sm_main', 'Main settings', null, 'iw-student-management' );
		add_settings_field( 'default_days', 'Default invite length (days)', [ $this, 'field_default_days' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'default_partner_limit', 'Max students per partner (0 = unlimited)', [ $this, 'field_partner_limit' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'notify_days_before', 'Notify partners this many days before expiry', [ $this, 'field_notify_days_before' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'post_register_redirect', 'Post-registration redirect page', [ $this, 'field_post_register_redirect' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'post_login_subscriber_redirect', 'Post-login redirect page for subscribers', [ $this, 'field_post_login_subscriber_redirect' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'post_login_partner_redirect', 'Post-login redirect page for partner admins', [ $this, 'field_post_login_partner_redirect' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'post_login_norole_redirect', 'Post-login redirect page for users with no role', [ $this, 'field_post_login_norole_redirect' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'logout_redirect', 'Post-logout redirect page', [ $this, 'field_logout_redirect' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'login_page_url', 'Login page (required for site-wide access control)', [ $this, 'field_login_page_url' ], 'iw-student-management', 'iw_sm_main' );
		add_settings_field( 'registration_page_url', 'Registration page (public)', [ $this, 'field_registration_page_url' ], 'iw-student-management', 'iw_sm_main' );
	}

	public function sanitize_options( $vals ) {
		$vals = (array) $vals;
		$vals['default_days'] = isset( $vals['default_days'] ) ? intval( $vals['default_days'] ) : 30;
		$vals['default_partner_limit'] = isset( $vals['default_partner_limit'] ) ? intval( $vals['default_partner_limit'] ) : 10;
		$vals['notify_days_before'] = isset( $vals['notify_days_before'] ) ? intval( $vals['notify_days_before'] ) : 7;
		
		// For page dropdowns, store the page ID as integer
		$vals['post_register_redirect'] = isset( $vals['post_register_redirect'] ) ? intval( $vals['post_register_redirect'] ) : 0;
		$vals['post_login_subscriber_redirect'] = isset( $vals['post_login_subscriber_redirect'] ) ? intval( $vals['post_login_subscriber_redirect'] ) : 0;
		$vals['post_login_partner_redirect'] = isset( $vals['post_login_partner_redirect'] ) ? intval( $vals['post_login_partner_redirect'] ) : 0;
		$vals['post_login_norole_redirect'] = isset( $vals['post_login_norole_redirect'] ) ? intval( $vals['post_login_norole_redirect'] ) : 0;
		$vals['logout_redirect'] = isset( $vals['logout_redirect'] ) ? intval( $vals['logout_redirect'] ) : 0;
		$vals['login_page_url'] = isset( $vals['login_page_url'] ) ? intval( $vals['login_page_url'] ) : 0;
		$vals['registration_page_url'] = isset( $vals['registration_page_url'] ) ? intval( $vals['registration_page_url'] ) : 0;
		
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



	public function field_notify_days_before() {
		$options = get_option( self::OPTION_KEY, [] );
		$val = $options['notify_days_before'] ?? 7;
		echo '<input type="number" name="' . self::OPTION_KEY . '[notify_days_before]" value="' . esc_attr( $val ) . '" min="0" />';
		echo '<p class="description">Set 0 to disable advance notifications.</p>';
	}

	public function field_post_register_redirect() {
		$options = get_option( self::OPTION_KEY, [] );
		$selected = $this->convert_legacy_url_to_page_id( $options['post_register_redirect'] ?? 0 );
		
		$this->render_page_dropdown( 'post_register_redirect', $selected, 'my-account' );
		echo '<p class="description">Select the page to redirect newly-registered users to after automatic login (site-wide). Leave blank to send users to the homepage.</p>';
	}

	public function field_post_login_subscriber_redirect() {
		$options = get_option( self::OPTION_KEY, [] );
		$selected = $this->convert_legacy_url_to_page_id( $options['post_login_subscriber_redirect'] ?? 0 );
		
		$this->render_page_dropdown( 'post_login_subscriber_redirect', $selected, 'my-account' );
		echo '<p class="description">Select the page to redirect subscribers to after login. Leave blank to use default WordPress behavior.</p>';
	}

	public function field_post_login_partner_redirect() {
		$options = get_option( self::OPTION_KEY, [] );
		$selected = $this->convert_legacy_url_to_page_id( $options['post_login_partner_redirect'] ?? 0 );
		
		$this->render_page_dropdown( 'post_login_partner_redirect', $selected, 'partner-dashboard' );
		echo '<p class="description">Select the page to redirect partner admins to after login. Leave blank to use partner-dashboard.</p>';
	}

	public function field_post_login_norole_redirect() {
		$options = get_option( self::OPTION_KEY, [] );
		$selected = $this->convert_legacy_url_to_page_id( $options['post_login_norole_redirect'] ?? 0 );
		
		$this->render_page_dropdown( 'post_login_norole_redirect', $selected, 'extend-my-membership' );
		echo '<p class="description">Select the page to redirect users with no role to after login (e.g., expired users who need to extend membership). Leave blank to use extend-my-membership.</p>';
	}

	public function field_login_page_url() {
		$options = get_option( self::OPTION_KEY, [] );
		$selected = $this->convert_legacy_url_to_page_id( $options['login_page_url'] ?? 0 );
		
		$this->render_page_dropdown( 'login_page_url', $selected, 'login' );
		echo '<p class="description">Select the page that contains the [iw_login] shortcode. This is required for site-wide access control.</p>';
	}

	public function field_registration_page_url() {
		$options = get_option( self::OPTION_KEY, [] );
		$selected = $this->convert_legacy_url_to_page_id( $options['registration_page_url'] ?? 0 );
		
		$this->render_page_dropdown( 'registration_page_url', $selected, 'register' );
		echo '<p class="description">Select the page that contains the [iw_register_with_code] shortcode. This page must be publicly accessible so students can register.</p>';
	}
	
	public function field_logout_redirect() {
		$options = get_option( self::OPTION_KEY, [] );
		$selected = $this->convert_legacy_url_to_page_id( $options['logout_redirect'] ?? 0 );
		
		$this->render_page_dropdown( 'logout_redirect', $selected, 'login' );
		echo '<p class="description">Select the page to redirect users to after logout. Leave blank to use the login page.</p>';
	}
	
	/**
	 * Convert legacy URL string to page ID
	 * 
	 * @param mixed $value The value to convert (page ID or legacy URL string)
	 * @return int The page ID or 0 if not found
	 */
	private function convert_legacy_url_to_page_id( $value ) {
		// If it's already a page ID, return it
		if ( is_int( $value ) || ( is_numeric( $value ) && intval( $value ) > 0 ) ) {
			return intval( $value );
		}
		
		// If we have an old URL value, try to convert it to a page ID
		if ( is_string( $value ) && ! empty( $value ) ) {
			$page_id = url_to_postid( $value );
			if ( $page_id ) {
				return $page_id;
			}
		}
		
		return 0;
	}
	
	/**
	 * Render a page dropdown selector
	 * 
	 * @param string $field_name The field name for the setting
	 * @param int $selected The currently selected page ID
	 * @param string $default_slug The default page slug to highlight
	 */
	private function render_page_dropdown( $field_name, $selected, $default_slug = '' ) {
		$pages = get_pages( array(
			'sort_column' => 'post_title',
			'sort_order' => 'ASC',
		) );
		
		echo '<select name="' . esc_attr( self::OPTION_KEY . '[' . $field_name . ']' ) . '" id="iw_' . esc_attr( $field_name ) . '" style="width:60%;">';
		echo '<option value="0">' . esc_html( '-- Select a page --' ) . '</option>';
		
		foreach ( $pages as $page ) {
			$page_slug = $page->post_name;
			// Match pages that contain the default slug as a word
			// For example: 'login' matches 'login', 'my-login', 'login-page'
			$is_default = false;
			if ( $default_slug && strpos( $page_slug, $default_slug ) !== false ) {
				$is_default = true;
			}
			$label = esc_html( $page->post_title );
			
			// Add indicator for default/recommended page
			if ( $is_default ) {
				$label .= ' (recommended)';
			}
			
			echo '<option value="' . esc_attr( $page->ID ) . '"';
			selected( $selected, $page->ID );
			echo '>' . $label . '</option>';
		}
		
		echo '</select>';
	}
	
	/**
	 * Get URL from page ID setting with fallback
	 * 
	 * @param mixed $page_id_or_url The page ID (int) or legacy URL (string)
	 * @param string $fallback_url The fallback URL if page ID is not set
	 * @return string The page URL or fallback URL
	 */
	private function get_url_from_page_setting( $page_id_or_url, $fallback_url = '' ) {
		// If it's already a URL string (legacy), return it
		if ( is_string( $page_id_or_url ) && ! empty( $page_id_or_url ) ) {
			return $page_id_or_url;
		}
		
		// If it's a page ID, get the permalink
		$page_id = intval( $page_id_or_url );
		if ( $page_id > 0 ) {
			$url = get_permalink( $page_id );
			if ( $url && ! is_wp_error( $url ) ) {
				return $url;
			}
		}
		
		// Return fallback
		return $fallback_url;
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
		<?php
	}

	public function docs_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>Partnership area - Docs 2025</h1>
			
			<div style="max-width: 1200px;">
				<h2>Available Shortcodes</h2>
				<p>This plugin provides several shortcodes that you can use on your WordPress pages. Below is a comprehensive guide to each shortcode and its purpose.</p>
				
				<div style="background: #fff; border: 1px solid #ccc; padding: 20px; margin: 20px 0; border-radius: 4px;">
					<h3 style="margin-top: 0;"><code>[iw_partner_dashboard]</code></h3>
					<p><strong>Purpose:</strong> Displays the partner dashboard where partner admins can create invite codes and manage students.</p>
					<p><strong>Access:</strong> Requires <code>manage_partner_invites</code> capability (available to administrator, partner_admin, or impact_manager roles).</p>
					<p><strong>Features:</strong></p>
					<ul>
						<li>Create up to 10 single-use invite codes at once</li>
						<li>View all invite codes (used and available)</li>
						<li>See who used each code and when</li>
						<li>List of all active students with expiration dates</li>
						<li>Ability to revoke student access</li>
						<li>Global dashboard - all partner admins see the same codes and students (shared company pool)</li>
					</ul>
					<p><strong>Usage:</strong> Create a page (e.g., "Partner Dashboard") and add this shortcode. Configure the post-login redirect for partner admins to point to this page in the settings.</p>
				</div>
				
				<div style="background: #fff; border: 1px solid #ccc; padding: 20px; margin: 20px 0; border-radius: 4px;">
					<h3 style="margin-top: 0;"><code>[iw_register_with_code]</code></h3>
					<p><strong>Purpose:</strong> Displays the registration form where students can create accounts using invite codes.</p>
					<p><strong>Access:</strong> Public (must be configured as publicly accessible in settings).</p>
					<p><strong>Features:</strong></p>
					<ul>
						<li>Registration form with fields for invite code, first name, last name, username, email, and password</li>
						<li>Validates single-use invite codes</li>
						<li>Automatically creates WordPress user account with subscriber role</li>
						<li>Enrolls new user in ALL LearnDash courses</li>
						<li>Sets expiration date based on plugin settings</li>
						<li>Automatically logs in user after registration</li>
						<li>Notifies partner admin of successful registration</li>
					</ul>
					<p><strong>Usage:</strong> Create a page (e.g., "Register") and add this shortcode. Configure the Registration page URL in Partnership area > Settings to exempt this page from login enforcement.</p>
				</div>
				
				<div style="background: #fff; border: 1px solid #ccc; padding: 20px; margin: 20px 0; border-radius: 4px;">
					<h3 style="margin-top: 0;"><code>[iw_login]</code></h3>
					<p><strong>Purpose:</strong> Displays a custom styled login form for the site.</p>
					<p><strong>Access:</strong> Public.</p>
					<p><strong>Features:</strong></p>
					<ul>
						<li>Custom styled login form with username/email and password fields</li>
						<li>Remember me checkbox</li>
						<li>Lost password link</li>
						<li>Redirects users based on their role after login (configurable in settings)</li>
					</ul>
					<p><strong>Usage:</strong> Create a page (e.g., "Login") and add this shortcode. Configure the Login page URL in Partnership area > Settings for site-wide login enforcement.</p>
				</div>
				
				<div style="background: #fff; border: 1px solid #ccc; padding: 20px; margin: 20px 0; border-radius: 4px;">
					<h3 style="margin-top: 0;"><code>[iw_my_expiry]</code></h3>
					<p><strong>Purpose:</strong> Displays the user's account information, allowing them to edit their profile and change their password.</p>
					<p><strong>Access:</strong> Logged-in users only.</p>
					<p><strong>Features:</strong></p>
					<ul>
						<li>Shows and allows editing of first name, last name, and email</li>
						<li>Displays username (read-only)</li>
						<li>Shows membership expiry date and days remaining</li>
						<li>Password change form with current password verification</li>
						<li>Indicates if membership has expired</li>
					</ul>
					<p><strong>Usage:</strong> Create a page (e.g., "My Account") and add this shortcode. This is useful for subscribers to manage their profile and see when their access expires.</p>
				</div>
				
				<div style="background: #fff; border: 1px solid #ccc; padding: 20px; margin: 20px 0; border-radius: 4px;">
					<h3 style="margin-top: 0;"><code>[extend-membership]</code></h3>
					<p><strong>Purpose:</strong> Allows users with no role (expired users) to extend their membership using a new invite code.</p>
					<p><strong>Access:</strong> Logged-in users only.</p>
					<p><strong>Features:</strong></p>
					<ul>
						<li>Form to enter an extension code</li>
						<li>Validates invite codes (single-use)</li>
						<li>Restores subscriber role</li>
						<li>Re-enrolls user in all LearnDash courses</li>
						<li>Sets new expiration date</li>
						<li>Notifies partner admin of membership extension</li>
					</ul>
					<p><strong>Usage:</strong> Create a page (e.g., "Extend My Membership") and add this shortcode. Configure the post-login redirect for users with no role to point to this page in the settings.</p>
				</div>
				
				<h2>Important Notes</h2>
				<div style="background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; margin: 20px 0; border-radius: 4px;">
					<h3 style="margin-top: 0;">User Expiry Behavior</h3>
					<p>When a user's membership expires, the system automatically:</p>
					<ul>
						<li>Removes all LearnDash course enrollments</li>
						<li>Changes the user's role to "none" (no role)</li>
						<li>User can still log in but will have no access to courses</li>
						<li>Users with no role are redirected to the extend membership page upon login</li>
						<li>Partner admin receives notification of the expiration</li>
					</ul>
				</div>
				
				<div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; margin: 20px 0; border-radius: 4px;">
					<h3 style="margin-top: 0;">Required Configuration</h3>
					<p>For the plugin to work correctly, you must:</p>
					<ol>
						<li>Create pages with the shortcodes listed above</li>
						<li>Configure the Login page URL in Partnership area > Settings</li>
						<li>Configure the Registration page URL in Partnership area > Settings</li>
						<li>Set up post-login redirect URLs for different user roles</li>
						<li>Configure the default invite length (days)</li>
					</ol>
				</div>
				
				<h2>Support</h2>
				<p>If you encounter any issues or have questions about using these shortcodes, please contact the plugin administrator or refer to the main README file in the plugin directory.</p>
			</div>
		</div>
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
		$days = isset( $_POST['days'] ) ? intval( $_POST['days'] ) : 30;
		$email_codes = isset( $_POST['email_codes'] ) && $_POST['email_codes'] === 'yes';

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
			update_post_meta( $post_id, self::META_INVITE_DAYS, $days );
			$codes[] = $code;
		}

		if ( empty( $codes ) ) {
			wp_send_json_error( 'Could not create invites' );
		}

		// Email codes to partner admin if requested
		if ( $email_codes ) {
			$partner = get_userdata( $partner_id );
			if ( $partner && $partner->user_email ) {
				$subject = 'Your invite codes';
				$display_name = sanitize_text_field( $partner->display_name );
				$message = "Hello " . $display_name . ",\n\n";
				$message .= "You have created " . count( $codes ) . " invite code(s), each allowing " . $days . " days of access:\n\n";
				$message .= implode( "\n", $codes ) . "\n\n";
				$message .= "Share these codes with your students.\n\n";
				$message .= "Regards,\nImpact Websites";
				$result = wp_mail( $partner->user_email, $subject, $message );
				// Note: Email send failure is non-critical, codes are still created successfully
			}
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

		// Validate user exists
		$user = get_userdata( $student_id );
		if ( ! $user ) {
			wp_send_json_error( 'Invalid user ID', 400 );
		}
		
		// Check if user is a subscriber (active student)
		if ( ! in_array( 'subscriber', $user->roles ) ) {
			wp_send_json_error( 'User is not an active student', 403 );
		}
		
		// Ensure user has manager meta assigned (handles pre-existing users)
		$this->ensure_user_manager( $student_id );
		
		// Set expiry to now (marks as expired)
		update_user_meta( $student_id, self::META_USER_EXPIRY, time() );
		
		// Remove all LearnDash course enrollments
		$this->remove_user_enrollments( $student_id );
		
		// Remove all roles from user (sets to no role)
		$user->set_role( '' );

		wp_send_json_success( 'revoked' );
	}

	/* AJAX: delete invite code (only available or expired codes can be deleted) */
	public function ajax_delete_code() {
		if ( ! is_user_logged_in() || ! current_user_can( self::CAP_MANAGE ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		if ( empty( $_POST['iw_dash_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['iw_dash_nonce'] ), self::NONCE_DASH ) ) {
			wp_send_json_error( 'Invalid request', 400 );
		}
		$code_id = isset( $_POST['code_id'] ) ? intval( $_POST['code_id'] ) : 0;
		if ( ! $code_id ) {
			wp_send_json_error( 'Missing code ID', 400 );
		}

		$invite = get_post( $code_id );
		if ( ! $invite || $invite->post_type !== self::CPT_INVITE ) {
			wp_send_json_error( 'Invalid code', 400 );
		}

		$used = get_post_meta( $code_id, self::META_INVITE_USED, true );
		
		// Only allow deletion if code is available (not used)
		if ( $used ) {
			$used_by = get_post_meta( $code_id, self::META_INVITE_USED_BY, true );
			$user = get_userdata( intval( $used_by ) );
			
			// Check if user is expired (no longer has subscriber role or expiry passed)
			$now = time();
			$is_expired = false;
			if ( ! $user || ! in_array( 'subscriber', $user->roles ) ) {
				$is_expired = true;
			} else {
				$exp = intval( get_user_meta( $user->ID, self::META_USER_EXPIRY, true ) );
				if ( $exp && $exp <= $now ) {
					$is_expired = true;
				}
			}
			
			if ( ! $is_expired ) {
				wp_send_json_error( 'Cannot delete active codes. Only available or expired codes can be deleted.', 403 );
			}
		}

		// Delete the invite post
		$result = wp_delete_post( $code_id, true );
		if ( ! $result ) {
			wp_send_json_error( 'Failed to delete code' );
		}

		wp_send_json_success( 'deleted' );
	}

	/* AJAX: update student expiry date (global — any partner admin can update any managed student) */
	public function ajax_update_expiry() {
		if ( ! is_user_logged_in() || ! current_user_can( self::CAP_MANAGE ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		if ( empty( $_POST['iw_dash_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['iw_dash_nonce'] ), self::NONCE_DASH ) ) {
			wp_send_json_error( 'Invalid request', 400 );
		}
		$student_id = isset( $_POST['student_id'] ) ? intval( $_POST['student_id'] ) : 0;
		$expiry_date = isset( $_POST['expiry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['expiry_date'] ) ) : '';
		
		if ( ! $student_id || ! $expiry_date ) {
			wp_send_json_error( 'Missing required fields', 400 );
		}

		$user = get_userdata( $student_id );
		if ( ! $user ) {
			wp_send_json_error( 'Invalid user', 400 );
		}

		// Check if user is a subscriber (active student)
		if ( ! in_array( 'subscriber', $user->roles ) ) {
			wp_send_json_error( 'User is not an active student', 403 );
		}
		
		// Ensure user has manager meta assigned (handles pre-existing users)
		$this->ensure_user_manager( $student_id );

		// Convert date to timestamp (end of day)
		$timestamp = strtotime( $expiry_date . ' 23:59:59' );
		if ( ! $timestamp ) {
			wp_send_json_error( 'Invalid date format', 400 );
		}

		// Update the expiry
		update_user_meta( $student_id, self::META_USER_EXPIRY, $timestamp );
		
		// Clear any expiry notice flag
		delete_user_meta( $student_id, self::META_EXPIRY_NOTICE_SENT );

		wp_send_json_success( 'Expiry updated' );
	}

	/* AJAX: re-enrol inactive student (global — any partner admin can re-enrol any previously managed student) */
	public function ajax_reenrol_student() {
		if ( ! is_user_logged_in() || ! current_user_can( self::CAP_MANAGE ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		if ( empty( $_POST['iw_dash_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['iw_dash_nonce'] ), self::NONCE_DASH ) ) {
			wp_send_json_error( 'Invalid request', 400 );
		}
		$student_id = isset( $_POST['student_id'] ) ? intval( $_POST['student_id'] ) : 0;
		$days = isset( $_POST['days'] ) ? intval( $_POST['days'] ) : 0;
		
		if ( ! $student_id || ! $days ) {
			wp_send_json_error( 'Missing required fields', 400 );
		}
		
		$user = get_userdata( $student_id );
		if ( ! $user ) {
			wp_send_json_error( 'Invalid user', 400 );
		}
		
		// Check if user is already active (subscriber)
		if ( in_array( 'subscriber', $user->roles ) ) {
			wp_send_json_error( 'User is already an active student', 403 );
		}
		
		// Ensure user has manager meta assigned (handles pre-existing users)
		$this->ensure_user_manager( $student_id );
		
		// Calculate new expiry
		$new_expiry = time() + ( $days * DAY_IN_SECONDS );
		
		// Re-activate the user
		$user->set_role( 'subscriber' );
		update_user_meta( $student_id, self::META_USER_EXPIRY, $new_expiry );
		delete_user_meta( $student_id, self::META_EXPIRY_NOTICE_SENT );
		
		// Re-enroll in all LearnDash courses
		$this->enroll_user_in_all_courses( $student_id );
		
		wp_send_json_success( 'Student re-enrolled successfully' );
	}

	/* AJAX: create user manually without invite code */
	public function ajax_create_user_manually() {
		if ( ! is_user_logged_in() || ! current_user_can( self::CAP_MANAGE ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		if ( empty( $_POST['iw_dash_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['iw_dash_nonce'] ), self::NONCE_DASH ) ) {
			wp_send_json_error( 'Invalid request', 400 );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$days = isset( $_POST['days'] ) ? intval( $_POST['days'] ) : 0;

		// Validate required fields
		if ( empty( $email ) || empty( $first_name ) || empty( $last_name ) ) {
			wp_send_json_error( 'Email, first name, and last name are required', 400 );
		}

		// Validate email format
		if ( ! is_email( $email ) ) {
			wp_send_json_error( 'Invalid email address', 400 );
		}

		// Check if email already exists
		if ( email_exists( $email ) ) {
			wp_send_json_error( 'A user with this email address already exists', 400 );
		}

		// Default to 30 days if not specified
		if ( ! $days ) {
			$options = get_option( self::OPTION_KEY, [] );
			$days = intval( $options['default_days'] ?? 30 );
		}

		// Check partner limit (global pool)
		$options = get_option( self::OPTION_KEY, [] );
		$global_limit = intval( $options['default_partner_limit'] ?? 0 );
		if ( $global_limit > 0 ) {
			$active_count = $this->count_active_managed_users( null );
			if ( $active_count >= $global_limit ) {
				wp_send_json_error( 'The partner pool has reached its active account limit. Cannot create new user.', 403 );
			}
		}

		// Generate username from email (everything before @)
		$at_pos = strpos( $email, '@' );
		if ( $at_pos === false || $at_pos === 0 ) {
			// Fallback if email doesn't have @ or @ is at start
			// Use first 8 chars of a random password for unpredictable username
			$username = sanitize_user( 'user_' . substr( wp_generate_password( 8, false, false ), 0, 8 ) );
		} else {
			$username = sanitize_user( substr( $email, 0, $at_pos ) );
		}
		
		// Ensure username is unique
		$base_username = $username;
		$counter = 1;
		while ( username_exists( $username ) ) {
			$username = $base_username . $counter;
			$counter++;
		}

		// Generate random password with special characters for better security
		$password = wp_generate_password( 16, true, true );

		// Create the user
		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( 'Could not create user: ' . $user_id->get_error_message(), 500 );
		}

		// Set user role
		$user = new WP_User( $user_id );
		$user->set_role( 'subscriber' );

		// Save first name and last name
		update_user_meta( $user_id, 'first_name', $first_name );
		update_user_meta( $user_id, 'last_name', $last_name );

		// Set expiry and manager
		$partner_id = get_current_user_id();
		$expiry_ts = time() + ( $days * DAY_IN_SECONDS );
		update_user_meta( $user_id, self::META_USER_MANAGER, $partner_id );
		update_user_meta( $user_id, self::META_USER_EXPIRY, $expiry_ts );
		delete_user_meta( $user_id, self::META_EXPIRY_NOTICE_SENT );

		// Enroll in all LearnDash courses
		$this->enroll_user_in_all_courses( $user_id );

		// Send welcome email with credentials
		$this->send_welcome_email( $user_id, $username, $password, $email, $first_name, $expiry_ts );

		// Notify partner admin
		$this->notify_partner_manual_user_created( $partner_id, $user_id, $username, $email, $expiry_ts );

		wp_send_json_success( [
			'message' => 'User created successfully',
			'username' => $username,
			'email' => $email,
			'expiry' => $this->format_date( $expiry_ts )
		] );
	}

	/**
	 * Ensure user has manager meta assigned (for pre-existing users)
	 * 
	 * If user doesn't have a manager set, assigns the current partner admin as manager.
	 * This handles pre-existing users from before plugin installation.
	 * 
	 * IMPORTANT: This method should only be called from AJAX handlers that have already
	 * verified the current user has the CAP_MANAGE capability. It assumes the current
	 * user is authorized to manage students.
	 * 
	 * @param int $user_id User ID to check and update
	 * @return void
	 */
	private function ensure_user_manager( $user_id ) {
		$mgr = intval( get_user_meta( $user_id, self::META_USER_MANAGER, true ) );
		if ( ! $mgr ) {
			$current_partner_id = get_current_user_id();
			update_user_meta( $user_id, self::META_USER_MANAGER, $current_partner_id );
		}
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

		// All students with subscriber role (active students)
		$now = time();
		$users = get_users( [
			'role' => 'subscriber',
			'fields' => 'all_with_meta',
		] );
		$all_students = $users; // All subscribers are included in the list

		// Get inactive students (users who were previously managed but are no longer subscribers)
		// Use meta_query to efficiently filter users with partner manager
		$all_users = get_users( [
			'fields' => 'all_with_meta',
			'meta_query' => [
				[
					'key'     => self::META_USER_MANAGER,
					'compare' => 'EXISTS',
				],
			],
		] );
		$inactive_students = [];
		foreach ( $all_users as $u ) {
			// Only include users who are no longer subscribers
			if ( ! in_array( 'subscriber', $u->roles ) ) {
				$inactive_students[] = $u;
			}
		}

		// limit: use global partner limit (site setting). This is for the shared pool.
		$options = get_option( self::OPTION_KEY, [] );
		$global_limit = intval( $options['default_partner_limit'] ?? 0 );
		$active_count = count( $all_students );
		$inactive_count = count( $inactive_students );
		$slots_left = ( $global_limit === 0 ) ? 'Unlimited' : max( 0, $global_limit - $active_count );

		ob_start();
		$dash_nonce = wp_create_nonce( self::NONCE_DASH );
		?>
		<style>
		.iw-form-table { width:100%; border-collapse:collapse; margin-bottom:1.5em; }
		.iw-form-table td, .iw-form-table th { padding:12px; border:1px solid #ddd; }
		.iw-form-table th { background:#f8f9fa; font-weight:600; width:50%; }
		.iw-form-table input[type="text"], .iw-form-table input[type="email"], .iw-form-table input[type="number"], .iw-form-table select { width:100%; padding:12px; box-sizing:border-box; }
		.iw-tabs { margin:20px 0 10px; border-bottom:1px solid #ccc; }
		.iw-tabs button { background:none; border:none; padding:10px 20px; cursor:pointer; font-size:14px; border-bottom:2px solid transparent; margin-bottom:-1px; }
		.iw-tabs button.active { border-bottom-color:#0073aa; color:#0073aa; font-weight:bold; }
		.iw-tabs button:hover { background:#f0f0f0; }
		.iw-code-row { display:none; }
		.iw-code-row.show { display:table-row; }
		.iw-dashboard-section { border:1px solid #ddd; border-radius:8px; padding:20px; margin-bottom:25px; background:#fff; }
		.iw-dashboard-section h2 { margin-top:0; }
		.iw-expiry-input { width:120px; padding:4px 8px; font-size:13px; }
		.iw-update-expiry { margin-left:8px; }
		.iw-student-section { display:none; }
		.iw-student-section.active { display:block; }
		.iw-days-input { width:80px; padding:4px 8px; font-size:13px; margin-right:8px; }
		.iw-search-box { margin:15px 0; }
		.iw-search-box input { padding:8px; width:300px; font-size:14px; border:1px solid #ddd; border-radius:4px; }
		#iw-active-students-table td, #iw-active-students-table th, #iw-inactive-students-table td, #iw-inactive-students-table th { padding:8px; }
		</style>
		<div id="iw-partner-dashboard">
			<div class="iw-dashboard-section">
				<h2>Create invite codes</h2>
				<form id="iw-create-invite-form">
					<input type="hidden" name="iw_dash_nonce" value="<?php echo esc_attr( $dash_nonce ); ?>" />
					<table class="iw-form-table">
					<tr>
						<th>How many codes do you want to create?</th>
						<td><input type="number" name="quantity" value="1" min="1" max="10" /></td>
					</tr>
					<tr>
						<th>How many days' access should each code allow?</th>
						<td>
							<select name="days">
								<option value="30">30</option>
								<option value="60">60</option>
								<option value="90">90</option>
								<option value="180">180</option>
								<option value="365">365</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>Email codes to me?</th>
						<td><label><input type="checkbox" name="email_codes" value="yes" /> Yes, send codes to my email</label></td>
					</tr>
					<tr>
						<td colspan="2" style="text-align:center;">
							<button type="submit" class="button button-primary">Create codes</button>
						</td>
					</tr>
				</table>
			</form>
			</div>

			<div class="iw-dashboard-section">
				<h2>Create user manually</h2>
				<p>Create a new user account directly without using an invite code. The user will receive an email with their login credentials.</p>
				<form id="iw-create-user-form">
					<input type="hidden" name="iw_dash_nonce" value="<?php echo esc_attr( $dash_nonce ); ?>" />
					<table class="iw-form-table">
					<tr>
						<th>Email Address</th>
						<td><input type="email" name="email" required placeholder="user@example.com" /></td>
					</tr>
					<tr>
						<th>First Name</th>
						<td><input type="text" name="first_name" required placeholder="John" /></td>
					</tr>
					<tr>
						<th>Last Name</th>
						<td><input type="text" name="last_name" required placeholder="Doe" /></td>
					</tr>
					<tr>
						<th>How many days' access?</th>
						<td>
							<select name="days">
								<option value="30">30</option>
								<option value="60">60</option>
								<option value="90">90</option>
								<option value="180">180</option>
								<option value="365">365</option>
							</select>
						</td>
					</tr>
					<tr>
						<td colspan="2" style="text-align:center;">
							<button type="submit" class="button button-primary">Create User</button>
						</td>
					</tr>
				</table>
			</form>
			</div>

			<div class="iw-dashboard-section">
				<h2>Your codes</h2>
				<div class="iw-tabs">
				<button class="iw-tab active" data-tab="all">ALL</button>
				<button class="iw-tab" data-tab="active">Active</button>
				<button class="iw-tab" data-tab="available">Available</button>
				<button class="iw-tab" data-tab="expired">Expired</button>
			</div>
			<table class="widefat" id="iw-codes-table">
				<thead><tr><th>Code</th><th>Days Access</th><th>Status</th><th>Used by</th><th>Activated on</th><th>Action</th></tr></thead>
				<tbody>
				<?php
				if ( empty( $invites ) ) {
					echo '<tr><td colspan="6">No codes yet.</td></tr>';
				} else {
					foreach ( $invites as $inv ) {
						$code = get_post_meta( $inv->ID, self::META_INVITE_CODE, true );
						$days = intval( get_post_meta( $inv->ID, self::META_INVITE_DAYS, true ) );
						$days_text = $days > 0 ? $days : '-';
						$used = get_post_meta( $inv->ID, self::META_INVITE_USED, true );
						$used_by = get_post_meta( $inv->ID, self::META_INVITE_USED_BY, true );
						$used_at = intval( get_post_meta( $inv->ID, self::META_INVITE_USED_AT, true ) );
						
						$status_class = '';
						$can_delete = false;
						
						if ( $used ) {
							$u = get_userdata( intval( $used_by ) );
							$used_by_text = $u ? esc_html( $u->user_login ) . ' (' . esc_html( $u->user_email ) . ')' : 'User ID: ' . intval( $used_by );
							$used_at_text = $used_at ? esc_html( $this->format_date( $used_at ) ) : '';
							
							// Determine status based on user state
							if ( $u ) {
								$exp = intval( get_user_meta( $u->ID, self::META_USER_EXPIRY, true ) );
								if ( in_array( 'expired', $u->roles ) || ! in_array( 'subscriber', $u->roles ) ) {
									$used_label = '<span style="color:red;font-weight:bold;">Revoked</span>';
									$status_class = 'expired';
									$can_delete = true;
								} elseif ( $exp && $exp <= $now ) {
									$used_label = '<span style="color:gray;font-weight:bold;">Expired</span>';
									$status_class = 'expired';
									$can_delete = true;
								} else {
									$used_label = '<span style="color:green;font-weight:bold;">Active</span>';
									$status_class = 'active';
								}
							} else {
								$used_label = '<span style="color:gray;font-weight:bold;">Expired</span>';
								$status_class = 'expired';
								$can_delete = true;
							}
						} else {
							$used_label = '<span style="color:orange;">Available</span>';
							$used_by_text = '-';
							$used_at_text = '-';
							$status_class = 'available';
							$can_delete = true;
						}
						
						$delete_btn = $can_delete ? '<button class="button iw-delete-code" data-code-id="' . intval( $inv->ID ) . '">Delete</button>' : '-';
						
						echo '<tr class="iw-code-row ' . esc_attr( $status_class ) . '" data-status="' . esc_attr( $status_class ) . '">';
						echo '<td>' . esc_html( $code ) . '</td>';
						echo '<td>' . esc_html( $days_text ) . '</td>';
						echo '<td>' . $used_label . '</td>';
						echo '<td>' . $used_by_text . '</td>';
						echo '<td>' . $used_at_text . '</td>';
						echo '<td>' . $delete_btn . '</td>';
						echo '</tr>';
					}
				}
				?>
				</tbody>
			</table>
			</div>

			<div class="iw-dashboard-section">
				<h2>Students</h2>
				<p>Slots left: <strong><?php echo is_numeric( $slots_left ) ? intval( $slots_left ) : esc_html( $slots_left ); ?></strong></p>
				<div class="iw-tabs">
					<button class="iw-tab-student active" data-student-tab="active">Active (<?php echo intval( $active_count ); ?>)</button>
					<button class="iw-tab-student" data-student-tab="inactive">Inactive (<?php echo intval( $inactive_count ); ?>)</button>
				</div>

				<!-- Active Students Section -->
				<div id="iw-active-students" class="iw-student-section active">
					<div class="iw-search-box">
						<input type="text" id="iw-active-search" placeholder="Search by name or email..." />
					</div>
					<table class="widefat" id="iw-active-students-table">
						<thead><tr><th>Name</th><th>Email</th><th>Last Login</th><th>Expires</th><th>Extended Access</th><th>Action</th></tr></thead>
						<tbody>
						<?php
						if ( empty( $all_students ) ) {
							echo '<tr><td colspan="6">No active students found.</td></tr>';
						} else {
							foreach ( $all_students as $s ) {
								$exp = intval( get_user_meta( $s->ID, self::META_USER_EXPIRY, true ) );
								$exp_date_value = $exp ? date( 'Y-m-d', $exp ) : '';
								$exp_text = $exp ? $this->format_date( $exp ) : 'No expiry';
								$student_id = intval( $s->ID );
								$first_name = esc_html( get_user_meta( $s->ID, 'first_name', true ) );
								$last_name = esc_html( get_user_meta( $s->ID, 'last_name', true ) );
								$email = esc_html( $s->user_email );
								$full_name = trim( $first_name . ' ' . $last_name );
								if ( empty( $full_name ) ) {
									$full_name = $email;
								}
								
								// Get last login
								$last_login = intval( get_user_meta( $s->ID, self::META_LAST_LOGIN, true ) );
								$last_login_text = $last_login ? $this->format_date( $last_login ) : 'Never';
								
								echo '<tr id="iw-student-' . $student_id . '" data-firstname="' . esc_attr( strtolower( $first_name ) ) . '" data-lastname="' . esc_attr( strtolower( $last_name ) ) . '" data-email="' . esc_attr( strtolower( $email ) ) . '">';
								echo '<td>' . ( $full_name !== $email ? $full_name : '—' ) . '</td>';
								echo '<td>' . $email . '</td>';
								echo '<td>' . esc_html( $last_login_text ) . '</td>';
								echo '<td><span class="iw-expiry-display">' . esc_html( $exp_text ) . '</span></td>';
								echo '<td>';
								echo '<input type="date" class="iw-expiry-input" id="iw-expiry-input-' . $student_id . '" data-student="' . $student_id . '" value="' . esc_attr( $exp_date_value ) . '" aria-label="Expiry date for ' . esc_attr( $full_name ) . '" />';
								echo '<button class="button iw-update-expiry" data-student="' . $student_id . '" aria-label="Update expiry for ' . esc_attr( $full_name ) . '">Update</button>';
								echo '</td>';
								echo '<td><button class="button iw-revoke" data-student="' . $student_id . '">Revoke</button></td>';
								echo '</tr>';
							}
						}
						?>
						</tbody>
					</table>
				</div>

				<!-- Inactive Students Section -->
				<div id="iw-inactive-students" class="iw-student-section">
					<div class="iw-search-box">
						<input type="text" id="iw-inactive-search" placeholder="Search by name or email..." />
					</div>
					<table class="widefat" id="iw-inactive-students-table">
						<thead><tr><th>Name</th><th>Email</th><th>Last Login</th><th>Last Expiry</th><th>How many additional days?</th></tr></thead>
						<tbody>
						<?php
						if ( empty( $inactive_students ) ) {
							echo '<tr><td colspan="5">No inactive students found.</td></tr>';
						} else {
							foreach ( $inactive_students as $s ) {
								$exp = intval( get_user_meta( $s->ID, self::META_USER_EXPIRY, true ) );
								$exp_text = $exp ? $this->format_date( $exp ) : 'No expiry set';
								$student_id = intval( $s->ID );
								$first_name = esc_html( get_user_meta( $s->ID, 'first_name', true ) );
								$last_name = esc_html( get_user_meta( $s->ID, 'last_name', true ) );
								$email = esc_html( $s->user_email );
								$full_name = trim( $first_name . ' ' . $last_name );
								if ( empty( $full_name ) ) {
									$full_name = $email;
								}
								
								// Get last login
								$last_login = intval( get_user_meta( $s->ID, self::META_LAST_LOGIN, true ) );
								$last_login_text = $last_login ? $this->format_date( $last_login ) : 'Never';
								
								echo '<tr id="iw-inactive-student-' . $student_id . '" data-firstname="' . esc_attr( strtolower( $first_name ) ) . '" data-lastname="' . esc_attr( strtolower( $last_name ) ) . '" data-email="' . esc_attr( strtolower( $email ) ) . '">';
								echo '<td>' . ( $full_name !== $email ? $full_name : '—' ) . '</td>';
								echo '<td>' . $email . '</td>';
								echo '<td>' . esc_html( $last_login_text ) . '</td>';
								echo '<td>' . esc_html( $exp_text ) . '</td>';
								echo '<td>';
								echo '<input type="number" class="iw-days-input" id="iw-days-input-' . $student_id . '" data-student="' . $student_id . '" value="' . intval( self::DEFAULT_REENROL_DAYS ) . '" min="1" placeholder="Days" aria-label="Days for ' . esc_attr( $full_name ) . '" />';
								echo '<button class="button iw-reenrol" data-student="' . $student_id . '" aria-label="Re-enrol ' . esc_attr( $full_name ) . '">Re-enrol</button>';
								echo '</td>';
								echo '</tr>';
							}
						}
						?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<script>
		(function(){
			// Tab functionality
			const tabs = document.querySelectorAll('.iw-tab');
			const rows = document.querySelectorAll('.iw-code-row');
			
			function showTab(tab) {
				tabs.forEach(t => t.classList.remove('active'));
				tab.classList.add('active');
				
				const filter = tab.getAttribute('data-tab');
				rows.forEach(row => {
					if (filter === 'all') {
						row.classList.add('show');
					} else {
						const status = row.getAttribute('data-status');
						if (status === filter) {
							row.classList.add('show');
						} else {
							row.classList.remove('show');
						}
					}
				});
			}
			
			tabs.forEach(tab => {
				tab.addEventListener('click', function() {
					showTab(this);
				});
			});
			
			// Show all by default
			showTab(document.querySelector('.iw-tab.active'));

			// Create codes form
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

			// Create user manually form
			const userForm = document.getElementById('iw-create-user-form');
			userForm.addEventListener('submit', function(e){
				e.preventDefault();
				const data = new FormData(userForm);
				data.append('action','<?php echo self::AJAX_CREATE_USER; ?>');
				
				// Disable submit button during processing
				const submitBtn = userForm.querySelector('button[type="submit"]');
				const originalText = submitBtn.textContent;
				submitBtn.disabled = true;
				submitBtn.textContent = 'Creating...';
				
				fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
					method:'POST',
					credentials:'same-origin',
					body:data
				}).then(r=>r.json()).then(d=>{
					if(d.success){
						const msg = 'User created successfully!\n\n' +
							'Username: ' + d.data.username + '\n' +
							'Email: ' + d.data.email + '\n' +
							'Expiry: ' + d.data.expiry + '\n\n' +
							'A welcome email with login credentials has been sent to the user.';
						alert(msg);
						location.reload();
					}else{
						alert('Error creating user: '+(d.data||d));
						submitBtn.disabled = false;
						submitBtn.textContent = originalText;
					}
				}).catch(err=>{
					alert('Network error. Please try again.');
					submitBtn.disabled = false;
					submitBtn.textContent = originalText;
				});
			});

			// Revoke student
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

			// Delete code
			document.querySelectorAll('.iw-delete-code').forEach(function(btn){
				btn.addEventListener('click', function(){
					if(!confirm('Delete this code? This action cannot be undone.')) return;
					const codeId = this.getAttribute('data-code-id');
					const data = new FormData();
					data.append('action','<?php echo self::AJAX_DELETE_CODE; ?>');
					data.append('code_id', codeId);
					data.append('iw_dash_nonce', '<?php echo $dash_nonce; ?>');
					fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
						method:'POST',
						credentials:'same-origin',
						body:data
					}).then(r=>r.json()).then(d=>{
						if(d.success){
							alert('Code deleted successfully.');
							location.reload();
						}else{
							alert('Error deleting code: ' + (d.data||d));
						}
					});
				});
			});

			// Update expiry date
			document.querySelectorAll('.iw-update-expiry').forEach(function(btn){
				btn.addEventListener('click', function(){
					const studentId = this.getAttribute('data-student');
					const input = document.querySelector('.iw-expiry-input[data-student="' + studentId + '"]');
					const newDate = input.value;
					
					if(!newDate) {
						alert('Please select a date');
						return;
					}
					
					if(!confirm('Update expiry date for this student?')) return;
					
					const data = new FormData();
					data.append('action','<?php echo esc_js( self::AJAX_UPDATE_EXPIRY ); ?>');
					data.append('student_id', studentId);
					data.append('expiry_date', newDate);
					data.append('iw_dash_nonce', '<?php echo esc_js( $dash_nonce ); ?>');
					fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
						method:'POST',
						credentials:'same-origin',
						body:data
					}).then(r=>r.json()).then(d=>{
						if(d.success){
							alert('Expiry date updated successfully.');
							location.reload();
						}else{
							alert('Error updating expiry: ' + (d.data||d));
						}
					});
				});
			});

			// Student tabs functionality
			const studentTabs = document.querySelectorAll('.iw-tab-student');
			const studentSections = document.querySelectorAll('.iw-student-section');
			
			studentTabs.forEach(tab => {
				tab.addEventListener('click', function() {
					// Remove active class from all tabs
					studentTabs.forEach(t => t.classList.remove('active'));
					// Add active to clicked tab
					this.classList.add('active');
					
					// Hide all sections
					studentSections.forEach(section => section.classList.remove('active'));
					
					// Show selected section
					const targetTab = this.getAttribute('data-student-tab');
					const targetSection = document.getElementById('iw-' + targetTab + '-students');
					if (targetSection) {
						targetSection.classList.add('active');
					}
				});
			});

			// Re-enrol student
			document.querySelectorAll('.iw-reenrol').forEach(function(btn){
				btn.addEventListener('click', function(){
					const studentId = this.getAttribute('data-student');
					const daysInput = document.getElementById('iw-days-input-' + studentId);
					const days = daysInput ? parseInt(daysInput.value) : 30;
					
					if(!days || days < 1) {
						alert('Please enter a valid number of days (minimum 1)');
						return;
					}
					
					if(!confirm('Re-enrol this student with ' + days + ' days of access?')) return;
					
					const data = new FormData();
					data.append('action','<?php echo esc_js( self::AJAX_REENROL ); ?>');
					data.append('student_id', studentId);
					data.append('days', days);
					data.append('iw_dash_nonce', '<?php echo esc_js( $dash_nonce ); ?>');
					fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
						method:'POST',
						credentials:'same-origin',
						body:data
					}).then(r=>r.json()).then(d=>{
						if(d.success){
							alert('Student re-enrolled successfully.');
							location.reload();
						}else{
							alert('Error re-enrolling student: ' + (d.data||d));
						}
					});
				});
			});

			// Search functionality helper
			function setupTableSearch(searchInputId, tableId) {
				const searchInput = document.getElementById(searchInputId);
				if (searchInput) {
					let debounceTimer;
					searchInput.addEventListener('input', function() {
						clearTimeout(debounceTimer);
						debounceTimer = setTimeout(function() {
							const searchTerm = searchInput.value.toLowerCase();
							const table = document.getElementById(tableId);
							const rows = table.querySelectorAll('tbody tr');
							
							rows.forEach(function(row) {
								const firstname = row.getAttribute('data-firstname') || '';
								const lastname = row.getAttribute('data-lastname') || '';
								const email = row.getAttribute('data-email') || '';
								
								if (firstname.includes(searchTerm) || lastname.includes(searchTerm) || email.includes(searchTerm)) {
									row.style.display = '';
								} else {
									row.style.display = 'none';
								}
							});
						}, 250);
					});
				}
			}

			// Setup search for both tables
			setupTableSearch('iw-active-search', 'iw-active-students-table');
			setupTableSearch('iw-inactive-search', 'iw-inactive-students-table');
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

		// set expiry on user - use days from invite or fallback to default
		$invite_days = intval( get_post_meta( $inv_id, self::META_INVITE_DAYS, true ) );
		if ( ! $invite_days && ! empty( $options['default_days'] ) ) {
			$invite_days = intval( $options['default_days'] );
		}
		$invite_expiry_ts = $invite_days > 0 ? time() + ( $invite_days * DAY_IN_SECONDS ) : 0;
		
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

		$redirect = $this->get_url_from_page_setting( $options['post_register_redirect'] ?? 0, home_url( '/' ) );
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

	/* Daily cron: notify and expire users */
	public function daily_expire_check() {
		$options = get_option( self::OPTION_KEY, [] );
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

		// expire users - remove LearnDash enrollments and set role to none
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
				
				// Get user data and validate user exists
				$user = get_userdata( $uid );
				if ( ! $user ) {
					continue; // Skip if user was deleted
				}
				
				// Remove LearnDash enrollments
				$this->remove_user_enrollments( $uid );
				
				// Remove all roles from user (sets to no role)
				$user->set_role( '' );
				
				// Notify partner admin
				if ( $manager_id ) {
					$this->notify_partner_user_expired( $manager_id, $uid, $exp, $user );
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

	/* Send welcome email to manually created user with login credentials */
	private function send_welcome_email( $user_id, $username, $password, $email, $first_name, $expiry_ts ) {
		$to = $email;
		$subject = 'Welcome to IELTS Student Management - Your Account Details';
		$options = get_option( self::OPTION_KEY, [] );
		$login_url = $this->get_url_from_page_setting( $options['login_page_url'] ?? 0, wp_login_url() );
		
		$message = "Hello {$first_name},\n\n";
		$message .= "Your account has been created successfully!\n\n";
		$message .= "Your login details:\n";
		$message .= "Username: {$username}\n";
		$message .= "Email: {$email}\n";
		$message .= "Temporary Password: {$password}\n\n";
		$message .= "Login URL: {$login_url}\n\n";
		$message .= "Your access expires on: " . $this->format_date( $expiry_ts ) . "\n\n";
		$message .= "You have been enrolled in all available courses. Please log in to get started.\n\n";
		$message .= "IMPORTANT SECURITY NOTICE:\n";
		$message .= "- This email contains your temporary password. Please delete this email after changing your password.\n";
		$message .= "- We strongly recommend changing your password immediately after your first login.\n";
		$message .= "- Keep your password secure and do not share it with anyone.\n\n";
		$message .= "Regards,\nImpact Websites";
		
		wp_mail( $to, $subject, $message );
	}

	/* Notify partner when user is manually created */
	private function notify_partner_manual_user_created( $partner_id, $user_id, $username, $email, $expiry_ts ) {
		$partner = get_userdata( $partner_id );
		$user = get_userdata( $user_id );
		if ( ! $partner || ! $user ) {
			return;
		}
		$to = $partner->user_email;
		if ( empty( $to ) ) {
			return;
		}
		$subject = sprintf( 'New user created: %s', $username );
		$expiry_text = $this->format_date( $expiry_ts );
		$message = "Hello " . $partner->display_name . ",\n\n";
		$message .= "You have successfully created a new user account.\n\n";
		$message .= "User details:\n";
		$message .= "Username: {$username}\n";
		$message .= "Email: {$email}\n";
		$message .= "Expires: {$expiry_text}\n\n";
		$message .= "The user has been enrolled in all courses and will receive a welcome email with their login credentials.\n\n";
		$message .= "Regards,\nImpact Websites";
		wp_mail( $to, $subject, $message );
	}

	/* Shortcode: show logged-in user's expiry and account management */
	public function shortcode_my_expiry( $atts = [] ) {
		if ( ! is_user_logged_in() ) {
			return '<p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your account.</p>';
		}
		
		$template_file = IW_PLUGIN_DIR . 'templates/my-account.php';
		if ( ! file_exists( $template_file ) ) {
			return '<p>Template file not found.</p>';
		}
		
		ob_start();
		include $template_file;
		return ob_get_clean();
	}

	/* Shortcode: login page (styled table) with lost password link */
	public function shortcode_login() {
		if ( is_user_logged_in() ) {
			// If user is already logged in, redirect them to the intended destination
			$options = get_option( self::OPTION_KEY, [] );
			$default_redirect = $this->get_url_from_page_setting( $options['post_register_redirect'] ?? 0, home_url( '/' ) );
			$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : $default_redirect;
			// Validate redirect to prevent open redirect vulnerabilities
			$redirect_to = wp_validate_redirect( $redirect_to, home_url( '/' ) );
			wp_safe_redirect( $redirect_to );
			exit;
		}

		$options = get_option( self::OPTION_KEY, [] );
		$default_redirect = $this->get_url_from_page_setting( $options['post_register_redirect'] ?? 0, home_url( '/' ) );
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

		<?php
		// Display error messages if login failed
		$login_error = '';
		if ( isset( $_GET['login'] ) && $_GET['login'] === 'failed' ) {
			$error_code = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
			$login_error = '<div style="background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;padding:12px;margin:0 auto 15px;max-width:720px;border-radius:4px;text-align:center;font-weight:bold;">Login failed. Please check your username and password.</div>';
		}
		?>

		<?php echo $login_error; ?>

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

	/* Handle failed login attempts - redirect to custom login page with error */
	public function handle_login_failed( $username, $error ) {
		$options = get_option( self::OPTION_KEY, [] );
		$login_url = $this->get_url_from_page_setting( $options['login_page_url'] ?? 0, '' );
		
		if ( empty( $login_url ) ) {
			return; // No custom login page configured, use default behavior
		}

		$referrer = wp_get_referer();
		
		// Only redirect if coming from our custom login page
		if ( ! empty( $referrer ) && strpos( $referrer, $login_url ) !== false ) {
			$redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';
			$login_url = add_query_arg( 'login', 'failed', $login_url );
			if ( ! empty( $redirect_to ) ) {
				$login_url = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $login_url );
			}
			wp_safe_redirect( $login_url );
			exit;
		}
	}

	/* Handle authentication errors - keep errors within plugin */
	public function handle_login_errors( $user, $username, $password ) {
		$options = get_option( self::OPTION_KEY, [] );
		$login_url = $this->get_url_from_page_setting( $options['login_page_url'] ?? 0, '' );
		
		if ( empty( $login_url ) ) {
			return $user; // No custom login page configured, use default behavior
		}

		// If there's an error, redirect to custom login page
		if ( is_wp_error( $user ) && isset( $_POST['wp-submit'] ) ) {
			$referrer = wp_get_referer();
			
			// Only redirect if coming from our custom login page
			if ( ! empty( $referrer ) && strpos( $referrer, $login_url ) !== false ) {
				$redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';
				$error_code = $user->get_error_code();
				$login_url = add_query_arg( 'login', 'failed', $login_url );
				$login_url = add_query_arg( 'error', urlencode( $error_code ), $login_url );
				if ( ! empty( $redirect_to ) ) {
					$login_url = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $login_url );
				}
				wp_safe_redirect( $login_url );
				exit;
			}
		}

		return $user;
	}

	/* Redirect users after login based on their role */
	public function partner_admin_login_redirect( $redirect_to, $request, $user ) {
		// Check if user is valid WP_User object
		if ( ! is_wp_error( $user ) && isset( $user->roles ) && is_array( $user->roles ) ) {
			$options = get_option( self::OPTION_KEY, [] );
			
			// Check for partner admin role
			if ( in_array( self::PARTNER_ROLE, $user->roles ) ) {
				$partner_redirect = $this->get_url_from_page_setting( $options['post_login_partner_redirect'] ?? 0, home_url( '/partner-dashboard/' ) );
				return $partner_redirect;
			}
			
			// Check for subscriber role
			if ( in_array( 'subscriber', $user->roles ) ) {
				$subscriber_redirect = $this->get_url_from_page_setting( $options['post_login_subscriber_redirect'] ?? 0, '' );
				if ( ! empty( $subscriber_redirect ) ) {
					return $subscriber_redirect;
				}
			}
			
			// Check for users with no role (empty roles array)
			if ( empty( $user->roles ) ) {
				$norole_redirect = $this->get_url_from_page_setting( $options['post_login_norole_redirect'] ?? 0, home_url( '/extend-my-membership/' ) );
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
		$login_url = $this->get_url_from_page_setting( $options['login_page_url'] ?? 0, '' );
		$registration_url = $this->get_url_from_page_setting( $options['registration_page_url'] ?? 0, '' );

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

	/**
	 * Track last login time for users
	 * 
	 * Hooked to 'wp_login' action to record the timestamp when a user logs in.
	 * This information is displayed in the partner dashboard to help partners
	 * track student engagement.
	 * 
	 * @param string  $user_login Username (required by wp_login hook signature, not used)
	 * @param WP_User $user       User object
	 * @return void
	 */
	public function track_last_login( $user_login, $user ) {
		update_user_meta( $user->ID, self::META_LAST_LOGIN, time() );
	}

	/**
	 * Show expiry date field in user profile (admin backend)
	 */
	public function show_expiry_field( $user ) {
		// Only show for users with manage_options capability or the manage_partner_invites capability
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( self::CAP_MANAGE ) ) {
			return;
		}

		$expiry_ts = intval( get_user_meta( $user->ID, self::META_USER_EXPIRY, true ) );
		$expiry_date = $expiry_ts ? date( 'Y-m-d', $expiry_ts ) : '';
		$expiry_display = $expiry_ts ? $this->format_date( $expiry_ts ) : 'No expiry set';
		?>
		<h3>Membership Expiry</h3>
		<table class="form-table">
			<tr>
				<th><label for="iw_user_expiry">Expiry Date</label></th>
				<td>
					<input type="date" name="iw_user_expiry" id="iw_user_expiry" value="<?php echo esc_attr( $expiry_date ); ?>" class="regular-text" />
					<p class="description">Current expiry: <?php echo esc_html( $expiry_display ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save expiry date field from user profile (admin backend)
	 */
	public function save_expiry_field( $user_id ) {
		// Check user capabilities
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}
		
		// Only allow users with manage_options or manage_partner_invites to edit
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( self::CAP_MANAGE ) ) {
			return false;
		}

		if ( isset( $_POST['iw_user_expiry'] ) ) {
			$expiry_date = sanitize_text_field( wp_unslash( $_POST['iw_user_expiry'] ) );
			
			if ( ! empty( $expiry_date ) ) {
				// Convert to timestamp (end of day)
				$timestamp = strtotime( $expiry_date . ' 23:59:59' );
				if ( $timestamp ) {
					update_user_meta( $user_id, self::META_USER_EXPIRY, $timestamp );
					// Clear any expiry notice flag
					delete_user_meta( $user_id, self::META_EXPIRY_NOTICE_SENT );
				}
			} else {
				// If empty, remove the expiry
				delete_user_meta( $user_id, self::META_USER_EXPIRY );
			}
		}
	}
}

new Impact_Websites_Student_Management();
