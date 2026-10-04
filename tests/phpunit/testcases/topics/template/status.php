<?php

/**
 * Tests for the topic status template functions.
 *
 * @group topics
 * @group template
 * @group status
 */
class BBP_Tests_Topics_Template_Status extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_is_topic_sticky
	 */
	public function test_bbp_is_topic_sticky() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );

		$this->assertFalse( bbp_is_topic_sticky( $topic_id ) );
		$this->assertTrue( bbp_stick_topic( $topic_id ) );
		$this->assertTrue( bbp_is_topic_sticky( $topic_id ) );
		$this->assertTrue( bbp_is_topic_sticky( $topic_id, false ) );
	}

	/**
	 * @covers ::bbp_is_topic_super_sticky
	 */
	public function test_bbp_is_topic_super_sticky() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );

		$this->assertFalse( bbp_is_topic_super_sticky( $topic_id ) );
		$this->assertTrue( bbp_stick_topic( $topic_id, true ) );
		$this->assertTrue( bbp_is_topic_super_sticky( $topic_id ) );
		$this->assertTrue( bbp_is_topic_sticky( $topic_id ) );
		$this->assertFalse( bbp_is_topic_sticky( $topic_id, false ) );
	}

	/**
	 * @covers ::bbp_topic_status
	 */
	public function test_bbp_topic_status() {
		$topic_id = $this->factory->topic->create();

		$this->expectOutputString( bbp_get_public_status_id() );
		bbp_topic_status( $topic_id );
	}

	/**
	 * @covers ::bbp_get_topic_status
	 */
	public function test_bbp_get_topic_status() {
		$topic_id = $this->factory->topic->create();

		$this->assertSame( bbp_get_public_status_id(), bbp_get_topic_status( $topic_id ) );
		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_closed_status_id() ) );
		$this->assertSame( bbp_get_closed_status_id(), bbp_get_topic_status( $topic_id ) );
	}

	/**
	 * @covers ::bbp_is_topic_closed
	 */
	public function test_bbp_is_topic_closed() {
		$topic_id = $this->factory->topic->create();

		$this->assertFalse( bbp_is_topic_closed( $topic_id ) );
		bbp_close_topic( $topic_id );
		$this->assertTrue( bbp_is_topic_closed( $topic_id ) );
	}

	/**
	 * @covers ::bbp_is_topic_open
	 */
	public function test_bbp_is_topic_open() {
		$topic_id = $this->factory->topic->create();

		$this->assertTrue( bbp_is_topic_open( $topic_id ) );
		bbp_close_topic( $topic_id );
		$this->assertFalse( bbp_is_topic_open( $topic_id ) );
		bbp_open_topic( $topic_id );
		$this->assertTrue( bbp_is_topic_open( $topic_id ) );
		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_pending_status_id() ) );
		$this->assertTrue( bbp_is_topic_open( $topic_id ) );
	}

	/**
	 * @covers ::bbp_is_topic_public
	 */
	public function test_bbp_is_topic_public() {
		$topic_id = $this->factory->topic->create();

		$this->assertTrue( bbp_is_topic_public( $topic_id ) );
		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_closed_status_id() ) );
		$this->assertTrue( bbp_is_topic_public( $topic_id ) );
		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_pending_status_id() ) );
		$this->assertFalse( bbp_is_topic_public( $topic_id ) );
	}

	/**
	 * @covers ::bbp_is_topic_published
	 */
	public function test_bbp_is_topic_published() {
		$topic_id = $this->factory->topic->create();

		$this->assertTrue( bbp_is_topic_published( $topic_id ) );
		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_pending_status_id() ) );
		$this->assertFalse( bbp_is_topic_published( $topic_id ) );
	}

	/**
	 * @covers ::bbp_is_topic_spam
	 */
	public function test_bbp_is_topic_spam() {
		$topic_id = $this->factory->topic->create();

		$this->assertFalse( bbp_is_topic_spam( $topic_id ) );
		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_spam_status_id() ) );
		$this->assertTrue( bbp_is_topic_spam( $topic_id ) );
	}

	/**
	 * @covers ::bbp_is_topic_trash
	 */
	public function test_bbp_is_topic_trash() {
		$topic_id = $this->factory->topic->create();

		$this->assertFalse( bbp_is_topic_trash( $topic_id ) );
		wp_trash_post( $topic_id );
		$this->assertTrue( bbp_is_topic_trash( $topic_id ) );
	}

	/**
	 * @covers ::bbp_is_topic_pending
	 */
	public function test_bbp_is_topic_pending() {
		$topic_id = $this->factory->topic->create();

		$this->assertFalse( bbp_is_topic_pending( $topic_id ) );
		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_pending_status_id() ) );
		$this->assertTrue( bbp_is_topic_pending( $topic_id ) );
	}

	/**
	 * @covers ::bbp_is_topic_private
	 */
	public function test_bbp_is_topic_private() {
		$topic_id = $this->factory->topic->create();

		$this->assertFalse( bbp_is_topic_private( $topic_id ) );
		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_private_status_id() ) );
		$this->assertTrue( bbp_is_topic_private( $topic_id ) );
	}

	/**
	 * @covers ::bbp_is_topic_anonymous
	 */
	public function test_bbp_is_topic_anonymous() {
		$user_id  = $this->factory->user->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id ) );

		$this->assertFalse( bbp_is_topic_anonymous( $topic_id ) );
		update_post_meta( $topic_id, '_bbp_anonymous_name', 'Guest' );
		$this->assertTrue( bbp_is_topic_anonymous( $topic_id ) );
		delete_post_meta( $topic_id, '_bbp_anonymous_name' );
		update_post_meta( $topic_id, '_bbp_anonymous_email', 'guest@example.org' );
		$this->assertTrue( bbp_is_topic_anonymous( $topic_id ) );
		delete_post_meta( $topic_id, '_bbp_anonymous_email' );
		wp_update_post( array( 'ID' => $topic_id, 'post_author' => 0 ) );
		$this->assertTrue( bbp_is_topic_anonymous( $topic_id ) );
	}
}
