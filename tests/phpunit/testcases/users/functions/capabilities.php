<?php

/**
 * Tests for user roles, account state, and object moderators.
 *
 * @group users
 * @group capabilities
 */
class BBP_Tests_Users_Functions_Capabilities extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_is_valid_role
	 */
	public function test_only_registered_forum_roles_are_valid() {
		$this->assertTrue( bbp_is_valid_role( bbp_get_participant_role() ) );
		$this->assertTrue( bbp_is_valid_role( bbp_get_moderator_role() ) );
		$this->assertFalse( bbp_is_valid_role( 'subscriber' ) );
		$this->assertFalse( bbp_is_valid_role( array( bbp_get_participant_role() ) ) );
		$this->assertFalse( bbp_is_valid_role() );
	}

	/**
	 * @covers ::bbp_set_user_role
	 * @covers ::bbp_get_user_role
	 * @covers ::bbp_get_user_blog_role
	 */
	public function test_setting_forum_role_preserves_wordpress_role() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$role    = bbp_get_user_role( $user_id );
		if ( $role ) {
			get_userdata( $user_id )->remove_role( $role );
		}
		$this->assertFalse( bbp_get_user_role( $user_id ) );
		$this->assertSame( 'subscriber', bbp_get_user_blog_role( $user_id ) );

		$this->assertSame( bbp_get_participant_role(), bbp_set_user_role( $user_id, bbp_get_participant_role() ) );
		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $user_id ) );
		$this->assertSame( 'subscriber', bbp_get_user_blog_role( $user_id ) );
		$this->assertFalse( bbp_set_user_role( $user_id, bbp_get_participant_role() ) );

		$this->assertSame( bbp_get_moderator_role(), bbp_set_user_role( $user_id, bbp_get_moderator_role() ) );
		$this->assertSame( bbp_get_moderator_role(), bbp_get_user_role( $user_id ) );
		$this->assertFalse( in_array( bbp_get_participant_role(), get_userdata( $user_id )->roles, true ) );
		$this->assertSame( 'subscriber', bbp_get_user_blog_role( $user_id ) );
	}

	/**
	 * @covers ::bbp_set_user_role
	 */
	public function test_setting_invalid_role_does_not_change_user_roles() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$roles = get_userdata( $user_id )->roles;

		bbp_set_user_role( $user_id, 'not-a-forum-role' );
		$this->assertSame( $roles, get_userdata( $user_id )->roles );
		$this->assertFalse( bbp_set_user_role( PHP_INT_MAX, bbp_get_moderator_role() ) );
	}

	/**
	 * @covers ::bbp_get_user_role_map
	 */
	public function test_wordpress_role_map_defaults_to_keymaster_for_administrators() {
		$map = bbp_get_user_role_map();
		$this->assertSame( bbp_get_keymaster_role(), $map['administrator'] );
		$this->assertSame( bbp_get_default_role(), $map['subscriber'] );
		$this->assertSame( bbp_get_default_role(), $map['editor'] );
	}

	/**
	 * @covers ::bbp_is_user_spammer
	 * @covers ::bbp_is_user_deleted
	 * @covers ::bbp_is_user_active
	 * @covers ::bbp_is_user_inactive
	 */
	public function test_user_activity_distinguishes_valid_missing_and_filtered_users() {
		$user_id = $this->factory->user->create();
		$this->assertFalse( bbp_is_user_spammer( $user_id ) );
		$this->assertFalse( bbp_is_user_deleted( $user_id ) );
		$this->assertTrue( bbp_is_user_active( $user_id ) );
		$this->assertFalse( bbp_is_user_inactive( $user_id ) );
		$this->assertFalse( bbp_is_user_active( PHP_INT_MAX ) );
		$this->assertTrue( bbp_is_user_deleted( PHP_INT_MAX ) );

		add_filter( 'bbp_core_is_user_spammer', '__return_true' );
		try {
			$this->assertFalse( bbp_is_user_active( $user_id ) );
			$this->assertTrue( bbp_is_user_inactive( $user_id ) );
		} finally {
			remove_filter( 'bbp_core_is_user_spammer', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_is_user_keymaster
	 */
	public function test_keymaster_check_uses_forum_role_and_filter() {
		$user_id = $this->factory->user->create();
		$this->assertFalse( bbp_is_user_keymaster( $user_id ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->assertTrue( bbp_is_user_keymaster( $user_id ) );

		add_filter( 'bbp_is_user_keymaster', '__return_false' );
		try {
			$this->assertFalse( bbp_is_user_keymaster( $user_id ) );
		} finally {
			remove_filter( 'bbp_is_user_keymaster', '__return_false' );
		}
	}

	/**
	 * @covers ::bbp_user_has_profile
	 */
	public function test_profile_visibility_rejects_missing_and_inactive_users_except_for_keymaster() {
		$user_id      = $this->factory->user->create();
		$keymaster_id = $this->factory->user->create();
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		$this->assertTrue( bbp_user_has_profile( $user_id ) );
		$this->assertFalse( bbp_user_has_profile( PHP_INT_MAX ) );

		$this->set_current_user( $user_id );
		add_filter( 'bbp_core_is_user_spammer', '__return_true' );
		try {
			$this->assertFalse( bbp_user_has_profile( $user_id ) );
			$this->set_current_user( $keymaster_id );
			$this->assertTrue( bbp_user_has_profile( $user_id ) );
		} finally {
			remove_filter( 'bbp_core_is_user_spammer', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_add_moderator
	 * @covers ::bbp_remove_moderator
	 * @covers ::bbp_get_moderator_ids
	 * @covers ::bbp_get_moderators
	 */
	public function test_object_moderators_are_added_listed_and_removed() {
		$forum_id     = $this->factory->forum->create();
		$moderator_id = $this->factory->user->create();
		$this->assertTrue( bbp_add_moderator( $forum_id, $moderator_id ) );
		$this->assertContains( $moderator_id, bbp_get_moderator_ids( $forum_id ) );
		$this->assertContains( $moderator_id, wp_list_pluck( bbp_get_moderators( $forum_id ), 'ID' ) );

		$this->assertTrue( bbp_remove_moderator( $forum_id, $moderator_id ) );
		$this->assertNotContains( $moderator_id, bbp_get_moderator_ids( $forum_id ) );
		$this->assertSame( array(), bbp_get_moderators( $forum_id ) );
	}

	/**
	 * @covers ::bbp_get_moderators
	 */
	public function test_global_moderators_have_moderator_role() {
		$moderator_id = $this->factory->user->create();
		$other_id     = $this->factory->user->create();
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );

		$ids = wp_list_pluck( bbp_get_moderators(), 'ID' );
		$this->assertContains( $moderator_id, $ids );
		$this->assertNotContains( $other_id, $ids );
	}
}
