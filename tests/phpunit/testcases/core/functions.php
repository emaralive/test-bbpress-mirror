<?php

/**
 * Tests to test that that testing framework is testing tests. Meta, huh?
 *
 * @package wordpress-plugins-tests
 */
class BBP_bbPress_Tests extends WP_UnitTestCase  {

	/**
	 * Ensure that bbPress function exists
	 */
	function test_bbpress_exists() {
		$this->assertTrue( function_exists( 'bbpress' ) );
	}

	/**
	 * Ensure that bbPress has been installed and activated.
	 */
	function test_plugin_activated() {
		$this->assertTrue( is_plugin_active( 'bbpress/bbpress.php' ) );
	}

	/**
	 * @covers ::bbp_view_query
	 */
	public function test_bbp_view_query_rejects_an_unregistered_view() {
		bbp_insert_topic(
			array(
				'post_title'   => 'View query regression topic',
				'post_content' => 'Topic content',
				'post_status'  => bbp_get_public_status_id(),
			)
		);

		$this->assertFalse( bbp_view_query( 'missing-view' ) );
	}

	/**
	 * @covers ::bbp_view_query
	 */
	public function test_bbp_view_query_preserves_registered_views_with_empty_filtered_arguments() {
		bbp_insert_topic(
			array(
				'post_title'   => 'Registered view topic',
				'post_content' => 'Topic content',
				'post_status'  => bbp_get_public_status_id(),
			)
		);

		bbp_register_view( 'all-topics', 'All Topics' );
		$filter = function( $args, $view ) {
			return ( 'all-topics' === $view )
				? array()
				: $args;
		};
		add_filter( 'bbp_get_view_query_args', $filter, 10, 2 );

		try {
			// Registered views may intentionally rely on the default topic query.
			$this->assertTrue( bbp_view_query( 'all-topics' ) );
		} finally {
			remove_filter( 'bbp_get_view_query_args', $filter, 10 );
			bbp_deregister_view( 'all-topics' );
		}
	}

	/**
	 * @covers ::bbp_view_query
	 */
	public function test_bbp_view_query_preserves_filtered_arguments_for_virtual_views() {
		$topic_id = bbp_insert_topic(
			array(
				'post_title'   => 'Virtual view topic',
				'post_content' => 'Topic content',
				'post_status'  => bbp_get_public_status_id(),
			)
		);
		$filter   = function( $args, $view ) use ( $topic_id ) {
			if ( 'virtual-view' === $view ) {
				$args = array(
					'post_type' => bbp_get_topic_post_type(),
					'post__in'  => array( $topic_id ),
				);
			}

			return $args;
		};

		add_filter( 'bbp_get_view_query_args', $filter, 10, 2 );
		try {
			$this->assertTrue( bbp_view_query( 'virtual-view' ) );
		} finally {
			remove_filter( 'bbp_get_view_query_args', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_is_post_request
	 * @covers ::bbp_is_get_request
	 */
	public function test_request_helpers_return_false_when_the_method_is_unavailable() {
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? $_SERVER['REQUEST_METHOD']
			: null;

		unset( $_SERVER['REQUEST_METHOD'] );

		try {
			$this->assertFalse( bbp_is_post_request() );
			$this->assertFalse( bbp_is_get_request() );
		} finally {
			if ( null !== $method ) {
				$_SERVER['REQUEST_METHOD'] = $method;
			}
		}
	}
}
