<?php

/**
 * Tests for the reply status template functions.
 *
 * @group replies
 * @group template
 * @group status
 */
class BBP_Tests_Repliess_Template_Status extends BBP_UnitTestCase {
	protected $old_current_user = 0;
	protected $keymaster_id;

	public function setUp(): void {
		parent::setUp();
		$this->old_current_user = get_current_user_id();
		$this->set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->keymaster_id = get_current_user_id();
		bbp_set_user_role( $this->keymaster_id, bbp_get_keymaster_role() );
	}

	public function tearDown(): void {
		parent::tearDown();
		$this->set_current_user( $this->old_current_user );
	}

	/**
	 * @covers ::bbp_reply_status
	 * @covers ::bbp_get_reply_status
	 */
	public function test_bbp_get_reply_status() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		$this->assertSame( bbp_get_public_status_id(), bbp_get_reply_status( $reply_id ) );
		wp_update_post( array( 'ID' => $reply_id, 'post_status' => bbp_get_pending_status_id() ) );
		$this->assertSame( bbp_get_pending_status_id(), bbp_get_reply_status( $reply_id ) );
		$this->expectOutputString( bbp_get_pending_status_id() );
		bbp_reply_status( $reply_id );
	}

	/**
	 * @covers ::bbp_is_reply_public
	 */
	public function test_bbp_is_reply_public() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		$this->assertTrue( bbp_is_reply_public( $reply_id ) );
		wp_update_post( array( 'ID' => $reply_id, 'post_status' => bbp_get_pending_status_id() ) );
		$this->assertFalse( bbp_is_reply_public( $reply_id ) );
	}

	/**
	 * @covers ::bbp_is_reply_published
	 */
	public function test_bbp_is_reply_published() {
		$forum_id = $this->factory->forum->create();

		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta' => array(
				'forum_id' => $forum_id,
			),
		) );

		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id'              => $forum_id,
				'topic_id'              => $topic_id,
			)
		) );

		$reply_published = bbp_is_reply_published( $r );
		$this->assertTrue( $reply_published );
		$reply_published = bbp_is_reply_published( $reply_id );
		$this->assertTrue( $reply_published );
	}

	/**
	 * @covers ::bbp_is_reply_spam
	 */
	public function test_bbp_is_reply_spam() {
		$forum_id = $this->factory->forum->create();

		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta' => array(
				'forum_id' => $forum_id,
			),
		) );

		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id'              => $forum_id,
				'topic_id'              => $topic_id,
			)
		) );

		bbp_spam_reply( $r );

		$reply_spam = bbp_is_reply_spam( $r );
		$this->assertTrue( $reply_spam );

		bbp_unspam_reply( $r );

		$reply_spam = bbp_is_reply_spam( $r );
		$this->assertFalse( $reply_spam );
	}

	/**
	 * @covers ::bbp_is_reply_trash
	 */
	public function test_bbp_is_reply_trash() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		$this->assertFalse( bbp_is_reply_trash( $reply_id ) );
		wp_trash_post( $reply_id );
		$this->assertTrue( bbp_is_reply_trash( $reply_id ) );
	}

	/**
	 * @covers ::bbp_is_reply_pending
	 */
	public function test_bbp_is_reply_pending() {
		$forum_id = $this->factory->forum->create();

		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta' => array(
				'forum_id' => $forum_id,
			),
		) );

		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id'              => $forum_id,
				'topic_id'              => $topic_id,
			)
		) );

		bbp_unapprove_reply( $r );

		$reply_pending = bbp_is_reply_pending( $r );
		$this->assertTrue( $reply_pending );

		bbp_approve_reply( $r );

		$reply_pending = bbp_is_reply_pending( $r );
		$this->assertFalse( $reply_pending );
	}

	/**
	 * @covers ::bbp_is_reply_private
	 */
	public function test_bbp_is_reply_private() {
		$forum_id = $this->factory->forum->create();

		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta' => array(
				'forum_id' => $forum_id,
			),
		) );

		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		$reply_private = bbp_is_reply_private( $reply_id );
		$this->assertFalse( $reply_private );

		$r = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'post_status' => bbp_get_private_status_id(),
			'reply_meta' => array(
				'forum_id'              => $forum_id,
				'topic_id'              => $topic_id,
			)
		) );

		$reply_private = bbp_is_reply_private( $r );
		$this->assertTrue( $reply_private );
	}

	/**
	 * @covers ::bbp_is_reply_anonymous
	 */
	public function test_bbp_is_reply_anonymous() {
		$user_id  = $this->factory->user->create();
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array(
			'post_author' => $user_id,
			'post_parent' => $topic_id,
		) );

		$this->assertFalse( bbp_is_reply_anonymous( $reply_id ) );
		update_post_meta( $reply_id, '_bbp_anonymous_name', 'Guest' );
		$this->assertTrue( bbp_is_reply_anonymous( $reply_id ) );
		delete_post_meta( $reply_id, '_bbp_anonymous_name' );
		update_post_meta( $reply_id, '_bbp_anonymous_email', 'guest@example.org' );
		$this->assertTrue( bbp_is_reply_anonymous( $reply_id ) );
		delete_post_meta( $reply_id, '_bbp_anonymous_email' );
		wp_update_post( array( 'ID' => $reply_id, 'post_author' => 0 ) );
		$this->assertTrue( bbp_is_reply_anonymous( $reply_id ) );
	}
}
