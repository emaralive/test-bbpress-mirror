<?php

/**
 * Tests for the `bbp_*_form_forum_*_()` functions.
 *
 * @group forums
 * @group template
 * @group forms
 */
class BBP_Tests_Forums_Template_Forms extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_form_forum_title
	 * @covers ::bbp_get_form_forum_title
	 * @group  bbp_xss
	 */
	public function test_bbp_get_form_forum_title() {
		$forum_id = $this->factory->forum->create(
			array(
				'post_title' => 'Forum " autofocus onfocus="alert(1)',
			)
		);
		$old_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

		add_filter( 'bbp_is_forum_edit', '__return_true' );
		$GLOBALS['post'] = get_post( $forum_id );
		setup_postdata( $GLOBALS['post'] );

		$this->assertSame( 'Forum &quot; autofocus onfocus=&quot;alert(1)', bbp_get_form_forum_title() );

		$GLOBALS['post'] = $old_post;
		wp_reset_postdata();
		remove_filter( 'bbp_is_forum_edit', '__return_true' );
	}

	/**
	 * @covers ::bbp_get_form_forum_moderators
	 */
	public function test_bbp_get_form_forum_moderators_shows_assigned_user() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		bbp_add_moderator( $forum_id, $user_id );

		$old_post     = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$old_forum_id = bbpress()->current_forum_id;
		$GLOBALS['post'] = get_post( $forum_id );
		bbpress()->current_forum_id = $forum_id;
		setup_postdata( $GLOBALS['post'] );
		add_filter( 'bbp_is_forum_edit', '__return_true' );

		try {
			$this->assertSame( get_userdata( $user_id )->user_nicename, bbp_get_form_forum_moderators() );
		} finally {
			remove_filter( 'bbp_is_forum_edit', '__return_true' );
			bbpress()->current_forum_id = $old_forum_id;
			$GLOBALS['post'] = $old_post;
			wp_reset_postdata();
		}
	}

	/**
	 * @covers ::bbp_form_forum_content
	 * @covers ::bbp_get_form_forum_content
	 * @todo   Implement test_bbp_form_forum_content().
	 * @todo   Implement test_bbp_get_form_forum_content().
	 */
	public function test_bbp_get_form_forum_content() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_forum_parent
	 * @covers ::bbp_get_form_forum_parent
	 * @todo   Implement test_bbp_form_forum_parent().
	 * @todo   Implement test_bbp_get_form_forum_parent().
	 */
	public function test_bbp_get_form_forum_parent() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_forum_type
	 * @covers ::bbp_get_form_forum_type
	 * @todo   Implement test_bbp_form_forum_type().
	 * @todo   Implement test_bbp_get_form_forum_type().
	 */
	public function test_bbp_get_form_forum_type() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_forum_visibility
	 * @covers ::bbp_get_form_forum_visibility
	 * @todo   Implement test_bbp_form_forum_visibility().
	 * @todo   Implement test_bbp_get_form_forum_visibility().
	 */
	public function test_bbp_get_form_forum_visibility() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_forum_subscribed
	 * @covers ::bbp_get_form_forum_subscribed
	 * @todo   Implement test_bbp_form_forum_subscribed().
	 * @todo   Implement test_bbp_get_form_forum_subscribed().
	 */
	public function test_bbp_get_form_forum_subscribed() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_forum_type_dropdown
	 * @covers ::bbp_get_form_forum_type_dropdown
	 * @todo   Implement test_bbp_form_forum_type_dropdown().
	 * @todo   Implement test_bbp_get_form_forum_type_dropdown().
	 */
	public function test_bbp_get_form_forum_type_dropdown() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_form_forum_type_dropdown
	 */
	public function test_bbp_get_form_forum_type_dropdown_selects_forum_by_default() {
		$this->assertMatchesRegularExpression(
			'/<option value="forum" selected=/',
			bbp_get_form_forum_type_dropdown()
		);
	}

	/**
	 * @covers ::bbp_form_forum_status_dropdown
	 * @covers ::bbp_get_form_forum_status_dropdown
	 * @todo   Implement test_bbp_form_forum_status_dropdown().
	 * @todo   Implement test_bbp_get_form_forum_status_dropdown().
	 */
	public function test_bbp_get_form_forum_status_dropdown() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_forum_visibility_dropdown
	 * @covers ::bbp_get_form_forum_visibility_dropdown
	 * @todo   Implement test_bbp_form_forum_visibility_dropdown().
	 * @todo   Implement test_bbp_get_form_forum_visibility_dropdown().
	 */
	public function test_bbp_get_form_forum_visibility_dropdown() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_form_forum_visibility_dropdown
	 */
	public function test_bbp_get_form_forum_visibility_dropdown_has_distinct_filter() {
		$filter = function( $html ) {
			return 'filtered visibility';
		};
		add_filter( 'bbp_get_form_forum_visibility_dropdown', $filter );
		try {
			$this->assertSame( 'filtered visibility', bbp_get_form_forum_visibility_dropdown() );
		} finally {
			remove_filter( 'bbp_get_form_forum_visibility_dropdown', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_form_forum_visibility_dropdown
	 */
	public function test_bbp_get_form_forum_visibility_dropdown_preserves_type_filter() {
		$filter = function( $html, $parsed_args, $args ) {
			$this->assertSame( 'bbp_forum_visibility', $parsed_args['select_id'] );
			$this->assertSame( array(), $args );
			return 'filtered by type hook';
		};
		add_filter( 'bbp_get_form_forum_type_dropdown', $filter, 10, 3 );
		try {
			$this->assertSame( 'filtered by type hook', bbp_get_form_forum_visibility_dropdown() );
		} finally {
			remove_filter( 'bbp_get_form_forum_type_dropdown', $filter );
		}
	}

	/**
	 * @covers ::bbp_is_forum_form_post_request
	 * @todo   Implement test_bbp_is_forum_form_post_request().
	 */
	public function test_bbp_is_forum_form_post_request() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
