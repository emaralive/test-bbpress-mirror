<?php
/**
 * Tests for the cache functions.
 *
 * @group cache
 */
class BBP_Core_Cache_Tests extends BBP_UnitTestCase {

	/**
	 * @group counts
	 * @covers ::bbp_clean_post_cache
	 */
	public function test_bbp_clean_post_cache() {

		// Get the post types.
		$tpt = bbp_get_topic_post_type();
		$rpt = bbp_get_reply_post_type();

		// Set up a forum with 1 topic and 1 reply to that topic.
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Make sure we've cached some data.
		bbp_get_all_child_ids( $f, $tpt );
		bbp_get_all_child_ids( $t, $rpt );

		// Setup
		$f_key_hash   = md5( serialize( array( 'parent_id' => $f, 'post_type' => $tpt, 'post_status' => array( 'draft', 'future' ) ) ) );
		$t_key_hash   = md5( serialize( array( 'parent_id' => $t, 'post_type' => $rpt, 'post_status' => array( 'draft', 'future' ) ) ) );
		$last_changed = wp_cache_get_last_changed( 'bbpress_posts' );

		// Keys
		$f_key = "bbp_child_ids:{$f_key_hash}:{$last_changed}";
		$t_key = "bbp_child_ids:{$t_key_hash}:{$last_changed}";

		$this->assertEquals( array( $t ), wp_cache_get( $f_key, 'bbpress_posts' ) );
		$this->assertEquals( array( $r ), wp_cache_get( $t_key, 'bbpress_posts' ) );

		// Clean the reply cache.
		clean_post_cache( $r );

		// Setup
		$last_changed = wp_cache_get_last_changed( 'bbpress_posts' );

		// Keys
		$f_key = "bbp_child_ids:{$f_key_hash}:{$last_changed}";
		$t_key = "bbp_child_ids:{$t_key_hash}:{$last_changed}";

		$this->assertEquals( false, wp_cache_get( $f_key, 'bbpress_posts' ) );
		$this->assertEquals( false, wp_cache_get( $t_key, 'bbpress_posts' ) );
	}

	/**
	 * Updating a bbPress post must not suspend later cache invalidation.
	 */
	public function test_post_update_does_not_suspend_later_cache_invalidation() {
		$post_id = $this->factory->reply->create( array( 'post_parent' => 0 ) );
		wp_update_post( array( 'ID' => $post_id, 'post_title' => 'Updated reply' ) );
		clean_post_cache( $post_id );

		try {
			$this->assertEmpty( $GLOBALS['_wp_suspend_cache_invalidation'] );
		} finally {
			wp_suspend_cache_invalidation( false );
		}
	}

