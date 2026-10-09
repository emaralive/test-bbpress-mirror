<?php

/**
 * Tests for common admin functions.
 *
 * @group admin
 */
class BBP_Tests_Admin_Common extends BBP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}

		bbp_admin();
	}

	/**
	 * @covers ::bbp_admin_separator
	 */
	public function test_admin_separator_requires_a_visible_forum_menu() {
		$old_user      = get_current_user_id();
		$old_menu      = isset( $GLOBALS['menu'] ) ? $GLOBALS['menu'] : null;
		$old_separator = bbp_admin()->show_separator;
		$admin_id      = $this->factory->user->create( array( 'role' => 'administrator' ) );

		try {
			$GLOBALS['menu']             = array();
			bbp_admin()->show_separator = false;
			$this->set_current_user( 0 );
			bbp_admin_separator();
			$this->assertFalse( bbp_admin()->show_separator );
			$this->assertSame( array(), $GLOBALS['menu'] );

			bbp_set_user_role( $admin_id, bbp_get_keymaster_role() );
			$this->set_current_user( $admin_id );
			bbp_admin_separator();
			$this->assertTrue( bbp_admin()->show_separator );
			$this->assertSame(
				array( '', 'read', 'separator-bbpress', '', 'wp-menu-separator bbpress' ),
				end( $GLOBALS['menu'] )
			);
		} finally {
			bbp_admin()->show_separator = $old_separator;
			$GLOBALS['menu']             = $old_menu;
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_admin_custom_menu_order
	 */
	public function test_admin_custom_menu_order_follows_separator_state() {
		$old_separator = bbp_admin()->show_separator;

		try {
			bbp_admin()->show_separator = false;
			$this->assertSame( 'unchanged', bbp_admin_custom_menu_order( 'unchanged' ) );

			bbp_admin()->show_separator = true;
			$this->assertTrue( bbp_admin_custom_menu_order( false ) );
		} finally {
			bbp_admin()->show_separator = $old_separator;
		}
	}

	/**
	 * @covers ::bbp_admin_menu_order
	 */
	public function test_admin_menu_order_moves_forum_menus_before_appearance() {
		$old_separator = bbp_admin()->show_separator;
		$forum_menu    = 'edit.php?post_type=' . bbp_get_forum_post_type();
		$topic_menu    = 'edit.php?post_type=' . bbp_get_topic_post_type();
		$reply_menu    = 'edit.php?post_type=' . bbp_get_reply_post_type();
		$menu_order    = array(
			'index.php',
			'separator1',
			$forum_menu,
			$topic_menu,
			$reply_menu,
			'separator2',
			'themes.php',
			'separator-bbpress',
		);

		try {
			bbp_admin()->show_separator = false;
			$this->assertSame( $menu_order, bbp_admin_menu_order( $menu_order ) );
			$this->assertSame( array(), bbp_admin_menu_order( array() ) );

			bbp_admin()->show_separator = true;
			$this->assertSame(
				array(
					'index.php',
					'separator1',
					'separator-bbpress',
					$forum_menu,
					$topic_menu,
					$reply_menu,
					'separator2',
					'themes.php',
				),
				bbp_admin_menu_order( $menu_order )
			);

			$this->assertSame(
				array( 'index.php', 'separator-bbpress', $topic_menu, 'separator2', 'themes.php' ),
				bbp_admin_menu_order( array( 'index.php', $topic_menu, 'separator2', 'themes.php', 'separator-bbpress' ) )
			);
		} finally {
			bbp_admin()->show_separator = $old_separator;
		}
	}

	/**
	 * @covers ::bbp_sanitize_slug
	 */
	public function test_sanitize_slug_normalizes_slashes_and_is_filterable() {
		$observed = array();
		$filter   = function ( $value, $slug ) use ( &$observed ) {
			$observed = array( $value, $slug );
			return 'prefix/' . $value;
		};

		try {
			$this->assertSame( 'forums/topic', bbp_sanitize_slug( 'forums/topic' ) );
			$this->assertSame( 'forums/bad%20slug', bbp_sanitize_slug( 'forums/"bad slug"' ) );

			add_filter( 'bbp_sanitize_slug', $filter, 10, 2 );
			$this->assertSame( 'prefix/forums/general/fragment', bbp_sanitize_slug( '//forums///general/#fragment/' ) );
			$this->assertSame( array( 'forums/general/fragment', '//forums///general/#fragment/' ), $observed );
		} finally {
			remove_filter( 'bbp_sanitize_slug', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_do_uninstall
	 */
	public function test_do_uninstall_removes_site_options_and_restores_the_current_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$old_site_id = get_current_blog_id();
		remove_action( 'wpmu_new_blog', 'bbp_new_site', 10 );
		$site_id     = self::factory()->blog->create();
		$option      = '_bbp_uninstall_test';
		$filter      = function ( $defaults ) use ( $option ) {
			$defaults[ $option ] = 'default';
			return $defaults;
		};

		add_filter( 'bbp_get_default_options', $filter );
		switch_to_blog( $site_id );
		update_option( $option, 'delete-me' );
		restore_current_blog();

		try {
			bbp_do_uninstall( $site_id );
			$this->assertSame( $old_site_id, get_current_blog_id() );

			switch_to_blog( $site_id );
			$this->assertFalse( get_option( $option, false ) );
			restore_current_blog();
		} finally {
			remove_filter( 'bbp_get_default_options', $filter );

			while ( ms_is_switched() ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * @covers ::bbp_tools_modify_menu_highlight
	 */
	public function test_tools_menu_highlight_preserves_settings_and_groups_other_tools() {
		$old_plugin_page = isset( $GLOBALS['plugin_page'] ) ? $GLOBALS['plugin_page'] : null;
		$old_submenu     = isset( $GLOBALS['submenu_file'] ) ? $GLOBALS['submenu_file'] : null;

		try {
			$GLOBALS['plugin_page']  = 'bbp-settings';
			$GLOBALS['submenu_file'] = 'options-general.php';
			bbp_tools_modify_menu_highlight();
			$this->assertSame( 'options-general.php', $GLOBALS['submenu_file'] );

			$GLOBALS['plugin_page'] = 'bbp-upgrade';
			bbp_tools_modify_menu_highlight();
			$this->assertSame( 'bbp-repair', $GLOBALS['submenu_file'] );
		} finally {
			$GLOBALS['plugin_page']  = $old_plugin_page;
			$GLOBALS['submenu_file'] = $old_submenu;
		}
	}
}
