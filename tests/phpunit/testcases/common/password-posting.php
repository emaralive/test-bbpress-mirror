<?php

/**
 * Password checks for front-end topic and reply submissions.
 *
 * @group common
 * @group capabilities
 */
class BBP_Tests_Common_Password_Posting extends BBP_UnitTestCase {

	protected $old_post;
	protected $old_request;
	protected $old_server;
	protected $old_errors;
	protected $old_allow_anonymous;
	protected $old_allow_content_throttle;
	protected $old_password_cookie;

	public function setUp(): void {
		parent::setUp();

		$this->old_post                   = $_POST;
		$this->old_request                = $_REQUEST;
		$this->old_server                 = $_SERVER;
		$this->old_errors                 = bbpress()->errors;
		$this->old_allow_anonymous        = get_option( '_bbp_allow_anonymous' );
		$this->old_allow_content_throttle = get_option( '_bbp_allow_content_throttle' );
		$this->old_password_cookie        = isset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] ) ? $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] : null;

		update_option( '_bbp_allow_anonymous', true );
		update_option( '_bbp_allow_content_throttle', false );
		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );
	}

	public function tearDown(): void {
		$_POST            = $this->old_post;
		$_REQUEST         = $this->old_request;
		$_SERVER          = $this->old_server;
		bbpress()->errors = $this->old_errors;
		update_option( '_bbp_allow_anonymous', $this->old_allow_anonymous );
		update_option( '_bbp_allow_content_throttle', $this->old_allow_content_throttle );

		if ( null === $this->old_password_cookie ) {
			unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );
		} else {
			$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = $this->old_password_cookie;
		}

		parent::tearDown();
	}

	protected function submit( $type, $forum_id, $topic_id = 0 ) {
		$home_url             = wp_parse_url( home_url( '/' ) );
		$_SERVER['HTTP_HOST'] = $home_url['host'];

		if ( isset( $home_url['port'] ) ) {
			$_SERVER['HTTP_HOST'] .= ':' . $home_url['port'];
		}

		$_SERVER['REQUEST_URI'] = $home_url['path'];
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$_POST                  = array(
			'bbp_forum_id'       => $forum_id,
			'bbp_anonymous_name'  => 'Anonymous User',
			'bbp_anonymous_email' => 'anonymous@example.org',
		);
		$_REQUEST              = array();
		bbpress()->errors      = new WP_Error();

		if ( 'topic' === $type ) {
			$_POST['bbp_topic_title']   = 'Password posting test topic';
			$_POST['bbp_topic_content'] = 'Password posting test content';
			$_REQUEST['_wpnonce']      = wp_create_nonce( 'bbp-new-topic' );
		} else {
			$_POST['bbp_topic_id']      = $topic_id;
			$_POST['bbp_reply_content'] = 'Password posting test reply';
			$_REQUEST['_wpnonce']      = wp_create_nonce( 'bbp-new-reply' );
		}

		$redirect = function() {
			throw new RuntimeException( 'Posting redirect.' );
		};
		$no_cookies = function() {
			return array();
		};

		add_filter( 'wp_redirect', $redirect );
		add_filter( 'bbp_filter_anonymous_post_data', $no_cookies );

		try {
			if ( 'topic' === $type ) {
				bbp_new_topic_handler( 'bbp-new-topic' );
			} else {
				bbp_new_reply_handler( 'bbp-new-reply' );
			}
		} catch ( RuntimeException $exception ) {
			if ( 'Posting redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		} finally {
			remove_filter( 'wp_redirect', $redirect );
			remove_filter( 'bbp_filter_anonymous_post_data', $no_cookies );
		}
	}

	protected function get_child_ids( $type, $parent_id ) {
		return get_posts(
			array(
				'fields'           => 'ids',
				'posts_per_page'   => -1,
				'post_parent'      => $parent_id,
				'post_status'      => 'any',
				'post_type'        => ( 'topic' === $type ) ? bbp_get_topic_post_type() : bbp_get_reply_post_type(),
				'suppress_filters' => true,
			)
		);
	}

	/**
	 * @covers ::bbp_new_topic_handler
	 * @covers ::bbp_new_reply_handler
	 */
	public function test_anonymous_and_participant_cannot_post_without_forum_password() {
		$parent_id = $this->factory->forum->create( array( 'post_password' => 'forum-secret' ) );
		$child_id  = $this->factory->forum->create( array( 'post_parent' => $parent_id ) );
		$user_id   = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );

		foreach ( array( 0, $user_id ) as $actor_id ) {
			$this->set_current_user( $actor_id );

			foreach ( array( $parent_id, $child_id ) as $forum_id ) {
				$topic_id = $this->factory->topic->create(
					array(
						'post_parent' => $forum_id,
						'topic_meta'  => array( 'forum_id' => $forum_id ),
					)
				);
				$existing_topic_ids = $this->get_child_ids( 'topic', $forum_id );

				$this->submit( 'topic', $forum_id );
				$this->assertSame( $existing_topic_ids, $this->get_child_ids( 'topic', $forum_id ) );
				$this->assertContains( 'bbp_new_topic_forum_password', bbpress()->errors->get_error_codes() );

				$this->submit( 'reply', $forum_id, $topic_id );
				$this->assertSame( array(), $this->get_child_ids( 'reply', $topic_id ) );
				$this->assertContains( 'bbp_new_reply_topic_password', bbpress()->errors->get_error_codes() );
			}
		}
	}

	/**
	 * @covers ::bbp_new_topic_handler
	 * @covers ::bbp_new_reply_handler
	 */
	public function test_participant_can_post_after_supplying_forum_password() {
		$forum_id = $this->factory->forum->create( array( 'post_password' => 'forum-secret' ) );
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$user_id = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher = new PasswordHash( 8, true );
		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = $hasher->HashPassword( 'forum-secret' );
		$this->set_current_user( $user_id );

		$this->submit( 'topic', $forum_id );
		$this->assertCount( 2, $this->get_child_ids( 'topic', $forum_id ) );
		$this->assertSame( array(), bbpress()->errors->get_error_codes() );

		$this->submit( 'reply', $forum_id, $topic_id );
		$this->assertCount( 1, $this->get_child_ids( 'reply', $topic_id ) );
		$this->assertSame( array(), bbpress()->errors->get_error_codes() );
	}

	/**
	 * @covers ::bbp_new_reply_handler
	 */
	public function test_anonymous_user_cannot_reply_without_topic_password() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent'   => $forum_id,
				'post_password' => 'topic-secret',
				'topic_meta'    => array( 'forum_id' => $forum_id ),
			)
		);

		$this->set_current_user( 0 );
		$this->submit( 'reply', $forum_id, $topic_id );

		$this->assertSame( array(), $this->get_child_ids( 'reply', $topic_id ) );
		$this->assertContains( 'bbp_new_reply_topic_password', bbpress()->errors->get_error_codes() );
	}

	/**
	 * @covers ::bbp_new_topic_handler
	 * @covers ::bbp_new_reply_handler
	 */
	public function test_anonymous_user_can_post_to_public_forum() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		$this->set_current_user( 0 );
		$this->submit( 'topic', $forum_id );
		$this->assertCount( 2, $this->get_child_ids( 'topic', $forum_id ) );
		$this->assertSame( array(), bbpress()->errors->get_error_codes() );

		$this->submit( 'reply', $forum_id, $topic_id );
		$this->assertCount( 1, $this->get_child_ids( 'reply', $topic_id ) );
		$this->assertSame( array(), bbpress()->errors->get_error_codes() );
	}
}