	/**
	 * A nested update must be visible immediately, including in status hooks.
	 */
	public function test_nested_post_update_cleans_its_cache() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => 0 ) );
		get_post( $reply_id );
		$update_reply = function ( $new_status, $old_status, $post ) use ( $topic_id, $reply_id ) {
			if ( $topic_id === $post->ID ) {
				wp_update_post( array( 'ID' => $reply_id, 'post_title' => 'Nested update' ) );
			}
		};
		add_action( 'transition_post_status', $update_reply, 20, 3 );
		try {
			wp_update_post( array( 'ID' => $topic_id, 'post_title' => 'Outer update' ) );
			$this->assertSame( 'Nested update', get_post( $reply_id )->post_title );
			$this->assertEmpty( $GLOBALS['_wp_suspend_cache_invalidation'] );
		} finally {
			remove_action( 'transition_post_status', $update_reply, 20 );
			wp_suspend_cache_invalidation( false );
		}
	}

	/**
	 * bbPress must preserve suspension requested by the caller.
	 */
	public function test_post_update_preserves_existing_cache_suspension() {
		$post_id = $this->factory->reply->create( array( 'post_parent' => 0 ) );
		wp_update_post( array( 'ID' => $post_id, 'post_title' => 'First update' ) );
		wp_suspend_cache_invalidation( true );
		try {
			wp_update_post( array( 'ID' => $post_id, 'post_title' => 'Suspended update' ) );
			$this->assertTrue( $GLOBALS['_wp_suspend_cache_invalidation'] );
		} finally {
			wp_suspend_cache_invalidation( false );
		}
	}

	/**
	 * Updating a parent must not evict its descendants' post caches.
	 */
	public function test_parent_update_preserves_child_post_cache() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		get_post( $topic_id );
		get_post( $reply_id );
		wp_update_post( array( 'ID' => $forum_id, 'post_title' => 'Updated forum' ) );

		$this->assertNotFalse( wp_cache_get( $topic_id, 'posts' ) );
		$this->assertNotFalse( wp_cache_get( $reply_id, 'posts' ) );
	}

	/**
	 * @covers BBP_Skip_Children
	 */
	public function test_skip_children_registers_its_compatibility_hook_and_ignores_other_posts() {
		$post_id = $this->factory->post->create();
		$cache   = new BBP_Skip_Children();

		try {
			$this->assertSame( 10, has_action( 'pre_post_update', array( $cache, 'pre_post_update' ) ) );
			$cache->pre_post_update( 0 );
			$this->assertFalse( has_action( 'clean_post_cache', array( $cache, 'skip_related_posts' ) ) );
			$cache->pre_post_update( $post_id );
			$this->assertFalse( has_action( 'clean_post_cache', array( $cache, 'skip_related_posts' ) ) );
		} finally {
			$this->remove_skip_children_hooks( $cache );
		}
	}

	/**
	 * @covers BBP_Skip_Children
	 */
	public function test_skip_children_suspends_and_restores_cache_invalidation_for_the_current_post() {
		$post_id = $this->factory->reply->create( array( 'post_parent' => 0 ) );
		$cache   = new BBP_Skip_Children();

		try {
			wp_suspend_cache_invalidation( false );
			$cache->pre_post_update( $post_id );
			$this->assertSame( 10, has_action( 'clean_post_cache', array( $cache, 'skip_related_posts' ) ) );

			$cache->skip_related_posts( PHP_INT_MAX );
			$this->assertFalse( $GLOBALS['_wp_suspend_cache_invalidation'] );
			$this->assertFalse( has_action( 'wp_insert_post', array( $cache, 'restore_cache_invalidation' ) ) );

			$cache->skip_related_posts( $post_id );
			$this->assertTrue( $GLOBALS['_wp_suspend_cache_invalidation'] );
			$this->assertSame( 10, has_action( 'wp_insert_post', array( $cache, 'restore_cache_invalidation' ) ) );

			$cache->restore_cache_invalidation();
			$this->assertFalse( $GLOBALS['_wp_suspend_cache_invalidation'] );
		} finally {
			$this->remove_skip_children_hooks( $cache );
			wp_suspend_cache_invalidation( false );
		}
	}

	/**
	 * @covers BBP_Skip_Children
	 */
	public function test_skip_children_preserves_existing_cache_invalidation_suspension() {
		$post_id = $this->factory->topic->create( array( 'post_parent' => 0 ) );
		$cache   = new BBP_Skip_Children();

		try {
			wp_suspend_cache_invalidation( true );
			$cache->pre_post_update( $post_id );
			$cache->skip_related_posts( $post_id );
			$this->assertSame( 10, has_action( 'wp_insert_post', array( $cache, 'restore_cache_invalidation' ) ) );
			$cache->restore_cache_invalidation();
			$this->assertTrue( $GLOBALS['_wp_suspend_cache_invalidation'] );
		} finally {
			$this->remove_skip_children_hooks( $cache );
			wp_suspend_cache_invalidation( false );
		}
	}

	/**
	 * @covers ::bbp_clean_post_cache
	 */
	public function test_clean_post_cache_ignores_non_bbp_posts() {
		$post_id = $this->factory->post->create();
		$post    = get_post( $post_id );
		$cleaned = 0;
		$action  = function () use ( &$cleaned ) {
			$cleaned++;
		};

		wp_cache_set( 'last_changed', 'before', 'bbpress_posts' );
		add_action( 'bbp_clean_post_cache', $action );
		try {
			bbp_clean_post_cache( $post_id, $post );
			$this->assertSame( 0, $cleaned );
			$this->assertSame( 'before', wp_cache_get( 'last_changed', 'bbpress_posts' ) );
		} finally {
			remove_action( 'bbp_clean_post_cache', $action );
		}
	}

	/**
	 * @covers ::bbp_clean_post_cache
	 */
	public function test_clean_post_cache_fires_its_action_and_invalidates_root_queries() {
		$post_id       = $this->factory->forum->create( array( 'post_parent' => 0 ) );
		$post          = get_post( $post_id );
		$action_values = array();
		$action        = function ( $cleaned_id, $cleaned_post ) use ( &$action_values ) {
			$action_values[] = array( $cleaned_id, $cleaned_post );
		};

		wp_cache_set( 'last_changed', 'before', 'bbpress_posts' );
		add_action( 'bbp_clean_post_cache', $action, 10, 2 );
		try {
			bbp_clean_post_cache( 0, $post );
			$this->assertSame( array( array( $post_id, $post ) ), $action_values );
			$this->assertNotSame( 'before', wp_cache_get( 'last_changed', 'bbpress_posts' ) );
		} finally {
			remove_action( 'bbp_clean_post_cache', $action, 10 );
		}
	}

	/**
	 * @covers ::bbp_clean_post_cache
	 */
	public function test_clean_post_cache_recurses_through_each_bbp_parent() {
		$forum_id    = $this->factory->forum->create();
		$topic_id    = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id    = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$cleaned_ids = array();
		$action      = function ( $post_id ) use ( &$cleaned_ids ) {
			$cleaned_ids[] = $post_id;
		};

		wp_cache_set( 'last_changed', 'before', 'bbpress_posts' );
		add_action( 'bbp_clean_post_cache', $action );
		try {
			bbp_clean_post_cache( $reply_id, get_post( $reply_id ) );
			$this->assertSame( array( $reply_id, $topic_id, $forum_id ), $cleaned_ids );
			$this->assertNotSame( 'before', wp_cache_get( 'last_changed', 'bbpress_posts' ) );
		} finally {
			remove_action( 'bbp_clean_post_cache', $action );
		}
	}

	/**
	 * @covers ::bbp_clean_post_cache
	 */
	public function test_clean_post_cache_does_not_invalidate_root_queries_before_reaching_the_root() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );

		remove_action( 'clean_post_cache', 'bbp_clean_post_cache' );
		wp_cache_set( 'last_changed', 'before', 'bbpress_posts' );
		try {
			bbp_clean_post_cache( $topic_id, get_post( $topic_id ) );
			$this->assertSame( 'before', wp_cache_get( 'last_changed', 'bbpress_posts' ) );
		} finally {
			add_action( 'clean_post_cache', 'bbp_clean_post_cache', 10, 2 );
		}
	}

	/**
	 * @covers ::bbp_clean_user_count_cache
	 */
	public function test_clean_user_count_cache_invalidates_cached_counts() {
		wp_cache_set( 'bbp_forum_users_last_changed', 'before', 'users' );

		bbp_clean_user_count_cache();

		$this->assertNotSame( 'before', wp_cache_get( 'bbp_forum_users_last_changed', 'users' ) );
	}

	/**
	 * @dataProvider data_capabilities_meta_keys
	 *
	 * @covers ::bbp_clean_user_count_cache_on_meta_change
	 */
	public function test_user_count_cache_is_invalidated_only_for_capabilities_meta( $key, $should_invalidate ) {
		wp_cache_set( 'bbp_forum_users_last_changed', 'before', 'users' );

		bbp_clean_user_count_cache_on_meta_change( 123, 456, $key );

		$last_changed = wp_cache_get( 'bbp_forum_users_last_changed', 'users' );
		if ( $should_invalidate ) {
			$this->assertNotSame( 'before', $last_changed );
		} else {
			$this->assertSame( 'before', $last_changed );
		}
	}

	/**
	 * Data provider for test_user_count_cache_is_invalidated_only_for_capabilities_meta().
	 */
	public static function data_capabilities_meta_keys() {
		global $wpdb;

		return array(
			'unprefixed capabilities'   => array( 'capabilities',                                    true  ),
			'prefix without underscore' => array( 'wpcapabilities',                                  true  ),
			'current site capabilities' => array( $wpdb->get_blog_prefix() . 'capabilities',          true  ),
			'secondary capabilities'    => array( $wpdb->base_prefix . '2_capabilities',              true  ),
			'leading underscore'        => array( '_capabilities',                                   true  ),
			'singular capability'       => array( 'capability',                                      false ),
			'suffixed capabilities'     => array( 'capabilities_extra',                              false ),
		);
	}

	/**
	 * Remove hooks registered by BBP_Skip_Children.
	 *
	 * @param BBP_Skip_Children $cache Cache compatibility object.
	 */
	private function remove_skip_children_hooks( $cache ) {
		remove_action( 'pre_post_update', array( $cache, 'pre_post_update' ) );
		remove_action( 'clean_post_cache', array( $cache, 'skip_related_posts' ) );
		remove_action( 'wp_insert_post', array( $cache, 'restore_cache_invalidation' ) );
	}

}
