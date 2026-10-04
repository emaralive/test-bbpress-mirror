<?php

/**
 * Tests for the reply component functions.
 *
 * @group replies
 * @group functions
 * @group reply
 */
class BBP_Tests_Replies_Functions_Reply extends BBP_UnitTestCase {

	private function assert_reply_action( $function, $hook ) {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$seen     = array();
		$callback = function ( $id ) use ( &$seen ) {
			$seen[] = $id;
		};
		add_action( $hook, $callback );

		try {
			$this->assertFalse( $function( PHP_INT_MAX ) );
			$function( $reply_id );
			$this->assertSame( array( $reply_id ), $seen );
		} finally {
			remove_action( $hook, $callback );
		}
	}

	/**
	 * @group canonical
	 * @covers ::bbp_insert_reply
	 */
	public function test_bbp_insert_reply() {

		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_title' => 'Reply To: Topic 1',
			'post_content' => 'Content of reply to Topic 1',
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Get the reply.
		$reply = bbp_get_reply( $r );

		remove_all_filters( 'bbp_get_reply_content' );

		// Reply post.
		$this->assertSame( 'Reply To: Topic 1', bbp_get_reply_title( $r ) );
		$this->assertSame( 'Content of reply to Topic 1', bbp_get_reply_content( $r ) );
		$this->assertSame( 'publish', bbp_get_reply_status( $r ) );
		$this->assertSame( $t, wp_get_post_parent_id( $r ) );
		$this->assertEquals( 'http://' . WP_TESTS_DOMAIN . '/?reply=' . $reply->post_name, $reply->guid );

		// Reply meta.
		$this->assertSame( $f, bbp_get_reply_forum_id( $r ) );
		$this->assertSame( $t, bbp_get_reply_topic_id( $r ) );
	}

