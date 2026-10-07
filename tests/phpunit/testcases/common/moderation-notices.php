<?php

/**
 * Tests for notices after front-end submissions are held for moderation.
 *
 * @group common
 * @group moderation
 */
class BBP_Tests_Common_Moderation_Notices extends BBP_UnitTestCase {

	protected $old_post;
	protected $old_request;
	protected $old_server;
	protected $old_errors;
	protected $old_moderation_keys;
	protected $old_allow_content_throttle;

	public function setUp(): void {
		parent::setUp();

		$this->old_post                   = $_POST;
		$this->old_request                = $_REQUEST;
		$this->old_server                 = $_SERVER;
		$this->old_errors                 = bbpress()->errors;
		$this->old_moderation_keys        = get_option( 'moderation_keys' );
		$this->old_allow_content_throttle = get_option( '_bbp_allow_content_throttle' );

		update_option( 'moderation_keys', 'review phrase' );
		update_option( '_bbp_allow_content_throttle', false );
	}

	public function tearDown(): void {
		$_POST            = $this->old_post;
		$_REQUEST         = $this->old_request;
		$_SERVER          = $this->old_server;
		bbpress()->errors = $this->old_errors;
		update_option( 'moderation_keys', $this->old_moderation_keys );
		update_option( '_bbp_allow_content_throttle', $this->old_allow_content_throttle );

		parent::tearDown();
	}

	protected function prepare_request() {
		$home_url = wp_parse_url( home_url( '/' ) );

		$_SERVER['HTTP_HOST'] = $home_url['host'];
		if ( isset( $home_url['port'] ) ) {
			$_SERVER['HTTP_HOST'] .= ':' . $home_url['port'];
		}

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $home_url['path'];
		$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
		$_POST                     = array();
		$_REQUEST                  = array();
		bbpress()->errors          = new WP_Error();
	}

	protected function catch_redirect( $callback ) {
		$did_redirect = false;
		$redirect     = function() use ( &$did_redirect ) {
			$did_redirect = true;
			throw new RuntimeException( 'Posting redirect.' );
		};

		add_filter( 'wp_redirect', $redirect );

		try {
			call_user_func( $callback );
		} catch ( RuntimeException $exception ) {
			if ( 'Posting redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		} finally {
			remove_filter( 'wp_redirect', $redirect );
		}

		return $did_redirect;
	}

	/**
	 * @covers ::bbp_new_topic_handler
	 */
	public function test_moderated_topic_shows_notice_without_redirecting() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();

		$this->set_current_user( $user_id );
		$this->prepare_request();

		$_POST['bbp_forum_id']     = $forum_id;
		$_POST['bbp_topic_title']   = 'Moderated topic';
		$_POST['bbp_topic_content'] = 'Contains the review phrase.';
		$_POST['bbp_topic_tags']    = 'moderated';
		$_REQUEST['_wpnonce']       = wp_create_nonce( 'bbp-new-topic' );

		$did_redirect = $this->catch_redirect( function() {
			bbp_new_topic_handler( 'bbp-new-topic' );
		} );
		$topic_ids   = get_posts(
			array(
				'fields'           => 'ids',
				'posts_per_page'   => -1,
				'post_parent'      => $forum_id,
				'post_status'      => 'any',
				'post_type'        => bbp_get_topic_post_type(),
				'suppress_filters' => true,
			)
		);

		$this->assertCount( 1, $topic_ids );
		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $topic_ids[0] ) );
		$this->assertSame( 'Your topic is pending moderation.', bbpress()->errors->get_error_message( 'bbp_topic_moderated' ) );
		$this->assertFalse( $did_redirect );
		$this->assertSame( '', bbp_get_form_topic_title() );
		$this->assertSame( '', bbp_get_form_topic_content() );
		$this->assertSame( '', bbp_get_form_topic_tags() );
	}

	/**
	 * @covers ::bbp_new_reply_handler
	 */
	public function test_moderated_reply_shows_notice_without_redirecting() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		$this->set_current_user( $user_id );
		$this->prepare_request();

		$_POST['bbp_forum_id']      = $forum_id;
		$_POST['bbp_topic_id']      = $topic_id;
		$_POST['bbp_reply_content'] = 'Contains the review phrase.';
		$_REQUEST['_wpnonce']       = wp_create_nonce( 'bbp-new-reply' );

		$did_redirect = $this->catch_redirect( function() {
			bbp_new_reply_handler( 'bbp-new-reply' );
		} );
		$reply_ids   = get_posts(
			array(
				'fields'           => 'ids',
				'posts_per_page'   => -1,
				'post_parent'      => $topic_id,
				'post_status'      => 'any',
				'post_type'        => bbp_get_reply_post_type(),
				'suppress_filters' => true,
			)
		);

		$this->assertCount( 1, $reply_ids );
		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $reply_ids[0] ) );
		$this->assertSame( 'Your reply is pending moderation.', bbpress()->errors->get_error_message( 'bbp_reply_moderated' ) );
		$this->assertFalse( $did_redirect );
		$this->assertSame( '', bbp_get_form_reply_content() );
	}
}
