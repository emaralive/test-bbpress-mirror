<?php

/**
 * Tests for the search component search template functions.
 *
 * @group search
 * @group template
 */
class BBP_Tests_Search_Template_Search extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_has_search_results
	 */
	public function test_bbp_has_search_results() {
		$topic_id = $this->factory->topic->create( array( 'post_title' => 'UniqueSearchNeedle' ) );

		$this->assertTrue( bbp_has_search_results( array( 's' => 'UniqueSearchNeedle', 'post_type' => bbp_get_topic_post_type() ) ) );
		$this->assertSame( $topic_id, bbpress()->search_query->posts[0]->ID );
		$this->assertFalse( bbp_has_search_results( array( 's' => 'NoMatchingSearchNeedle', 'post_type' => bbp_get_topic_post_type() ) ) );
	}

	/**
	 * @covers ::bbp_search_results
	 */
	public function test_bbp_search_results() {
		$this->factory->topic->create( array( 'post_title' => 'UniqueSearchNeedle' ) );
		bbp_has_search_results( array( 's' => 'UniqueSearchNeedle', 'post_type' => bbp_get_topic_post_type() ) );

		$this->assertTrue( bbp_search_results() );
		bbp_the_search_result();
		$this->assertFalse( bbp_search_results() );
	}

	/**
	 * @covers ::bbp_the_search_result
	 */
	public function test_bbp_the_search_result() {
		$topic_id = $this->factory->topic->create( array( 'post_title' => 'UniqueSearchNeedle' ) );
		bbp_has_search_results( array( 's' => 'UniqueSearchNeedle', 'post_type' => bbp_get_topic_post_type() ) );

		$this->assertTrue( bbp_search_results() );
		bbp_the_search_result();
		$this->assertSame( $topic_id, get_the_ID() );
		$this->assertSame( $topic_id, bbpress()->current_topic_id );
		$this->assertFalse( bbp_search_results() );
	}

	/**
	 * @covers ::bbp_search_title
	 * @covers ::bbp_get_search_title
	 */
	public function test_bbp_get_search_title() {
		$this->assertSame( 'Search', bbp_get_search_title() );
		$filter = static function() {
			return 'sample query';
		};
		add_filter( 'bbp_get_search_terms', $filter );
		try {
			$title = bbp_get_search_title();
			$this->assertStringContainsString( 'sample query', $title );
			$this->expectOutputString( $title );
			bbp_search_title();
		} finally {
			remove_filter( 'bbp_get_search_terms', $filter );
		}
	}

	/**
	 * @covers ::bbp_search_url
	 * @covers ::bbp_get_search_url
	 */
	public function test_bbp_get_search_url() {
		$url = bbp_get_search_url();

		$this->assertStringContainsString( bbp_get_search_rewrite_id(), $url );
		$this->expectOutputString( esc_url( $url ) );
		bbp_search_url();
	}

	/**
	 * @covers ::bbp_search_results_url
	 * @covers ::bbp_get_search_results_url
	 */
	public function test_bbp_get_search_results_url() {
		$filter = static function() {
			return 'sample query';
		};
		add_filter( 'bbp_get_search_terms', $filter );
		try {
			$url = bbp_get_search_results_url();
			$this->assertStringContainsString( 'sample', $url );
			$this->expectOutputString( esc_url( $url ) );
			bbp_search_results_url();
		} finally {
			remove_filter( 'bbp_get_search_terms', $filter );
		}
	}

	/**
	 * @covers ::bbp_search_terms
	 * @covers ::bbp_get_search_terms
	 */
	public function test_bbp_get_search_terms() {
		$this->assertFalse( bbp_get_search_terms() );
		$this->assertSame( 'sample-query', bbp_get_search_terms( 'Sample Query' ) );
		$this->expectOutputString( 'sample-query' );
		bbp_search_terms( 'Sample Query' );
	}

	/**
	 * @covers ::bbp_search_pagination_count
	 * @covers ::bbp_get_search_pagination_count
	 */
	public function test_bbp_get_search_pagination_count() {
		$this->factory->topic->create_many( 2, array( 'post_title' => 'SearchPageNeedle' ) );
		bbp_has_search_results( array(
			's'              => 'SearchPageNeedle',
			'post_type'      => bbp_get_topic_post_type(),
			'posts_per_page' => 1,
		) );
		$count = bbp_get_search_pagination_count();

		$this->assertStringContainsString( 'Viewing', $count );
		$this->assertStringContainsString( '2 total', $count );
		$this->expectOutputString( $count );
		bbp_search_pagination_count();
	}

	/**
	 * @covers ::bbp_search_pagination_links
	 * @covers ::bbp_get_search_pagination_links
	 */
	public function test_bbp_get_search_pagination_links() {
		$this->factory->topic->create_many( 2, array( 'post_title' => 'SearchPageNeedle' ) );
		bbp_has_search_results( array(
			's'              => 'SearchPageNeedle',
			'post_type'      => bbp_get_topic_post_type(),
			'posts_per_page' => 1,
		) );
		$links = bbp_get_search_pagination_links();

		$this->assertNotEmpty( $links );
		$this->assertStringContainsString( 'page-numbers', $links );
		$this->expectOutputString( $links );
		bbp_search_pagination_links();
	}
}