	/**
	 * @covers ::bbp_new_reply_handler
	 * @todo   Implement test_bbp_new_reply_handler().
	 */
	public function test_bbp_new_reply_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_edit_reply_handler
	 * @todo   Implement test_bbp_edit_reply_handler().
	 */
	public function test_bbp_edit_reply_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_reply
	 */
	public function test_bbp_update_reply() {
		$old_user = get_current_user_id();
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id, 'post_author' => $user_id ) );
		update_post_meta( $reply_id, '_edit_lock', 'stale' );
		delete_post_meta( $reply_id, '_bbp_forum_id' );
		delete_post_meta( $reply_id, '_bbp_topic_id' );
		$this->set_current_user( $user_id );

		try {
			bbp_update_reply( $reply_id, $topic_id, $forum_id, array(), $user_id, true );
			$this->assertSame( (string) $user_id, get_post_meta( $reply_id, '_edit_last', true ) );
			$this->assertFalse( metadata_exists( 'post', $reply_id, '_edit_lock' ) );
			$this->assertSame( (string) $forum_id, get_post_meta( $reply_id, '_bbp_forum_id', true ) );
			$this->assertSame( (string) $topic_id, get_post_meta( $reply_id, '_bbp_topic_id', true ) );
			delete_post_meta( $reply_id, '_bbp_author_ip' );
			bbp_update_reply( $reply_id, $topic_id, $forum_id, array(), $user_id, false );
			$this->assertNotEmpty( get_post_meta( $reply_id, '_bbp_author_ip', true ) );
		} finally {
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_update_reply_walker
	 */
	public function test_bbp_update_reply_walker() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = wp_insert_post( array(
			'post_parent' => $topic_id,
			'post_status' => bbp_get_public_status_id(),
			'post_type'   => bbp_get_reply_post_type(),
			'post_title'  => 'Reply walker',
		) );
		$active_time = get_post_field( 'post_date', $reply_id );

		bbp_update_reply_walker( $reply_id, $active_time, $forum_id, $topic_id, false );

		$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( 0, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $forum_id, false, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );
		$this->assertSame( $reply_id, bbp_get_topic_last_reply_id( $topic_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $forum_id ) );
	}

	/**
	 * @covers ::bbp_update_reply
	 * @covers ::bbp_update_reply_walker
	 */
	public function test_bbp_new_reply_updates_each_ancestor_once() {
		$root_id  = $this->factory->forum->create();
		$forum_id = $this->factory->forum->create(
			array(
				'post_parent' => $root_id,
			)
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id )
			)
		);
		$reply_id = wp_insert_post(
			array(
				'post_parent' => $topic_id,
				'post_status' => bbp_get_public_status_id(),
				'post_type'   => bbp_get_reply_post_type(),
				'post_title'  => 'Nested reply walker'
			)
		);

		$topic_updates  = array();
		$forum_updates  = array();
		$voice_counts   = 0;
		$topic_callback = function( $last_reply_id, $updated_topic_id ) use ( &$topic_updates ) {
			$topic_updates[] = $updated_topic_id;
			return $last_reply_id;
		};
		$forum_callback = function( $last_reply_id, $updated_forum_id ) use ( &$forum_updates ) {
			$forum_updates[] = $updated_forum_id;
			return $last_reply_id;
		};
		$voice_callback = function( $count ) use ( &$voice_counts ) {
			$voice_counts++;
			return $count;
		};

		add_filter( 'bbp_update_topic_last_reply_id', $topic_callback, 10, 2 );
		add_filter( 'bbp_update_forum_last_reply_id', $forum_callback, 10, 2 );
		add_filter( 'bbp_update_topic_voice_count', $voice_callback );

		do_action( 'bbp_new_reply', $reply_id, $topic_id, $forum_id, array(), 0, false, 0 );

		remove_filter( 'bbp_update_topic_last_reply_id', $topic_callback, 10 );
		remove_filter( 'bbp_update_forum_last_reply_id', $forum_callback, 10 );
		remove_filter( 'bbp_update_topic_voice_count', $voice_callback, 10 );

		$this->assertSame( array( $topic_id ), $topic_updates );
		$this->assertSame( array( $forum_id, $root_id ), $forum_updates );
		$this->assertSame( 1, $voice_counts );
		$this->assertSame( $reply_id, bbp_get_topic_last_reply_id( $topic_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $forum_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $root_id ) );
	}

	/**
	 * @covers ::bbp_update_reply_forum_id
	 */
	public function test_bbp_update_reply_forum_id() {
		bbp_create_initial_content();

		$forum_id = 36;
		$topic_id = 37;
		$reply_id = 38;

		bbp_update_reply_forum_id( $reply_id, $forum_id);

		$reply_forum_id = bbp_get_reply_forum_id( $reply_id );
		$this->assertSame( 36, $reply_forum_id );
	}

	/**
	 * @covers ::bbp_update_reply_topic_id
	 */
	public function test_bbp_update_reply_topic_id() {
		bbp_create_initial_content();

		$forum_id = 36;
		$topic_id = 37;
		$reply_id = 38;

		bbp_update_reply_topic_id( $reply_id, $topic_id);

		$reply_topic_id = bbp_get_reply_topic_id( $reply_id );
		$this->assertSame( 37, $reply_topic_id );
	}

	/**
	 * @covers ::bbp_update_reply_to
	 */
	public function test_bbp_update_reply_to() {
		$forum_id  = $this->factory->forum->create();
		$topic_ids = $this->factory->topic->create_many( 2, array( 'post_parent' => $forum_id ) );
		$parent_id = $this->factory->reply->create( array( 'post_parent' => $topic_ids[0] ) );
		$reply_id  = $this->factory->reply->create( array( 'post_parent' => $topic_ids[0] ) );
		$other_id  = $this->factory->reply->create( array( 'post_parent' => $topic_ids[1] ) );

		$this->assertSame( $parent_id, bbp_update_reply_to( $reply_id, $parent_id ) );
		$this->assertSame( $parent_id, bbp_get_reply_to( $reply_id ) );
		$this->assertSame( 0, bbp_update_reply_to( $reply_id, $other_id ) );
		$this->assertSame( 0, bbp_get_reply_to( $reply_id ) );
	}

	/**
	 * @covers ::bbp_get_reply_ancestors
	 * @todo   Implement test_bbp_get_reply_ancestors().
	 */
	public function test_bbp_get_reply_ancestors() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_reply_revision_log
	 */
	public function test_bbp_update_reply_revision_log() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$user_id  = $this->factory->user->create();
		$log      = bbp_update_reply_revision_log( array( 'reply_id' => $reply_id, 'author_id' => $user_id, 'revision_id' => 17, 'reason' => 'Clarify wording' ) );
		$this->assertSame( $user_id, $log[17]['author'] );
		$this->assertSame( 'Clarify wording', $log[17]['reason'] );
		$this->assertSame( $log, bbp_get_reply_raw_revision_log( $reply_id ) );
	}

	/**
	 * @covers ::bbp_move_reply_handler
	 * @todo   Implement test_bbp_move_reply_handler().
	 */
	public function test_bbp_move_reply_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_move_reply_count
	 */
	public function test_bbp_move_reply_count() {
		$topic_author_id       = $this->factory->user->create();
		$reply_author_id       = $this->factory->user->create();
		$destination_author_id = $this->factory->user->create();
		$source_parent_id      = $this->factory->forum->create( array( 'forum_meta' => array( 'forum_type' => 'category' ) ) );
		$destination_parent_id = $this->factory->forum->create( array( 'forum_meta' => array( 'forum_type' => 'category' ) ) );
		$source_forum_id       = $this->factory->forum->create( array( 'post_parent' => $source_parent_id ) );
		$destination_forum_id  = $this->factory->forum->create( array( 'post_parent' => $destination_parent_id ) );
		$source_topic_id      = $this->factory->topic->create( array(
			'post_author' => $topic_author_id,
			'post_parent' => $source_forum_id,
			'topic_meta'  => array( 'forum_id' => $source_forum_id ),
		) );
		$destination_topic_id = $this->factory->topic->create( array(
			'post_author' => $destination_author_id,
			'post_parent' => $destination_forum_id,
			'topic_meta'  => array( 'forum_id' => $destination_forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_author' => $reply_author_id,
			'post_parent' => $source_topic_id,
			'reply_meta'  => array(
				'forum_id' => $source_forum_id,
				'topic_id' => $source_topic_id,
			),
		) );

		wp_update_post( array(
			'ID'          => $reply_id,
			'post_parent' => $destination_topic_id,
		) );
		bbp_update_reply_topic_id( $reply_id, $destination_topic_id );
		bbp_update_reply_forum_id( $reply_id, $destination_forum_id );
		bbp_move_reply_count( $reply_id, $source_topic_id, $destination_topic_id );

		$this->assertSame( 0, bbp_get_forum_reply_count( $source_forum_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $destination_forum_id, true, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $source_parent_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $destination_parent_id, true, true ) );
		$this->assertSame( 0, bbp_get_topic_reply_count( $source_topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count( $destination_topic_id, true ) );
		$this->assertEqualSets( array( $topic_author_id ), bbp_get_topic_engagements( $source_topic_id ) );
		$this->assertEqualSets( array( $reply_author_id, $destination_author_id ), bbp_get_topic_engagements( $destination_topic_id ) );
		$this->assertSame( 1, bbp_get_topic_voice_count( $source_topic_id, true ) );
		$this->assertSame( 2, bbp_get_topic_voice_count( $destination_topic_id, true ) );
	}

	/**
	 * @covers ::bbp_move_reply_count
	 */
	public function test_bbp_move_reply_count_updates_hidden_counts() {
		$source_parent_id      = $this->factory->forum->create( array( 'forum_meta' => array( 'forum_type' => 'category' ) ) );
		$destination_parent_id = $this->factory->forum->create( array( 'forum_meta' => array( 'forum_type' => 'category' ) ) );
		$source_forum_id       = $this->factory->forum->create( array( 'post_parent' => $source_parent_id ) );
		$destination_forum_id  = $this->factory->forum->create( array( 'post_parent' => $destination_parent_id ) );
		$source_topic_id      = $this->factory->topic->create( array(
			'post_parent' => $source_forum_id,
			'topic_meta'  => array( 'forum_id' => $source_forum_id ),
		) );
		$destination_topic_id = $this->factory->topic->create( array(
			'post_parent' => $destination_forum_id,
			'topic_meta'  => array( 'forum_id' => $destination_forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $source_topic_id,
			'post_status' => bbp_get_pending_status_id(),
			'reply_meta'  => array(
				'forum_id' => $source_forum_id,
				'topic_id' => $source_topic_id,
			),
		) );

		wp_update_post( array(
			'ID'          => $reply_id,
			'post_parent' => $destination_topic_id,
		) );
		bbp_update_reply_topic_id( $reply_id, $destination_topic_id );
		bbp_update_reply_forum_id( $reply_id, $destination_forum_id );
		bbp_move_reply_count( $reply_id, $source_topic_id, $destination_topic_id );

		$this->assertSame( 0, bbp_get_forum_reply_count_hidden( $source_forum_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $destination_forum_id, true, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count_hidden( $source_parent_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $destination_parent_id, true, true ) );
		$this->assertSame( 0, bbp_get_topic_reply_count_hidden( $source_topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count_hidden( $destination_topic_id, true ) );
	}

	/**
	 * @covers ::bbp_move_reply_count
	 */
	public function test_bbp_move_reply_count_transfers_contribution_when_converted_to_topic() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_author' => $user_id,
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		wp_update_post( array(
			'ID'          => $reply_id,
			'post_parent' => $forum_id,
			'post_type'   => bbp_get_topic_post_type(),
		) );
		bbp_update_topic_forum_id( $reply_id, $forum_id );
		bbp_update_topic_topic_id( $reply_id );
		bbp_move_reply_count( $reply_id, $topic_id, $reply_id );

		$this->assertSame( 0, bbp_get_user_reply_count( $user_id, true ) );
		$this->assertSame( 1, bbp_get_user_topic_count( $user_id, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $forum_id, true, true ) );
		$this->assertSame( 2, bbp_get_forum_topic_count( $forum_id, true, true ) );
	}

	/**
	 * @covers ::bbp_toggle_reply_handler
	 * @todo   Implement test_bbp_toggle_reply_handler().
	 */
	public function test_bbp_toggle_reply_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_delete_reply
	 */
	public function test_bbp_delete_reply() {
		$this->assert_reply_action( 'bbp_delete_reply', 'bbp_delete_reply' );
	}

	/**
	 * @covers ::bbp_trash_reply
	 */
	public function test_bbp_trash_reply() {
		$this->assert_reply_action( 'bbp_trash_reply', 'bbp_trash_reply' );
	}

	/**
	 * @covers ::bbp_untrash_reply
	 */
	public function test_bbp_untrash_reply() {
		$this->assert_reply_action( 'bbp_untrash_reply', 'bbp_untrash_reply' );
	}

	/**
	 * @covers ::bbp_deleted_reply
	 */
	public function test_bbp_deleted_reply() {
		$this->assert_reply_action( 'bbp_deleted_reply', 'bbp_deleted_reply' );
	}

	/**
	 * @covers ::bbp_trashed_reply
	 */
	public function test_bbp_trashed_reply() {
		$this->assert_reply_action( 'bbp_trashed_reply', 'bbp_trashed_reply' );
	}

	/**
	 * @covers ::bbp_untrashed_reply
	 */
	public function test_bbp_untrashed_reply() {
		$this->assert_reply_action( 'bbp_untrashed_reply', 'bbp_untrashed_reply' );
	}

	/**
	 * @covers ::bbp_get_replies_per_page
	 */
	public function test_bbp_get_replies_per_page() {
		update_option( '_bbp_replies_per_page', 0 );
		$this->assertSame( 12, bbp_get_replies_per_page( 12 ) );
		update_option( '_bbp_replies_per_page', '22' );
		$this->assertSame( 22, bbp_get_replies_per_page() );
	}

	/**
	 * @covers ::bbp_get_replies_per_rss_page
	 */
	public function test_bbp_get_replies_per_rss_page() {
		update_option( '_bbp_replies_per_rss_page', 0 );
		$this->assertSame( 18, bbp_get_replies_per_rss_page( 18 ) );
		update_option( '_bbp_replies_per_rss_page', '30' );
		$this->assertSame( 30, bbp_get_replies_per_rss_page() );
	}

	/**
	 * @covers ::bbp_reply_content_autoembed
	 */
	public function test_bbp_reply_content_autoembed() {
		global $wp_embed;

		$callback = array( $wp_embed, 'autoembed' );
		$priority = has_filter( 'bbp_get_reply_content', $callback );
		remove_filter( 'bbp_get_reply_content', $callback, $priority );
		add_filter( 'bbp_use_autoembed', '__return_false' );

		try {
			bbp_reply_content_autoembed();
			$this->assertFalse( has_filter( 'bbp_get_reply_content', $callback ) );
			remove_filter( 'bbp_use_autoembed', '__return_false' );
			add_filter( 'bbp_use_autoembed', '__return_true' );
			bbp_reply_content_autoembed();
			$this->assertSame( 2, has_filter( 'bbp_get_reply_content', $callback ) );
		} finally {
			remove_filter( 'bbp_use_autoembed', '__return_false' );
			remove_filter( 'bbp_use_autoembed', '__return_true' );
			remove_filter( 'bbp_get_reply_content', $callback, 2 );
			if ( false !== $priority ) {
				add_filter( 'bbp_get_reply_content', $callback, $priority );
			}
		}
	}

	/**
	 * @covers ::_bbp_has_replies_where
	 */
	public function test_bbp_has_replies_where() {
		$topic_id = $this->factory->topic->create();
		$posts    = bbp_db()->prefix . 'posts';
		$where    = "WHERE 1=1  AND {$posts}.post_parent = {$topic_id}";
		$query    = new WP_Query();
		$query->set( 'post_parent', $topic_id );
		$query->set( 'post_type', array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ) );

		$this->assertSame( $where, _bbp_has_replies_where( $where, false ) );
		$this->assertSame( "WHERE 1=1 AND ({$posts}.ID = {$topic_id} OR {$posts}.post_parent = {$topic_id})", _bbp_has_replies_where( $where, $query ) );
		$query->set( 'post__in', array( $topic_id ) );
		$this->assertSame( $where, _bbp_has_replies_where( $where, $query ) );
	}

	/**
	 * @covers ::bbp_display_replies_feed_rss2
	 * @todo   Implement test_bbp_display_replies_feed_rss2().
	 */
	public function test_bbp_display_replies_feed_rss2() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_check_reply_edit
	 * @todo   Implement test_bbp_check_reply_edit().
	 */
	public function test_bbp_check_reply_edit() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_reply_position
	 * @todo   Implement test_bbp_update_reply_position().
	 */
	public function test_bbp_update_reply_position() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_reply_position_raw
	 */
	public function test_bbp_get_reply_position_raw() {
		$topic_id = $this->factory->topic->create();
		$first_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$next_id  = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$this->assertSame( 0, bbp_get_reply_position_raw( $topic_id, $topic_id ) );
		$this->assertSame( 1, bbp_get_reply_position_raw( $first_id, $topic_id ) );
		$this->assertSame( 2, bbp_get_reply_position_raw( $next_id, $topic_id ) );
	}

	/**
	 * @covers ::bbp_list_replies
	 */
	public function test_bbp_list_replies() {
		$bbp       = bbpress();
		$old_query = $bbp->reply_query;
		$old_pages = $bbp->max_num_pages;
		$walker    = new class {
			public $max_pages = 2;

			public function paged_walk() {
				return '<li>Reply</li>';
			}
		};
		$bbp->reply_query = (object) array( 'posts' => array() );

		try {
			ob_start();
			bbp_list_replies( array( 'walker' => $walker, 'style' => 'div' ) );
			$this->assertSame( "<div class='bbp-replies-list'><li>Reply</li></div>", ob_get_clean() );
			$this->assertSame( 2, $bbp->max_num_pages );
			$this->assertFalse( $bbp->reply_query->in_the_loop );
			ob_start();
			bbp_list_replies( array( 'walker' => $walker, 'style' => 'table' ) );
			$this->assertSame( "<ul class='bbp-replies-list'><li>Reply</li></ul>", ob_get_clean() );
		} finally {
			$bbp->reply_query   = $old_query;
			$bbp->max_num_pages = $old_pages;
		}
	}

	/**
	 * @covers ::bbp_validate_reply_to
	 */
	public function test_bbp_validate_reply_to() {
		$forum_id  = $this->factory->forum->create();
		$topic_ids = $this->factory->topic->create_many( 2, array( 'post_parent' => $forum_id ) );
		$parent_id = $this->factory->reply->create( array( 'post_parent' => $topic_ids[0] ) );
		$reply_id  = $this->factory->reply->create( array( 'post_parent' => $topic_ids[0] ) );
		$other_id  = $this->factory->reply->create( array( 'post_parent' => $topic_ids[1] ) );

		$this->assertSame( $parent_id, bbp_validate_reply_to( $parent_id, $reply_id ) );
		$this->assertSame( 0, bbp_validate_reply_to( $reply_id, $reply_id ) );
		$this->assertSame( 0, bbp_validate_reply_to( (string) $reply_id, $reply_id ) );
		$this->assertSame( 0, bbp_validate_reply_to( $other_id, $reply_id ) );
		$this->assertSame( $other_id, bbp_validate_reply_to( $other_id ) );
	}
}
