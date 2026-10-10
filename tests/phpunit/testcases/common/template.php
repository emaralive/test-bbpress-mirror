<?php

/**
 * Tests for common template functions.
 */
class BBP_Tests_Common_Template extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_forums_url
	 * @covers ::bbp_get_forums_url
	 */
	public function test_forums_url_returns_and_outputs_escaped_url() {
		$path     = '/page/2/?first=1&second="quoted"';
		$slug     = function() {
			return 'discussion-board';
		};
		add_filter( 'bbp_get_root_slug', $slug );
		$expected = home_url( 'discussion-board' . $path );
		$escaped  = esc_url( $expected );

		try {
			$this->assertSame( home_url( 'discussion-board/' ), bbp_get_forums_url() );
			$this->assertSame( $expected, bbp_get_forums_url( $path ) );
			$this->assertNotSame( $expected, $escaped );
			$this->assertStringContainsString( '&#038;', $escaped );
			$this->assertStringNotContainsString( '"', $escaped );
			$this->expectOutputString( $escaped );
			bbp_forums_url( $path );
		} finally {
			remove_filter( 'bbp_get_root_slug', $slug );
		}
	}

	/**
	 * @covers ::bbp_topics_url
	 * @covers ::bbp_get_topics_url
	 */
	public function test_topics_url_returns_and_outputs_escaped_url() {
		$path     = '/page/3/?first=1&second="quoted"';
		$slug     = function() {
			return 'discussions';
		};
		add_filter( 'bbp_get_topic_archive_slug', $slug );
		$expected = home_url( 'discussions' . $path );
		$escaped  = esc_url( $expected );

		try {
			$this->assertSame( home_url( 'discussions/' ), bbp_get_topics_url() );
			$this->assertSame( $expected, bbp_get_topics_url( $path ) );
			$this->assertNotSame( $expected, $escaped );
			$this->assertStringContainsString( '&#038;', $escaped );
			$this->assertStringNotContainsString( '"', $escaped );
			$this->expectOutputString( $escaped );
			bbp_topics_url( $path );
		} finally {
			remove_filter( 'bbp_get_topic_archive_slug', $slug );
		}
	}

	/**
	 * @covers ::bbp_head
	 */
	public function test_head_runs_extension_action() {
		$called   = 0;
		$level    = ob_get_level();
		$callback = function() use ( &$called ) {
			$this->assertSame( 'bbp_head', current_filter() );
			++$called;
		};
		add_action( 'bbp_head', $callback );
		ob_start();

		try {
			bbp_head();
			$this->assertSame( 1, $called );
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			remove_action( 'bbp_head', $callback );
		}
	}

	/**
	 * @covers ::bbp_footer
	 */
	public function test_footer_runs_extension_action() {
		$called   = 0;
		$level    = ob_get_level();
		$callback = function() use ( &$called ) {
			$this->assertSame( 'bbp_footer', current_filter() );
			++$called;
		};
		add_action( 'bbp_footer', $callback );
		ob_start();

		try {
			bbp_footer();
			$this->assertSame( 1, $called );
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			remove_action( 'bbp_footer', $callback );
		}
	}

	/**
	 * @covers ::bbp_is_site_public
	 */
	public function test_is_site_public_uses_current_site_option_and_filter() {
		$site_id    = get_current_blog_id();
		$old_public = get_option( 'blog_public', 1 );
		$filter     = function( $public, $filtered_site_id ) use ( $site_id ) {
			$this->assertSame( 1, (int) $public );
			$this->assertSame( $site_id, $filtered_site_id );
			return false;
		};

		try {
			update_option( 'blog_public', 0 );
			$this->assertFalse( bbp_is_site_public() );

			update_option( 'blog_public', 1 );
			$this->assertTrue( bbp_is_site_public( $site_id ) );

			add_filter( 'bbp_is_site_public', $filter, 10, 2 );
			$this->assertFalse( bbp_is_site_public() );
		} finally {
			remove_filter( 'bbp_is_site_public', $filter, 10 );
			update_option( 'blog_public', $old_public );
		}
	}

	/**
	 * @covers ::bbp_is_site_public
	 * @group multisite
	 */
	public function test_is_site_public_uses_supplied_site_id() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$current_site_id = get_current_blog_id();
		$other_site_id   = self::factory()->blog->create();
		$old_public      = get_blog_option( $current_site_id, 'blog_public', 1 );
		$filtered_id     = null;
		$filter          = function( $public, $site_id ) use ( &$filtered_id ) {
			$filtered_id = $site_id;
			return $public;
		};

		update_blog_option( $current_site_id, 'blog_public', 1 );
		update_blog_option( $other_site_id, 'blog_public', 0 );
		add_filter( 'bbp_is_site_public', $filter, 10, 2 );

		try {
			$this->assertFalse( bbp_is_site_public( $other_site_id ) );
			$this->assertSame( $other_site_id, $filtered_id );

			switch_to_blog( $other_site_id );
			$this->assertFalse( bbp_is_site_public() );
			$this->assertSame( $other_site_id, $filtered_id );
		} finally {
			if ( get_current_blog_id() !== $current_site_id ) {
				restore_current_blog();
			}
			remove_filter( 'bbp_is_site_public', $filter, 10 );
			update_blog_option( $current_site_id, 'blog_public', $old_public );
		}
	}

	/**
	 * @covers ::bbp_is_forum
	 * @covers ::bbp_is_topic
	 * @covers ::bbp_is_reply
	 */
	public function test_object_type_predicates_identify_bbpress_posts() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$post_id  = $this->factory->post->create();

		$this->assertTrue( bbp_is_forum( $forum_id ) );
		$this->assertTrue( bbp_is_forum( (string) $forum_id ) );
		$this->assertFalse( bbp_is_forum( $topic_id ) );
		$this->assertFalse( bbp_is_forum( $post_id ) );
		$this->assertFalse( bbp_is_forum( 0 ) );
		$this->assertFalse( bbp_is_forum( PHP_INT_MAX ) );

		$this->assertTrue( bbp_is_topic( $topic_id ) );
		$this->assertTrue( bbp_is_topic( (string) $topic_id ) );
		$this->assertFalse( bbp_is_topic( $reply_id ) );
		$this->assertFalse( bbp_is_topic( $post_id ) );
		$this->assertFalse( bbp_is_topic( 0 ) );
		$this->assertFalse( bbp_is_topic( PHP_INT_MAX ) );

		$this->assertTrue( bbp_is_reply( $reply_id ) );
		$this->assertTrue( bbp_is_reply( (string) $reply_id ) );
		$this->assertFalse( bbp_is_reply( $forum_id ) );
		$this->assertFalse( bbp_is_reply( $post_id ) );
		$this->assertFalse( bbp_is_reply( 0 ) );
		$this->assertFalse( bbp_is_reply( PHP_INT_MAX ) );
	}

	/**
	 * @covers ::bbp_is_forum
	 * @covers ::bbp_is_topic
	 * @covers ::bbp_is_reply
	 * @dataProvider object_type_predicate_provider
	 */
	public function test_object_type_predicate_filters( $function, $factory_name ) {
		$post_id = $this->factory->post->create();
		$called  = 0;
		$filter_name = $function;
		$filter  = function( $retval, $filtered_post_id ) use ( &$called, $post_id ) {
			++$called;
			$this->assertFalse( $retval );
			$this->assertSame( $post_id, $filtered_post_id );
			return true;
		};
		add_filter( $filter_name, $filter, 10, 2 );

		try {
			$this->assertTrue( call_user_func( $function, $post_id ) );
			$this->assertSame( 1, $called );
		} finally {
			remove_filter( $filter_name, $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_is_forum
	 * @covers ::bbp_is_topic
	 * @covers ::bbp_is_reply
	 * @dataProvider object_type_predicate_provider
	 */
	public function test_object_type_predicate_filters_can_reject_match( $function, $factory_name ) {
		$post_id     = $this->create_bbp_post( $factory_name );
		$filter_name = $function;
		$filter  = function( $retval, $filtered_post_id ) use ( $post_id ) {
			$this->assertTrue( $retval );
			$this->assertSame( $post_id, $filtered_post_id );
			return false;
		};
		add_filter( $filter_name, $filter, 10, 2 );

		try {
			$this->assertFalse( call_user_func( $function, $post_id ) );
		} finally {
			remove_filter( $filter_name, $filter, 10 );
		}
	}

	public static function object_type_predicate_provider() {
		return array(
			'forum' => array( 'bbp_is_forum', 'forum' ),
			'topic' => array( 'bbp_is_topic', 'topic' ),
			'reply' => array( 'bbp_is_reply', 'reply' ),
		);
	}

	private function create_bbp_post( $factory_name ) {
		if ( 'reply' === $factory_name ) {
			$topic_id = $this->factory->topic->create();
			return $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		}

		return $this->factory->{$factory_name}->create();
	}
}
