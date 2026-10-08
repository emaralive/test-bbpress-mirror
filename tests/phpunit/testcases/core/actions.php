<?php

/**
 * Tests for core action registration.
 *
 * @group core
 * @group actions
 */
class BBP_Tests_Core_Actions extends BBP_UnitTestCase {

	private function get_accepted_args( $hook, $callback, $priority ) {
		global $wp_filter;

		$callback_id = _wp_filter_build_unique_id( $hook, $callback, $priority );

		return $wp_filter[ $hook ]->callbacks[ $priority ][ $callback_id ]['accepted_args'];
	}

	/**
	 * Verify the primary WordPress-to-bbPress action bridges.
	 */
	public function test_wordpress_actions_bridge_to_bbp_sub_actions_at_expected_priorities() {
		$actions = array(
			array( 'plugins_loaded',         'bbp_loaded',                 10, 1 ),
			array( 'init',                   'bbp_init',                    0, 1 ),
			array( 'parse_query',            'bbp_parse_query',             2, 1 ),
			array( 'generate_rewrite_rules', 'bbp_generate_rewrite_rules', 10, 1 ),
			array( 'after_setup_theme',      'bbp_after_setup_theme',      10, 1 ),
			array( 'setup_theme',            'bbp_setup_theme',            10, 1 ),
			array( 'set_current_user',       'bbp_setup_current_user',     10, 1 ),
			array( 'profile_update',         'bbp_profile_update',         10, 2 ),
			array( 'user_register',          'bbp_user_register',          10, 1 ),
			array( 'login_form_login',       'bbp_login_form_login',       10, 1 ),
			array( 'template_redirect',      'bbp_template_redirect',       8, 1 ),
			array( 'widgets_init',           'bbp_widgets_init',           10, 1 ),
			array( 'wp_roles_init',          'bbp_roles_init',             10, 1 ),
			array( 'wp_enqueue_scripts',     'bbp_enqueue_scripts',        10, 1 ),
			array( 'wp_head',                'bbp_head',                   10, 1 ),
			array( 'wp_footer',              'bbp_footer',                 10, 1 ),
			array( 'rest_api_init',          'bbp_rest_api_init',          10, 1 ),
			array( 'xmlrpc_call',            'bbp_xmlrpc_call',            10, 3 ),
			array( 'transition_post_status', 'bbp_transition_post_status', 10, 3 ),
			array( 'post_updated',           'bbp_post_updated',           10, 3 ),
		);

		foreach ( $actions as $action ) {
			$this->assertSame( $action[2], has_action( $action[0], $action[1] ), $action[0] . ' should call ' . $action[1] );
			$this->assertSame( $action[3], $this->get_accepted_args( $action[0], $action[1], $action[2] ), $action[1] . ' should receive the expected arguments' );
		}
	}

	/**
	 * Verify the documented bbPress loader order.
	 */
	public function test_bbp_loader_actions_run_at_expected_priorities() {
		$actions = array(
			array( 'bbp_loaded', 'bbp_constants',                  2 ),
			array( 'bbp_loaded', 'bbp_boot_strap_globals',         4 ),
			array( 'bbp_loaded', 'bbp_includes',                   6 ),
			array( 'bbp_loaded', 'bbp_setup_globals',              8 ),
			array( 'bbp_loaded', 'bbp_setup_option_filters',      10 ),
			array( 'bbp_loaded', 'bbp_setup_user_option_filters', 12 ),
			array( 'bbp_loaded', 'bbp_pre_load_options',          14 ),
			array( 'bbp_init',   'bbp_load_textdomain',            0 ),
			array( 'bbp_init',   'bbp_reply_content_autoembed',    8 ),
			array( 'bbp_init',   'bbp_topic_content_autoembed',    8 ),
			array( 'bbp_init',   'bbp_register',                  10 ),
			array( 'bbp_init',   'bbp_add_rewrite_tags',          20 ),
			array( 'bbp_init',   'bbp_add_rewrite_rules',         30 ),
			array( 'bbp_init',   'bbp_add_permastructs',          40 ),
			array( 'bbp_init',   'bbp_setup_engagements',         50 ),
			array( 'bbp_init',   'bbp_ready',                    999 ),
		);

		foreach ( $actions as $action ) {
			$this->assertSame( $action[2], has_action( $action[0], $action[1] ), $action[0] . ' should call ' . $action[1] );
		}
	}

	/**
	 * Verify the object-registration order within bbp_register.
	 */
	public function test_bbp_register_actions_run_at_expected_priorities() {
		$actions = array(
			array( 'bbp_register_post_types',     2 ),
			array( 'bbp_register_post_statuses',  4 ),
			array( 'bbp_register_taxonomies',     6 ),
			array( 'bbp_register_views',          8 ),
			array( 'bbp_register_shortcodes',    10 ),
			array( 'bbp_register_blocks',        10 ),
			array( 'bbp_register_meta',          12 ),
		);

		foreach ( $actions as $action ) {
			$this->assertSame( $action[1], has_action( 'bbp_register', $action[0] ), 'bbp_register should call ' . $action[0] );
		}
	}

	/**
	 * Verify secondary integration bridges.
	 */
	public function test_secondary_bbp_action_bridges_run_at_expected_priorities() {
		$actions = array(
			array( 'bbp_rest_api_init',      'bbp_register_rest_attachment_controller', 5,  1 ),
			array( 'bbp_xmlrpc_call',        'bbp_validate_xmlrpc_post',               10, 2 ),
			array( 'bbp_setup_current_user', 'bbp_set_current_user_default_role',      10, 1 ),
			array( 'bbp_roles_init',         'bbp_add_forums_roles',                    8,  1 ),
			array( 'bbp_setup_theme',        'bbp_register_theme_packages',             2,  1 ),
			array( 'bbp_ready',              'bbp_setup_akismet',                       2,  1 ),
			array( 'bbp_after_setup_theme',  'bbp_load_theme_functions',               10, 1 ),
			array( 'bp_init',                'bbp_setup_buddypress',                    0,  1 ),
		);

		foreach ( $actions as $action ) {
			$this->assertSame( $action[2], has_action( $action[0], $action[1] ), $action[0] . ' should call ' . $action[1] );
			$this->assertSame( $action[3], $this->get_accepted_args( $action[0], $action[1], $action[2] ), $action[1] . ' should receive the expected arguments' );
		}
	}

	/**
	 * Verify access enforcement runs before request handlers.
	 */
	public function test_template_redirect_enforces_forum_access_before_request_handlers() {
		$this->assertSame( 1, has_action( 'bbp_template_redirect', 'bbp_forum_enforce_blocked' ) );
		$this->assertSame( 1, has_action( 'bbp_template_redirect', 'bbp_forum_enforce_hidden' ) );
		$this->assertSame( 1, has_action( 'bbp_template_redirect', 'bbp_forum_enforce_private' ) );
		$this->assertSame( 10, has_action( 'bbp_template_redirect', 'bbp_post_request' ) );
		$this->assertSame( 10, has_action( 'bbp_template_redirect', 'bbp_get_request' ) );
	}
}
