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

	/**
	 * @covers ::bbp_is_favorites
	 * @covers ::bbp_is_subscriptions
	 * @covers ::bbp_is_topics_created
	 * @covers ::bbp_is_replies_created
	 * @covers ::bbp_is_user_home
	 * @covers ::bbp_is_single_user
	 * @covers ::bbp_is_single_user_edit
	 * @covers ::bbp_is_single_user_profile
	 * @covers ::bbp_is_single_user_topics
	 * @covers ::bbp_is_single_user_replies
	 * @covers ::bbp_is_single_user_engagements
	 * @covers ::bbp_is_single_view
	 * @covers ::bbp_is_edit
	 * @covers ::bbp_is_forum_edit
	 * @covers ::bbp_is_topic_edit
	 * @covers ::bbp_is_reply_edit
	 * @dataProvider query_flag_predicate_provider
	 */
	public function test_query_flag_predicates_require_strict_true_and_apply_filter( $function, $property ) {
		$wp_query   = bbp_get_wp_query();
		$had_value  = property_exists( $wp_query, $property );
		$old_value  = $had_value ? $wp_query->{$property} : null;
		$old_name   = bbp_get_query_name();
		$filter     = function( $retval ) {
			$this->assertTrue( $retval );
			return false;
		};
		$override   = function( $retval ) {
			$this->assertFalse( $retval );
			return true;
		};

		try {
			bbp_reset_query_name();
			unset( $wp_query->{$property} );
			$this->assertFalse( call_user_func( $function ) );

			$wp_query->{$property} = 1;
			$this->assertFalse( call_user_func( $function ) );

			$wp_query->{$property} = false;
			add_filter( $function, $override );
			$this->assertTrue( call_user_func( $function ) );
			remove_filter( $function, $override );

			$wp_query->{$property} = true;
			$this->assertTrue( call_user_func( $function ) );

			add_filter( $function, $filter );
			$this->assertFalse( call_user_func( $function ) );
		} finally {
			remove_filter( $function, $filter );
			remove_filter( $function, $override );
			bbp_set_query_name( $old_name );
			if ( $had_value ) {
				$wp_query->{$property} = $old_value;
			} else {
				unset( $wp_query->{$property} );
			}
		}
	}

	public static function query_flag_predicate_provider() {
		return array(
			'favorites'               => array( 'bbp_is_favorites', 'bbp_is_single_user_favs' ),
			'subscriptions'           => array( 'bbp_is_subscriptions', 'bbp_is_single_user_subs' ),
			'topics created'          => array( 'bbp_is_topics_created', 'bbp_is_single_user_topics' ),
			'replies created'         => array( 'bbp_is_replies_created', 'bbp_is_single_user_replies' ),
			'user home'               => array( 'bbp_is_user_home', 'bbp_is_single_user_home' ),
			'single user'             => array( 'bbp_is_single_user', 'bbp_is_single_user' ),
			'single user edit'        => array( 'bbp_is_single_user_edit', 'bbp_is_single_user_edit' ),
			'single user profile'     => array( 'bbp_is_single_user_profile', 'bbp_is_single_user_profile' ),
			'single user topics'      => array( 'bbp_is_single_user_topics', 'bbp_is_single_user_topics' ),
			'single user replies'     => array( 'bbp_is_single_user_replies', 'bbp_is_single_user_replies' ),
			'single user engagements' => array( 'bbp_is_single_user_engagements', 'bbp_is_single_user_engagements' ),
			'single view'             => array( 'bbp_is_single_view', 'bbp_is_view' ),
			'edit'                    => array( 'bbp_is_edit', 'bbp_is_edit' ),
			'forum edit'              => array( 'bbp_is_forum_edit', 'bbp_is_forum_edit' ),
			'topic edit'              => array( 'bbp_is_topic_edit', 'bbp_is_topic_edit' ),
			'reply edit'              => array( 'bbp_is_reply_edit', 'bbp_is_reply_edit' ),
		);
	}

	/**
	 * @covers ::bbp_get_query_name
	 * @covers ::bbp_is_query_name
	 * @covers ::bbp_reset_query_name
	 * @covers ::bbp_set_query_name
	 */
	public function test_query_name_helpers_set_compare_and_reset_value() {
		$old_name = bbp_get_query_name();

		try {
			bbp_reset_query_name();
			$this->assertSame( '', bbp_get_query_name() );
			$this->assertTrue( bbp_is_query_name() );

			bbp_set_query_name( 'bbp_unit_test' );
			$this->assertSame( 'bbp_unit_test', bbp_get_query_name() );
			$this->assertTrue( bbp_is_query_name( 'bbp_unit_test' ) );
			$this->assertFalse( bbp_is_query_name( 'bbp_other' ) );
		} finally {
			bbp_set_query_name( $old_name );
		}
	}

	/**
	 * @covers ::bbp_is_forum_archive
	 * @covers ::bbp_is_single_forum
	 * @covers ::bbp_is_single_topic
	 * @covers ::bbp_is_topic_archive
	 * @covers ::bbp_is_single_reply
	 * @covers ::bbp_is_single_view
	 * @dataProvider query_name_predicate_provider
	 */
	public function test_query_name_predicates( $function, $query_name ) {
		$old_name = bbp_get_query_name();

		try {
			bbp_reset_query_name();
			$this->assertFalse( call_user_func( $function ) );

			bbp_set_query_name( $query_name );
			$this->assertTrue( call_user_func( $function ) );
		} finally {
			bbp_set_query_name( $old_name );
		}
	}

	public static function query_name_predicate_provider() {
		return array(
			'forum archive' => array( 'bbp_is_forum_archive', 'bbp_forum_archive' ),
			'single forum'  => array( 'bbp_is_single_forum', 'bbp_single_forum' ),
			'single topic'  => array( 'bbp_is_single_topic', 'bbp_single_topic' ),
			'topic archive' => array( 'bbp_is_topic_archive', 'bbp_topic_archive' ),
			'single reply'  => array( 'bbp_is_single_reply', 'bbp_single_reply' ),
			'single view'   => array( 'bbp_is_single_view', 'bbp_single_view' ),
		);
	}

	/**
	 * @covers ::bbp_is_single_forum
	 * @covers ::bbp_is_single_topic
	 * @covers ::bbp_is_single_reply
	 * @dataProvider single_predicate_edit_provider
	 */
	public function test_single_predicates_exclude_edit_state( $function, $query_name, $edit_property ) {
		$wp_query  = bbp_get_wp_query();
		$old_name  = bbp_get_query_name();
		$had_value = property_exists( $wp_query, $edit_property );
		$old_value = $had_value ? $wp_query->{$edit_property} : null;

		try {
			bbp_set_query_name( $query_name );
			$wp_query->{$edit_property} = true;
			$this->assertFalse( call_user_func( $function ) );
		} finally {
			bbp_set_query_name( $old_name );
			if ( $had_value ) {
				$wp_query->{$edit_property} = $old_value;
			} else {
				unset( $wp_query->{$edit_property} );
			}
		}
	}

	public static function single_predicate_edit_provider() {
		return array(
			'forum' => array( 'bbp_is_single_forum', 'bbp_single_forum', 'bbp_is_forum_edit' ),
			'topic' => array( 'bbp_is_single_topic', 'bbp_single_topic', 'bbp_is_topic_edit' ),
			'reply' => array( 'bbp_is_single_reply', 'bbp_single_reply', 'bbp_is_reply_edit' ),
		);
	}

	/**
	 * @covers ::bbp_is_single_forum
	 * @covers ::bbp_is_single_topic
	 * @covers ::bbp_is_single_reply
	 * @dataProvider singular_predicate_provider
	 */
	public function test_single_predicates_exclude_edit_state_for_singular_query( $function, $factory_name, $edit_property ) {
		$post_id  = $this->create_bbp_post( $factory_name );
		$wp_query = bbp_get_wp_query();
		$states   = array(
			'is_singular'       => $wp_query->is_singular,
			'queried_object'    => property_exists( $wp_query, 'queried_object' ) ? $wp_query->queried_object : null,
			'queried_object_id' => property_exists( $wp_query, 'queried_object_id' ) ? $wp_query->queried_object_id : null,
			$edit_property      => property_exists( $wp_query, $edit_property ) ? $wp_query->{$edit_property} : null,
		);
		$existing = array(
			'queried_object'    => property_exists( $wp_query, 'queried_object' ),
			'queried_object_id' => property_exists( $wp_query, 'queried_object_id' ),
			$edit_property      => property_exists( $wp_query, $edit_property ),
		);

		try {
			$wp_query->is_singular       = true;
			$wp_query->queried_object    = get_post( $post_id );
			$wp_query->queried_object_id = $post_id;
			$wp_query->{$edit_property}  = false;
			$this->assertTrue( call_user_func( $function ) );

			$wp_query->{$edit_property} = true;
			$this->assertFalse( call_user_func( $function ) );
		} finally {
			$wp_query->is_singular = $states['is_singular'];
			foreach ( $existing as $property => $had_value ) {
				if ( $had_value ) {
					$wp_query->{$property} = $states[ $property ];
				} else {
					unset( $wp_query->{$property} );
				}
			}
		}
	}

	public static function singular_predicate_provider() {
		return array(
			'forum' => array( 'bbp_is_single_forum', 'forum', 'bbp_is_forum_edit' ),
			'topic' => array( 'bbp_is_single_topic', 'topic', 'bbp_is_topic_edit' ),
			'reply' => array( 'bbp_is_single_reply', 'reply', 'bbp_is_reply_edit' ),
		);
	}

	/**
	 * @covers ::bbp_is_user_home_edit
	 */
	public function test_user_home_edit_requires_both_query_flags() {
		$wp_query = bbp_get_wp_query();
		$states   = array(
			'bbp_is_single_user_home' => property_exists( $wp_query, 'bbp_is_single_user_home' ) ? $wp_query->bbp_is_single_user_home : null,
			'bbp_is_single_user_edit' => property_exists( $wp_query, 'bbp_is_single_user_edit' ) ? $wp_query->bbp_is_single_user_edit : null,
		);
		$existing = array(
			'bbp_is_single_user_home' => property_exists( $wp_query, 'bbp_is_single_user_home' ),
			'bbp_is_single_user_edit' => property_exists( $wp_query, 'bbp_is_single_user_edit' ),
		);

		try {
			$wp_query->bbp_is_single_user_home = true;
			$wp_query->bbp_is_single_user_edit = false;
			$this->assertFalse( bbp_is_user_home_edit() );

			$wp_query->bbp_is_single_user_home = false;
			$wp_query->bbp_is_single_user_edit = true;
			$this->assertFalse( bbp_is_user_home_edit() );

			$wp_query->bbp_is_single_user_home = true;
			$wp_query->bbp_is_single_user_edit = true;
			$this->assertTrue( bbp_is_user_home_edit() );
		} finally {
			foreach ( $states as $property => $value ) {
				if ( $existing[ $property ] ) {
					$wp_query->{$property} = $value;
				} else {
					unset( $wp_query->{$property} );
				}
			}
		}
	}

	/**
	 * @covers ::bbp_is_topic_merge
	 * @covers ::bbp_is_topic_split
	 * @covers ::bbp_is_reply_move
	 * @dataProvider edit_action_predicate_provider
	 */
	public function test_edit_action_predicates_require_edit_state_and_matching_action( $function, $edit_property, $action, $other_action, $other_property ) {
		$wp_query = bbp_get_wp_query();
		$old_get  = $_GET;
		$states   = array(
			$edit_property  => property_exists( $wp_query, $edit_property ) ? $wp_query->{$edit_property} : null,
			$other_property => property_exists( $wp_query, $other_property ) ? $wp_query->{$other_property} : null,
		);
		$existing = array(
			$edit_property  => property_exists( $wp_query, $edit_property ),
			$other_property => property_exists( $wp_query, $other_property ),
		);

		try {
			$wp_query->{$edit_property}  = true;
			$wp_query->{$other_property} = false;
			unset( $_GET['action'] );
			$this->assertFalse( call_user_func( $function ) );

			$wp_query->{$edit_property} = false;
			$_GET['action'] = $action;
			$this->assertFalse( call_user_func( $function ) );

			$wp_query->{$edit_property} = true;
			$_GET['action'] = $other_action;
			$this->assertFalse( call_user_func( $function ) );

			$wp_query->{$edit_property}  = false;
			$wp_query->{$other_property} = true;
			$_GET['action']              = $action;
			$this->assertFalse( call_user_func( $function ) );

			$wp_query->{$edit_property}  = true;
			$wp_query->{$other_property} = false;
			$_GET['action'] = $action;
			$this->assertTrue( call_user_func( $function ) );
		} finally {
			$_GET = $old_get;
			foreach ( $states as $property => $value ) {
				if ( $existing[ $property ] ) {
					$wp_query->{$property} = $value;
				} else {
					unset( $wp_query->{$property} );
				}
			}
		}
	}

	public static function edit_action_predicate_provider() {
		return array(
			'topic merge' => array( 'bbp_is_topic_merge', 'bbp_is_topic_edit', 'merge', 'split', 'bbp_is_reply_edit' ),
			'topic split' => array( 'bbp_is_topic_split', 'bbp_is_topic_edit', 'split', 'merge', 'bbp_is_reply_edit' ),
			'reply move'  => array( 'bbp_is_reply_move', 'bbp_is_reply_edit', 'move', 'merge', 'bbp_is_topic_edit' ),
		);
	}

	/**
	 * @covers ::bbp_is_topic_tag
	 * @covers ::bbp_is_topic_tag_edit
	 */
	public function test_topic_tag_predicates_use_query_state_and_respect_feature_setting() {
		$wp_query      = bbp_get_wp_query();
		$query_var     = 'bbp_topic_tag';
		$edit_property = 'bbp_is_topic_tag_edit';
		$had_query_var = array_key_exists( $query_var, $wp_query->query_vars );
		$old_query_var = $had_query_var ? $wp_query->query_vars[ $query_var ] : null;
		$had_edit      = property_exists( $wp_query, $edit_property );
		$old_edit      = $had_edit ? $wp_query->{$edit_property} : null;

		add_filter( 'bbp_allow_topic_tags', '__return_true' );

		try {
			$wp_query->set( $query_var, '' );
			$wp_query->{$edit_property} = false;
			$this->assertFalse( bbp_is_topic_tag() );
			$this->assertFalse( bbp_is_topic_tag_edit() );

			$wp_query->set( $query_var, 'unit-test-tag' );
			$this->assertTrue( bbp_is_topic_tag() );
			add_filter( 'bbp_is_topic_tag', '__return_false' );
			$this->assertFalse( bbp_is_topic_tag() );
			remove_filter( 'bbp_is_topic_tag', '__return_false' );

			$wp_query->{$edit_property} = 1;
			$this->assertFalse( bbp_is_topic_tag_edit() );

			$wp_query->{$edit_property} = true;
			$this->assertTrue( bbp_is_topic_tag_edit() );
			add_filter( 'bbp_is_topic_tag_edit', '__return_false' );
			$this->assertFalse( bbp_is_topic_tag_edit() );
			remove_filter( 'bbp_is_topic_tag_edit', '__return_false' );
			$this->assertFalse( bbp_is_topic_tag() );

			add_filter( 'bbp_allow_topic_tags', '__return_false', 20 );
			$this->assertFalse( bbp_is_topic_tag() );
			$this->assertFalse( bbp_is_topic_tag_edit() );
		} finally {
			remove_filter( 'bbp_is_topic_tag_edit', '__return_false' );
			remove_filter( 'bbp_is_topic_tag', '__return_false' );
			remove_filter( 'bbp_allow_topic_tags', '__return_false', 20 );
			remove_filter( 'bbp_allow_topic_tags', '__return_true' );
			if ( $had_query_var ) {
				$wp_query->set( $query_var, $old_query_var );
			} else {
				unset( $wp_query->query_vars[ $query_var ] );
			}
			if ( $had_edit ) {
				$wp_query->{$edit_property} = $old_edit;
			} else {
				unset( $wp_query->{$edit_property} );
			}
		}
	}

	/**
	 * @covers ::bbp_is_topic_tag
	 * @covers ::bbp_is_topic_tag_edit
	 */
	public function test_topic_tag_predicate_filters_can_override_query_state() {
		$tag_filter  = '__return_true';
		$edit_filter = '__return_true';

		add_filter( 'bbp_allow_topic_tags', '__return_true' );
		add_filter( 'bbp_is_topic_tag', $tag_filter );

		try {
			$this->assertTrue( bbp_is_topic_tag() );
			remove_filter( 'bbp_is_topic_tag', $tag_filter );
			add_filter( 'bbp_is_topic_tag_edit', $edit_filter );
			$this->assertTrue( bbp_is_topic_tag_edit() );
		} finally {
			remove_filter( 'bbp_is_topic_tag_edit', $edit_filter );
			remove_filter( 'bbp_is_topic_tag', $tag_filter );
			remove_filter( 'bbp_allow_topic_tags', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_is_search
	 * @covers ::bbp_is_search_results
	 */
	public function test_search_predicates_use_query_name_query_state_and_request() {
		$wp_query       = bbp_get_wp_query();
		$rewrite_id     = bbp_get_search_rewrite_id();
		$old_name       = bbp_get_query_name();
		$old_request    = $_REQUEST;
		$search_states  = array(
			'bbp_is_search'    => property_exists( $wp_query, 'bbp_is_search' ) ? $wp_query->bbp_is_search : null,
			'bbp_search_terms' => property_exists( $wp_query, 'bbp_search_terms' ) ? $wp_query->bbp_search_terms : null,
		);
		$search_exists  = array(
			'bbp_is_search'    => property_exists( $wp_query, 'bbp_is_search' ),
			'bbp_search_terms' => property_exists( $wp_query, 'bbp_search_terms' ),
		);

		add_filter( 'bbp_allow_search', '__return_true' );

		try {
			bbp_reset_query_name();
			$wp_query->bbp_is_search    = false;
			$wp_query->bbp_search_terms = '';
			unset( $_REQUEST[ $rewrite_id ] );
			$this->assertFalse( bbp_is_search() );
			$this->assertFalse( bbp_is_search_results() );

			$wp_query->bbp_is_search = 1;
			$this->assertFalse( bbp_is_search() );

			$wp_query->bbp_is_search = true;
			$this->assertTrue( bbp_is_search() );

			$wp_query->bbp_is_search = false;
			bbp_set_query_name( $rewrite_id );
			$this->assertTrue( bbp_is_search() );

			bbp_reset_query_name();
			$_REQUEST[ $rewrite_id ] = '';
			$this->assertTrue( bbp_is_search() );

			unset( $_REQUEST[ $rewrite_id ] );
			$wp_query->bbp_search_terms = 'query terms';
			$this->assertTrue( bbp_is_search_results() );

			$wp_query->bbp_search_terms = '';
			bbp_set_query_name( 'bbp_search_results' );
			$this->assertTrue( bbp_is_search_results() );

			bbp_reset_query_name();
			$_REQUEST[ $rewrite_id ] = 'request terms';
			$this->assertTrue( bbp_is_search_results() );
		} finally {
			remove_filter( 'bbp_allow_search', '__return_true' );
			bbp_set_query_name( $old_name );
			$_REQUEST = $old_request;
			foreach ( $search_states as $property => $value ) {
				if ( $search_exists[ $property ] ) {
					$wp_query->{$property} = $value;
				} else {
					unset( $wp_query->{$property} );
				}
			}
		}
	}

	/**
	 * @covers ::bbp_is_search
	 * @covers ::bbp_is_search_results
	 */
	public function test_search_predicates_respect_feature_setting_and_filters() {
		$wp_query      = bbp_get_wp_query();
		$search_exists = property_exists( $wp_query, 'bbp_is_search' );
		$results_exist = property_exists( $wp_query, 'bbp_search_terms' );
		$old_search    = $search_exists ? $wp_query->bbp_is_search : null;
		$old_results   = $results_exist ? $wp_query->bbp_search_terms : null;

		try {
			$wp_query->bbp_is_search    = true;
			$wp_query->bbp_search_terms = 'query terms';
			add_filter( 'bbp_allow_search', '__return_true' );
			add_filter( 'bbp_is_search', '__return_false' );
			add_filter( 'bbp_is_search_results', '__return_false' );

			$this->assertFalse( bbp_is_search() );
			$this->assertFalse( bbp_is_search_results() );

			remove_filter( 'bbp_is_search', '__return_false' );
			remove_filter( 'bbp_is_search_results', '__return_false' );
			add_filter( 'bbp_allow_search', '__return_false', 20 );
			add_filter( 'bbp_is_search', '__return_true' );
			add_filter( 'bbp_is_search_results', '__return_true' );
			$this->assertFalse( bbp_is_search() );
			$this->assertFalse( bbp_is_search_results() );
		} finally {
			remove_filter( 'bbp_is_search_results', '__return_true' );
			remove_filter( 'bbp_is_search', '__return_true' );
			remove_filter( 'bbp_allow_search', '__return_false', 20 );
			remove_filter( 'bbp_is_search_results', '__return_false' );
			remove_filter( 'bbp_is_search', '__return_false' );
			remove_filter( 'bbp_allow_search', '__return_true' );
			if ( $search_exists ) {
				$wp_query->bbp_is_search = $old_search;
			} else {
				unset( $wp_query->bbp_is_search );
			}
			if ( $results_exist ) {
				$wp_query->bbp_search_terms = $old_results;
			} else {
				unset( $wp_query->bbp_search_terms );
			}
		}
	}

	/**
	 * @covers ::bbp_has_shortcode
	 */
	public function test_has_shortcode_detects_registered_codes_and_passes_filter_context() {
		$codes = array_keys( bbpress()->shortcodes->codes );
		$this->assertGreaterThanOrEqual( 2, count( $codes ) );

		$found_codes = array_slice( $codes, 0, 2 );
		$text        = 'Before [' . $found_codes[0] . '] between [' . $found_codes[1] . '] after';
		$called = 0;
		$filter = function( $retval, $found, $filtered_text ) use ( &$called, $found_codes, $text ) {
			++$called;
			$this->assertTrue( $retval );
			$this->assertSame( $found_codes, $found );
			$this->assertSame( $text, $filtered_text );
			return false;
		};

		$this->assertFalse( bbp_has_shortcode( '' ) );
		$this->assertFalse( bbp_has_shortcode( '[gallery]' ) );
		$this->assertFalse( bbp_has_shortcode( '[bbp-not-registered]' ) );
		$this->assertTrue( bbp_has_shortcode( $text ) );

		add_filter( 'bbp_has_shortcode', $filter, 10, 3 );
		try {
			$this->assertFalse( bbp_has_shortcode( $text ) );
			$this->assertSame( 1, $called );
		} finally {
			remove_filter( 'bbp_has_shortcode', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_has_shortcode
	 */
	public function test_has_shortcode_uses_singular_global_post_content() {
		global $post;

		$codes        = array_keys( bbpress()->shortcodes->codes );
		$code         = reset( $codes );
		$post_id      = $this->factory->post->create(
			array( 'post_content' => '[' . $code . ']' )
		);
		$plain_id     = $this->factory->post->create( array( 'post_content' => 'No shortcode' ) );
		$old_post     = $post;
		$wp_query     = bbp_get_wp_query();
		$old_singular = $wp_query->is_singular;

		try {
			$post                  = get_post( $post_id );
			$wp_query->is_singular = false;
			$this->assertFalse( bbp_has_shortcode() );

			$wp_query->is_singular = true;
			$this->assertTrue( bbp_has_shortcode() );

			$post = get_post( $plain_id );
			$this->assertFalse( bbp_has_shortcode() );
		} finally {
			$post                  = $old_post;
			$wp_query->is_singular = $old_singular;
		}
	}

	private function create_bbp_post( $factory_name ) {
		if ( 'reply' === $factory_name ) {
			$topic_id = $this->factory->topic->create();
			return $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		}

		return $this->factory->{$factory_name}->create();
	}
}
