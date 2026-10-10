<?php
/**
 * Tests for common template functions.
 *
 * @group common
 * @group template
 */

class BBP_Tests_Common_Template extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_is_topic_merge
	 */
	public function test_topic_merge_respects_filter_on_matching_request() {
		$wp_query  = bbp_get_wp_query();
		$old_get   = $_GET;
		$had_state = property_exists( $wp_query, 'bbp_is_topic_edit' );
		$old_state = $had_state ? $wp_query->bbp_is_topic_edit : null;

		try {
			$wp_query->bbp_is_topic_edit = true;
			$_GET['action'] = 'merge';
			$this->assertTrue( bbp_is_topic_merge() );

			add_filter( 'bbp_is_topic_merge', '__return_false' );
			$this->assertFalse( bbp_is_topic_merge() );
		} finally {
			remove_filter( 'bbp_is_topic_merge', '__return_false' );
			$_GET = $old_get;
			if ( $had_state ) {
				$wp_query->bbp_is_topic_edit = $old_state;
			} else {
				unset( $wp_query->bbp_is_topic_edit );
			}
		}
	}

	/**
	 * @covers ::bbp_body_class
	 * @dataProvider body_class_specific_state_provider
	 */
	public function test_body_class_uses_specific_and_general_state_classes( $properties, $action, $expected, $unexpected, $specific_predicate ) {
		$wp_query   = bbp_get_wp_query();
		$old_name   = bbp_get_query_name();
		$old_get     = $_GET;
		$old_request = $_REQUEST;
		$old_values  = array();
		$old_exists  = array();

		foreach ( $properties as $property => $value ) {
			$old_exists[ $property ] = property_exists( $wp_query, $property );
			$old_values[ $property ] = $old_exists[ $property ] ? $wp_query->{$property} : null;
		}

		add_filter( 'bbp_allow_search', '__return_true' );

		try {
			bbp_reset_query_name();
			unset( $_REQUEST[ bbp_get_search_rewrite_id() ] );
			foreach ( $properties as $property => $value ) {
				$wp_query->{$property} = $value;
			}

			if ( null === $action ) {
				unset( $_GET['action'] );
			} else {
				$_GET['action'] = $action;
			}

			if ( null !== $specific_predicate ) {
				$this->assertTrue( call_user_func( $specific_predicate ) );
			}

			$classes = bbp_body_class( array( 'existing', 'bbpress' ) );
			foreach ( $expected as $class ) {
				$this->assertContains( $class, $classes );
			}
			foreach ( $unexpected as $class ) {
				$this->assertNotContains( $class, $classes );
			}
			$this->assertContains( 'existing', $classes );
			$this->assertContains( 'bbpress', $classes );
			$this->assertContains( 'bbp-no-js', $classes );
			$this->assertSame( 1, array_count_values( $classes )['bbpress'] );
		} finally {
			remove_filter( 'bbp_allow_search', '__return_true' );
			bbp_set_query_name( $old_name );
			$_GET = $old_get;
			$_REQUEST = $old_request;
			foreach ( $old_values as $property => $value ) {
				if ( $old_exists[ $property ] ) {
					$wp_query->{$property} = $value;
				} else {
					unset( $wp_query->{$property} );
				}
			}
		}
	}

	public static function body_class_specific_state_provider() {
		return array(
			'topic merge' => array(
				array( 'bbp_is_topic_edit' => true ),
				'merge',
				array( bbp_get_topic_post_type() . '-merge', bbp_get_topic_post_type() . '-edit' ),
				array( bbp_get_topic_post_type() . '-split' ),
				'bbp_is_topic_merge',
			),
			'topic split' => array(
				array( 'bbp_is_topic_edit' => true ),
				'split',
				array( bbp_get_topic_post_type() . '-split', bbp_get_topic_post_type() . '-edit' ),
				array( bbp_get_topic_post_type() . '-merge' ),
				'bbp_is_topic_split',
			),
			'topic edit' => array(
				array( 'bbp_is_topic_edit' => true ),
				null,
				array( bbp_get_topic_post_type() . '-edit' ),
				array( bbp_get_topic_post_type() . '-merge', bbp_get_topic_post_type() . '-split' ),
				null,
			),
			'topic edit with reply action' => array(
				array( 'bbp_is_topic_edit' => true ),
				'move',
				array( bbp_get_topic_post_type() . '-edit' ),
				array( bbp_get_topic_post_type() . '-merge', bbp_get_topic_post_type() . '-split' ),
				null,
			),
			'reply move' => array(
				array( 'bbp_is_reply_edit' => true ),
				'move',
				array( bbp_get_reply_post_type() . '-move', bbp_get_reply_post_type() . '-edit' ),
				array(),
				'bbp_is_reply_move',
			),
			'reply edit' => array(
				array( 'bbp_is_reply_edit' => true ),
				null,
				array( bbp_get_reply_post_type() . '-edit' ),
				array( bbp_get_reply_post_type() . '-move' ),
				null,
			),
			'reply edit with topic action' => array(
				array( 'bbp_is_reply_edit' => true ),
				'merge',
				array( bbp_get_reply_post_type() . '-edit' ),
				array( bbp_get_reply_post_type() . '-move' ),
				null,
			),
			'user home edit' => array(
				array(
					'bbp_is_single_user_home' => true,
					'bbp_is_single_user_edit' => true,
					'bbp_is_single_user'      => true,
				),
				null,
				array( 'bbp-user-home-edit', 'bbp-user-edit', 'single', 'singular' ),
				array( 'bbp-user-page', 'bbp-user-home' ),
				'bbp_is_user_home_edit',
			),
			'user edit' => array(
				array(
					'bbp_is_single_user_home' => false,
					'bbp_is_single_user_edit' => true,
					'bbp_is_single_user'      => true,
				),
				null,
				array( 'bbp-user-edit', 'single', 'singular' ),
				array( 'bbp-user-home-edit' ),
				null,
			),
			'search results' => array(
				array(
					'bbp_is_search'    => true,
					'bbp_search_terms' => 'query terms',
				),
				null,
				array( 'bbp-search-results', 'forum-search-results', 'bbp-search', 'forum-search' ),
				array(),
				'bbp_is_search_results',
			),
			'search results without search' => array(
				array(
					'bbp_is_search'    => false,
					'bbp_search_terms' => 'query terms',
				),
				null,
				array( 'bbp-search-results', 'forum-search-results' ),
				array( 'bbp-search', 'forum-search' ),
				'bbp_is_search_results',
			),
			'search' => array(
				array(
					'bbp_is_search'    => true,
					'bbp_search_terms' => '',
				),
				null,
				array( 'bbp-search', 'forum-search' ),
				array( 'bbp-search-results', 'forum-search-results' ),
				null,
			),
		);
	}
}
