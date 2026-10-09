<?php

/**
 * Tests for admin settings helpers.
 *
 * @group admin
 * @group settings
 */
class BBP_Tests_Admin_Settings extends BBP_UnitTestCase {

	private $saved_options = array();
	private $bbp_options;

	public function setUp(): void {
		parent::setUp();

		require_once BBP_PLUGIN_DIR . 'includes/admin/settings.php';

		$this->bbp_options = bbpress()->options;
		bbpress()->options = array();
	}

	public function tearDown(): void {
		foreach ( $this->saved_options as $option => $saved ) {
			if ( $saved['exists'] ) {
				update_option( $option, $saved['value'] );
			} else {
				delete_option( $option );
			}
		}

		bbpress()->options = $this->bbp_options;

		parent::tearDown();
	}

	private function set_option( $option, $value ) {
		if ( ! isset( $this->saved_options[ $option ] ) ) {
			$missing = new stdClass();
			$current = get_option( $option, $missing );

			$this->saved_options[ $option ] = array(
				'exists' => ( $missing !== $current ),
				'value'  => $current,
			);
		}

		delete_option( $option );
		add_option( $option, $value );
	}

	/**
	 * @covers ::bbp_admin_get_settings_sections
	 */
	public function test_settings_sections_have_pages_callbacks_and_filters() {
		$filter = function ( $sections ) {
			$sections['bbp_test_section'] = array(
				'title'    => 'Test',
				'callback' => '__return_null',
				'page'     => 'test',
			);
			return $sections;
		};

		add_filter( 'bbp_admin_get_settings_sections', $filter );

		try {
			$sections = bbp_admin_get_settings_sections();
			$this->assertArrayHasKey( 'bbp_settings_status', $sections );
			$this->assertArrayHasKey( 'bbp_settings_users', $sections );
			$this->assertArrayHasKey( 'bbp_converter_connection', $sections );
			$this->assertSame( 'discussion', $sections['bbp_settings_status']['page'] );
			$this->assertSame( 'bbp_admin_setting_callback_status_section', $sections['bbp_settings_status']['callback'] );
			$this->assertSame( 'test', $sections['bbp_test_section']['page'] );
		} finally {
			remove_filter( 'bbp_admin_get_settings_sections', $filter );
		}
	}

	/**
	 * @covers ::bbp_admin_get_settings_fields
	 */
	public function test_settings_fields_define_callbacks_sanitizers_and_labels() {
		$fields = bbp_admin_get_settings_fields();

		$this->assertSame( 'bbp_admin_setting_callback_forums_status', $fields['bbp_settings_status']['_bbp_forums_status']['callback'] );
		$this->assertSame( 'bbp_admin_sanitize_forums_status', $fields['bbp_settings_status']['_bbp_forums_status']['sanitize_callback'] );
		$this->assertSame( array( 'label_for' => '_bbp_forums_status' ), $fields['bbp_settings_status']['_bbp_forums_status']['args'] );
		$this->assertSame( 'intval', $fields['bbp_converter_options']['_bbp_converter_clean']['sanitize_callback'] );
	}

