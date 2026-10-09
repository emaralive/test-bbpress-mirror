<?php

/**
 * Tests user profile topic and reply visibility.
 *
 * @group users
 * @group visibility
 */
class BBP_Tests_Users_Functions_Profile_Content_Visibility extends BBP_UnitTestCase {
	private $old_topic_query;
	private $old_reply_query;

	public function setUp(): void {
		parent::setUp();

		$this->old_topic_query = bbpress()->topic_query;
		$this->old_reply_query = bbpress()->reply_query;
	}

	public function tearDown(): void {
		bbpress()->topic_query = $this->old_topic_query;
		bbpress()->reply_query = $this->old_reply_query;
		$this->set_current_user( 0 );
		unset( $_GET['view'] );

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_get_user_topics_started
	 */
	public function test_author_sees_own_public_and_pending_topics() {
		$user_id       = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$public_topic  = $this->factory->topic->create( array( 'post_author' => $user_id ) );
		$pending_topic = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_status' => bbp_get_pending_status_id() ) );
		$spam_topic    = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_status' => bbp_get_spam_status_id() ) );
		$trash_topic   = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_status' => bbp_get_trash_status_id() ) );

		$this->set_current_user( $user_id );
		$this->assertTrue( bbp_get_user_topics_started( array( 'author' => $user_id ) ) );

		$topic_ids = wp_list_pluck( bbpress()->topic_query->posts, 'ID' );
		$this->assertContains( $public_topic, $topic_ids );
		$this->assertContains( $pending_topic, $topic_ids );
		$this->assertNotContains( $spam_topic, $topic_ids );
		$this->assertNotContains( $trash_topic, $topic_ids );
	}

	/**
	 * @covers ::bbp_get_user_replies_created
	 */
	public function test_author_sees_own_public_and_pending_replies() {
		$user_id      = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$topic_id     = $this->factory->topic->create();
		$reply_args   = array(
			'post_author' => $user_id,
			'post_parent' => $topic_id,
			'reply_meta'  => array( 'topic_id' => $topic_id ),
		);
		$public_reply = $this->factory->reply->create( $reply_args );

		$reply_args['post_status'] = bbp_get_pending_status_id();
		$pending_reply             = $this->factory->reply->create( $reply_args );
		$reply_args['post_status'] = bbp_get_spam_status_id();
		$spam_reply                = $this->factory->reply->create( $reply_args );
		$reply_args['post_status'] = bbp_get_trash_status_id();
		$trash_reply               = $this->factory->reply->create( $reply_args );

		$this->set_current_user( $user_id );
		$this->assertTrue( bbp_get_user_replies_created( array( 'author' => $user_id ) ) );

		$reply_ids = wp_list_pluck( bbpress()->reply_query->posts, 'ID' );
		$this->assertContains( $public_reply, $reply_ids );
		$this->assertContains( $pending_reply, $reply_ids );
		$this->assertNotContains( $spam_reply, $reply_ids );
		$this->assertNotContains( $trash_reply, $reply_ids );
	}

	/**
	 * @covers ::bbp_get_user_topics_started
	 */
	public function test_other_user_does_not_see_pending_topic() {
		$author_id     = $this->factory->user->create();
		$viewer_id     = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$public_topic  = $this->factory->topic->create( array( 'post_author' => $author_id ) );
		$pending_topic = $this->factory->topic->create( array( 'post_author' => $author_id, 'post_status' => bbp_get_pending_status_id() ) );

		$this->set_current_user( $viewer_id );
		$this->assertTrue( bbp_get_user_topics_started( array( 'author' => $author_id ) ) );

		$topic_ids = wp_list_pluck( bbpress()->topic_query->posts, 'ID' );
		$this->assertContains( $public_topic, $topic_ids );
		$this->assertNotContains( $pending_topic, $topic_ids );
	}

	/**
	 * @covers ::bbp_get_user_replies_created
	 */
	public function test_other_user_does_not_see_pending_reply() {
		$author_id     = $this->factory->user->create();
		$viewer_id     = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$topic_id      = $this->factory->topic->create();
		$reply_args    = array(
			'post_author' => $author_id,
			'post_parent' => $topic_id,
			'reply_meta'  => array( 'topic_id' => $topic_id ),
		);
		$public_reply  = $this->factory->reply->create( $reply_args );
		$reply_args['post_status'] = bbp_get_pending_status_id();
		$pending_reply = $this->factory->reply->create( $reply_args );

		$this->set_current_user( $viewer_id );
		$this->assertTrue( bbp_get_user_replies_created( array( 'author' => $author_id ) ) );

		$reply_ids = wp_list_pluck( bbpress()->reply_query->posts, 'ID' );
		$this->assertContains( $public_reply, $reply_ids );
		$this->assertNotContains( $pending_reply, $reply_ids );
	}

	/**
	 * @covers ::bbp_get_user_topics_started
	 * @covers ::bbp_get_user_replies_created
	 */
	public function test_keymaster_does_not_see_another_users_pending_content_without_view_all() {
		$author_id    = $this->factory->user->create();
		$keymaster_id = $this->factory->user->create();
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		$topic_id     = $this->factory->topic->create( array( 'post_author' => $author_id, 'post_status' => bbp_get_pending_status_id() ) );
		$public_topic = $this->factory->topic->create();
		$reply_id     = $this->factory->reply->create( array(
			'post_author' => $author_id,
			'post_parent' => $public_topic,
			'post_status' => bbp_get_pending_status_id(),
			'reply_meta'  => array( 'topic_id' => $public_topic ),
		) );

		$this->set_current_user( $keymaster_id );
		$this->assertFalse( bbp_get_user_topics_started( array( 'author' => $author_id ) ) );
		$this->assertNotContains( $topic_id, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		$this->assertFalse( bbp_get_user_replies_created( array( 'author' => $author_id ) ) );
		$this->assertNotContains( $reply_id, wp_list_pluck( bbpress()->reply_query->posts, 'ID' ) );
	}

	/**
	 * @covers ::bbp_get_user_topics_started
	 * @covers ::bbp_get_user_replies_created
	 */
	public function test_author_sees_own_private_content() {
		$user_id       = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$private_topic = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_status' => bbp_get_private_status_id() ) );
		$public_topic  = $this->factory->topic->create();
		$private_reply = $this->factory->reply->create( array(
			'post_author' => $user_id,
			'post_parent' => $public_topic,
			'post_status' => bbp_get_private_status_id(),
			'reply_meta'  => array( 'topic_id' => $public_topic ),
		) );

		$this->set_current_user( $user_id );
		$this->assertTrue( bbp_get_user_topics_started( array( 'author' => $user_id ) ) );
		$this->assertContains( $private_topic, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		$this->assertTrue( bbp_get_user_replies_created( array( 'author' => $user_id ) ) );
		$this->assertContains( $private_reply, wp_list_pluck( bbpress()->reply_query->posts, 'ID' ) );
	}

	/**
	 * @covers ::bbp_get_user_topics_started
	 * @covers ::bbp_get_user_replies_created
	 */
	public function test_logged_out_user_does_not_see_pending_content() {
		$author_id     = $this->factory->user->create();
		$topic_id      = $this->factory->topic->create( array( 'post_author' => $author_id, 'post_status' => bbp_get_pending_status_id() ) );
		$public_topic  = $this->factory->topic->create();
		$reply_id      = $this->factory->reply->create( array(
			'post_author' => $author_id,
			'post_parent' => $public_topic,
			'post_status' => bbp_get_pending_status_id(),
			'reply_meta'  => array( 'topic_id' => $public_topic ),
		) );

		$this->set_current_user( 0 );
		$this->assertFalse( bbp_get_user_topics_started( array( 'author' => $author_id ) ) );
		$this->assertNotContains( $topic_id, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		$this->assertFalse( bbp_get_user_replies_created( array( 'author' => $author_id ) ) );
		$this->assertNotContains( $reply_id, wp_list_pluck( bbpress()->reply_query->posts, 'ID' ) );
	}

	/**
	 * @covers ::bbp_get_user_topics_started
	 * @covers ::bbp_get_user_replies_created
	 */
	public function test_logged_out_query_without_author_does_not_see_pending_content() {
		$author_id    = $this->factory->user->create();
		$topic_id     = $this->factory->topic->create( array( 'post_author' => $author_id, 'post_status' => bbp_get_pending_status_id() ) );
		$public_topic = $this->factory->topic->create();
		$reply_id     = $this->factory->reply->create( array(
			'post_author' => $author_id,
			'post_parent' => $public_topic,
			'post_status' => bbp_get_pending_status_id(),
			'reply_meta'  => array( 'topic_id' => $public_topic ),
		) );

		$this->set_current_user( 0 );
		bbp_get_user_topics_started( array( 'author' => 0 ) );
		$this->assertNotContains( $topic_id, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		$this->assertFalse( bbp_get_user_replies_created( array( 'author' => 0 ) ) );
		$this->assertNotContains( $reply_id, wp_list_pluck( bbpress()->reply_query->posts, 'ID' ) );
	}

	/**
	 * @covers ::bbp_get_user_topics_started
	 * @covers ::bbp_get_user_replies_created
	 */
	public function test_comma_separated_authors_do_not_include_pending_content() {
		$author_ids    = $this->factory->user->create_many( 2 );
		$public_topic  = $this->factory->topic->create( array( 'post_author' => $author_ids[0] ) );
		$pending_topic = $this->factory->topic->create( array( 'post_author' => $author_ids[1], 'post_status' => bbp_get_pending_status_id() ) );
		$reply_args    = array(
			'post_author' => $author_ids[0],
			'post_parent' => $public_topic,
			'reply_meta'  => array( 'topic_id' => $public_topic ),
		);
		$public_reply  = $this->factory->reply->create( $reply_args );
		$reply_args['post_author'] = $author_ids[1];
		$reply_args['post_status'] = bbp_get_pending_status_id();
		$pending_reply = $this->factory->reply->create( $reply_args );
		$authors       = implode( ',', $author_ids );

		$this->set_current_user( $author_ids[0] );
		$this->assertTrue( bbp_get_user_topics_started( array( 'author' => $authors ) ) );
		$this->assertContains( $public_topic, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		$this->assertNotContains( $pending_topic, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		$this->assertTrue( bbp_get_user_replies_created( array( 'author' => $authors ) ) );
		$this->assertContains( $public_reply, wp_list_pluck( bbpress()->reply_query->posts, 'ID' ) );
		$this->assertNotContains( $pending_reply, wp_list_pluck( bbpress()->reply_query->posts, 'ID' ) );
	}

	/**
	 * @covers ::bbp_get_user_topics_started
	 * @covers ::bbp_get_user_replies_created
	 */
	public function test_keymaster_view_all_includes_own_spam_and_trash_content() {
		$user_id      = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$spam_topic   = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_status' => bbp_get_spam_status_id() ) );
		$public_topic = $this->factory->topic->create();
		$reply_args   = array(
			'post_author' => $user_id,
			'post_parent' => $public_topic,
			'reply_meta'  => array( 'topic_id' => $public_topic ),
		);
		$reply_args['post_status'] = bbp_get_trash_status_id();
		$trash_reply               = $this->factory->reply->create( $reply_args );

		$this->set_current_user( $user_id );
		$_GET['view'] = 'all';

		$this->assertTrue( bbp_get_user_topics_started( array( 'author' => $user_id ) ) );
		$this->assertContains( $spam_topic, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		$this->assertTrue( bbp_get_user_replies_created( array( 'author' => $user_id ) ) );
		$this->assertContains( $trash_reply, wp_list_pluck( bbpress()->reply_query->posts, 'ID' ) );
	}

	/**
	 * @covers ::bbp_get_user_topics_started
	 * @covers ::bbp_get_user_replies_created
	 */
	public function test_explicit_post_status_is_preserved_for_author_queries() {
		$user_id       = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$public_topic  = $this->factory->topic->create( array( 'post_author' => $user_id ) );
		$pending_topic = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_status' => bbp_get_pending_status_id() ) );
		$reply_args    = array(
			'post_author' => $user_id,
			'post_parent' => $public_topic,
			'reply_meta'  => array( 'topic_id' => $public_topic ),
		);
		$public_reply  = $this->factory->reply->create( $reply_args );
		$reply_args['post_status'] = bbp_get_pending_status_id();
		$pending_reply = $this->factory->reply->create( $reply_args );

		$this->set_current_user( $user_id );
		$this->assertTrue( bbp_get_user_topics_started( array( 'author' => $user_id, 'post_status' => bbp_get_public_status_id() ) ) );
		$this->assertSame( array( $public_topic ), wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		$this->assertNotContains( $pending_topic, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );

		$this->assertTrue( bbp_get_user_replies_created( array( 'author' => $user_id, 'post_status' => bbp_get_public_status_id() ) ) );
		$this->assertSame( array( $public_reply ), wp_list_pluck( bbpress()->reply_query->posts, 'ID' ) );
		$this->assertNotContains( $pending_reply, wp_list_pluck( bbpress()->reply_query->posts, 'ID' ) );
	}

	/**
	 * @covers ::bbp_get_user_replies_created
	 */
	public function test_author_does_not_see_pending_reply_under_pending_topic() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_status' => bbp_get_pending_status_id() ) );
		$reply_id = $this->factory->reply->create( array(
			'post_author' => $user_id,
			'post_parent' => $topic_id,
			'post_status' => bbp_get_pending_status_id(),
			'reply_meta'  => array( 'topic_id' => $topic_id ),
		) );

		$this->set_current_user( $user_id );
		$this->assertFalse( bbp_get_user_replies_created( array( 'author' => $user_id ) ) );
		$this->assertNotContains( $reply_id, wp_list_pluck( bbpress()->reply_query->posts, 'ID' ) );
	}
}
