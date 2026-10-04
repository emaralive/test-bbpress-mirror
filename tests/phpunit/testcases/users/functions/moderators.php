<?php

/**
 * Tests for object moderator lookup.
 *
 * @group users
 * @group capabilities
 */
class BBP_Tests_Users_Functions_Moderators extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_get_moderators
	 */
	public function test_object_without_moderators_does_not_return_unrelated_users() {
		$forum_id     = $this->factory->forum->create();
		$moderator_id = $this->factory->user->create();
		$other_id     = $this->factory->user->create();

		$this->assertSame( array(), bbp_get_moderators( $forum_id ) );
		$this->assertTrue( bbp_add_moderator( $forum_id, $moderator_id ) );
		$this->assertSame( array( $moderator_id ), wp_list_pluck( bbp_get_moderators( $forum_id ), 'ID' ) );
		$this->assertNotContains( $other_id, wp_list_pluck( bbp_get_moderators( $forum_id ), 'ID' ) );
		$this->assertTrue( bbp_remove_moderator( $forum_id, $moderator_id ) );
		$this->assertSame( array(), bbp_get_moderators( $forum_id ) );
	}
}