	/**
	 * @covers ::bbp_admin_get_settings_fields_for_section
	 */
	public function test_settings_fields_for_section_handles_empty_known_and_unknown_sections() {
		$observed = null;
		$filter   = function ( $fields, $section_id ) use ( &$observed ) {
			$observed = array( $fields, $section_id );
			return $fields;
		};

		$this->assertFalse( bbp_admin_get_settings_fields_for_section() );
		add_filter( 'bbp_admin_get_settings_fields_for_section', $filter, 10, 2 );

		try {
			$fields = bbp_admin_get_settings_fields_for_section( 'bbp_settings_status' );
			$this->assertArrayHasKey( '_bbp_forums_status', $fields );
			$this->assertSame( 'bbp_settings_status', $observed[1] );

			$this->assertSame( array(), bbp_admin_get_settings_fields_for_section( 'bbp_missing_section' ) );
			$this->assertSame( array( array(), 'bbp_missing_section' ), $observed );
		} finally {
			remove_filter( 'bbp_admin_get_settings_fields_for_section', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_admin_settings
	 */
	public function test_admin_settings_outputs_the_settings_form() {
		$had_request_uri = array_key_exists( 'REQUEST_URI', $_SERVER );
		$old_request_uri = $had_request_uri ? $_SERVER['REQUEST_URI'] : null;
		$had_sections    = isset( $GLOBALS['wp_settings_sections'] );
		$old_sections    = $had_sections ? $GLOBALS['wp_settings_sections'] : null;
		$had_fields      = isset( $GLOBALS['wp_settings_fields'] );
		$old_fields      = $had_fields ? $GLOBALS['wp_settings_fields'] : null;
		$section         = function () {
			echo '<p id="bbp-test-section">Section output</p>';
		};
		$field           = function () {
			echo '<input id="bbp-test-field" />';
		};

		$_SERVER['REQUEST_URI'] = '/wp-admin/options-general.php?page=bbpress';
		add_settings_section( 'bbp_test_section', 'Test Section', $section, 'bbpress' );
		add_settings_field( 'bbp_test_field', 'Test Field', $field, 'bbpress', 'bbp_test_section' );

		try {
			ob_start();
			bbp_admin_settings();
			$output = ob_get_clean();
		} finally {
			if ( $had_request_uri ) {
				$_SERVER['REQUEST_URI'] = $old_request_uri;
			} else {
				unset( $_SERVER['REQUEST_URI'] );
			}

			if ( $had_sections ) {
				$GLOBALS['wp_settings_sections'] = $old_sections;
			} else {
				unset( $GLOBALS['wp_settings_sections'] );
			}

			if ( $had_fields ) {
				$GLOBALS['wp_settings_fields'] = $old_fields;
			} else {
				unset( $GLOBALS['wp_settings_fields'] );
			}
		}

		$this->assertStringContainsString( '<h1 class="wp-heading-inline">Forums Settings</h1>', $output );
		$this->assertStringContainsString( '<form action="options.php" method="post">', $output );
		$this->assertStringContainsString( "name='option_page' value='bbpress'", $output );
		$this->assertStringContainsString( '<p id="bbp-test-section">Section output</p>', $output );
		$this->assertStringContainsString( '<input id="bbp-test-field" />', $output );
		$this->assertStringContainsString( 'value="Save Changes"', $output );
		$this->assertSame( 1, preg_match( '/name="_wpnonce" value="([^"]+)"/', $output, $matches ) );
		$this->assertNotFalse( wp_verify_nonce( $matches[1], 'bbpress-options' ) );
	}

	/**
	 * @covers ::bbp_admin_settings_help
	 */
	public function test_admin_settings_help_adds_tabs_and_sidebar() {
		$old_screen = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;

		try {
			$GLOBALS['current_screen'] = null;
			$this->assertNull( bbp_admin_settings_help() );

			$GLOBALS['current_screen'] = WP_Screen::get( 'settings_page_bbpress' );
			bbp_admin_settings_help();
			$tabs = get_current_screen()->get_help_tabs();

			$this->assertSame( array( 'overview', 'forum_status', 'main_settings', 'theme_packages', 'per_page', 'slugs' ), array_keys( $tabs ) );
			$this->assertStringContainsString( 'Frozen stops new topics and replies', $tabs['forum_status']['content'] );
			$this->assertStringContainsString( 'bbPress Documentation', get_current_screen()->get_help_sidebar() );
		} finally {
			$GLOBALS['current_screen'] = $old_screen;
		}
	}

	/**
	 * @covers ::bbp_maybe_admin_setting_disabled
	 */
	public function test_admin_setting_is_disabled_only_when_globally_overridden() {
		ob_start();
		bbp_maybe_admin_setting_disabled( '_bbp_test_option' );
		$this->assertSame( '', ob_get_clean() );

		bbpress()->options['_bbp_test_option'] = true;
		ob_start();
		bbp_maybe_admin_setting_disabled( '_bbp_test_option' );
		$this->assertSame( " disabled='disabled'", ob_get_clean() );
	}

	/**
	 * @covers ::bbp_form_option
	 * @covers ::bbp_get_form_option
	 */
	public function test_form_option_escapes_filters_falls_back_and_preserves_zero() {
		$this->set_option( '_bbp_test_option', 'value"quoted' );
		$this->set_option( '_bbp_test_zero', 0 );
		$this->set_option( '_bbp_test_empty', '' );

		$this->assertSame( 'value&quot;quoted', bbp_get_form_option( '_bbp_test_option' ) );
		$this->assertSame( '0', bbp_get_form_option( '_bbp_test_zero', 'fallback' ) );
		$this->assertSame( 'fallback', bbp_get_form_option( '_bbp_test_empty', 'fallback' ) );
		$this->assertSame( 'missing-default', bbp_get_form_option( '_bbp_missing_form_option', 'missing-default' ) );

		$slug_filter = function ( $value ) {
			return '<filtered-"slug">';
		};
		add_filter( 'editable_slug', $slug_filter );

		try {
			$this->assertSame( '&lt;filtered-&quot;slug&quot;&gt;', bbp_get_form_option( '_bbp_test_option', '', true ) );
		} finally {
			remove_filter( 'editable_slug', $slug_filter );
		}

		$observed = null;
		$filter   = function ( $value, $option, $default, $is_slug ) use ( &$observed ) {
			$observed = array( $value, $option, $default, $is_slug );
			return 'filtered-return';
		};
		add_filter( 'bbp_get_form_option', $filter, 10, 4 );

		try {
			$this->assertSame( 'filtered-return', bbp_get_form_option( '_bbp_test_option', 'default', false ) );
			$this->assertSame( array( 'value&quot;quoted', '_bbp_test_option', 'default', false ), $observed );
		} finally {
			remove_filter( 'bbp_get_form_option', $filter, 10 );
		}

		ob_start();
		bbp_form_option( '_bbp_test_option' );
		$this->assertSame( 'value&quot;quoted', ob_get_clean() );
	}

	/**
	 * @covers ::bbp_form_slug_conflict_check
	 */
	public function test_slug_conflict_check_reports_other_matching_slugs() {
		$this->set_option( '_bbp_forum_slug', 'shared-slug' );
		$this->set_option( '_bbp_topic_slug', 'shared-slug' );

		ob_start();
		bbp_form_slug_conflict_check( '_bbp_forum_slug', 'forum' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Possible bbPress conflict', $output );
		$this->assertStringContainsString( '<strong>Topic slug</strong>', $output );
		$this->assertStringNotContainsString( '<strong>Forum slug</strong>', $output );
	}
}
