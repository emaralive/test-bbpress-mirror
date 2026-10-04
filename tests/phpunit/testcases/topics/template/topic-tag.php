<?php

/**
 * Tests for the topic tag template functions.
 *
 * @group topics
 * @group topic_tags
 * @group template
 * @group topic
 * @group topic_tag
 */
class BBP_Tests_Topic_Tags_Template_Topic_Tag extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_topic_tag_list
	 * @covers ::bbp_get_topic_tag_list
	 * @todo   Implement test_bbp_get_topic_tag_list().
	 */
	public function test_bbp_get_topic_tag_list() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_tag_tax_id
	 * @covers ::bbp_get_topic_tag_tax_id
	 * @todo   Implement test_bbp_get_topic_tag_tax_id().
	 */
	public function test_bbp_get_topic_tag_tax_id() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_topic_tag_tax_labels
	 * @todo   Implement test_bbp_get_topic_tag_tax_labels().
	 */
	public function test_bbp_get_topic_tag_tax_labels() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_topic_tag_tax_rewrite
	 * @todo   Implement test_bbp_get_topic_tag_tax_rewrite().
	 */
	public function test_bbp_get_topic_tag_tax_rewrite() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_tag_id
	 * @covers ::bbp_get_topic_tag_id
	 * @todo   Implement test_bbp_get_topic_tag_id().
	 */
	public function test_bbp_get_topic_tag_id() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_tag_name
	 * @covers ::bbp_get_topic_tag_name
	 */
	public function test_bbp_get_topic_tag_name() {
		$term = wp_insert_term(
			'Rock & Roll\'s &lt;tag&gt;',
			bbp_get_topic_tag_tax_id(),
			array( 'slug' => 'rock-and-roll' )
		);

		$this->assertFalse( is_wp_error( $term ) );
		$topic_tag_name = bbp_get_topic_tag_name( 'rock-and-roll' );

		$this->assertSame( 'Rock &amp; Roll&#039;s &lt;tag&gt;', $topic_tag_name );
		$this->assertSame( 'Rock &amp; Roll&#039;s &lt;tag&gt;', esc_attr( $topic_tag_name ) );

		$filter = static function() {
			return '<img src=x onerror=alert(document.domain)>';
		};

		add_filter( 'bbp_get_topic_tag_name', $filter, 9 );
		$topic_tag_name = bbp_get_topic_tag_name( 'rock-and-roll' );
		remove_filter( 'bbp_get_topic_tag_name', $filter, 9 );

		$this->assertSame( '&lt;img src=x onerror=alert(document.domain)&gt;', $topic_tag_name );
	}

	/**
	 * @covers ::bbp_topic_tag_slug
	 * @covers ::bbp_get_topic_tag_slug
	 * @todo   Implement test_bbp_get_topic_tag_slug().
	 */
	public function test_bbp_get_topic_tag_slug() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_tag_link
	 * @covers ::bbp_get_topic_tag_link
	 * @todo   Implement test_bbp_get_topic_tag_link().
	 */
	public function test_bbp_get_topic_tag_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_tag_edit_link
	 * @covers ::bbp_get_topic_tag_edit_link
	 */
	public function test_bbp_get_topic_tag_edit_link() {
		$term = wp_insert_term( 'Alpha', bbp_get_topic_tag_tax_id(), array( 'slug' => 'alpha' ) );
		$this->assertFalse( is_wp_error( $term ) );
		$link = bbp_get_topic_tag_link( 'alpha' );
		$this->assertSame( '', bbp_get_topic_tag_edit_link( 'missing-tag' ) );

		add_filter( 'bbp_pretty_urls', '__return_false' );
		try {
			$edit_link = add_query_arg( array( bbp_get_edit_rewrite_id() => '1' ), $link );
			$this->assertSame( $edit_link, bbp_get_topic_tag_edit_link( 'alpha' ) );
			$this->expectOutputString( esc_url( $edit_link ) );
			bbp_topic_tag_edit_link( 'alpha' );
			remove_filter( 'bbp_pretty_urls', '__return_false' );
			add_filter( 'bbp_pretty_urls', '__return_true' );
			$this->assertSame( user_trailingslashit( trailingslashit( $link ) . bbp_get_edit_slug() ), bbp_get_topic_tag_edit_link( 'alpha' ) );
		} finally {
			remove_filter( 'bbp_pretty_urls', '__return_false' );
			remove_filter( 'bbp_pretty_urls', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_topic_tag_description
	 * @covers ::bbp_get_topic_tag_description
	 */
	public function test_bbp_get_topic_tag_description() {
		$term = wp_insert_term( 'Alpha', bbp_get_topic_tag_tax_id(), array( 'slug' => 'alpha', 'description' => 'Alpha description' ) );
		$this->assertFalse( is_wp_error( $term ) );
		$args = array( 'tag' => 'alpha', 'before' => '<p>', 'after' => '</p>' );
		$seen = array();
		$filter = function ( $description, $parsed, $original, $tag, $term ) use ( &$seen ) {
			$seen[] = array( $tag, $term->term_id );
			return $description;
		};
		add_filter( 'bbp_get_topic_tag_description', $filter, 10, 5 );

		try {
			$this->assertSame( '<p>Alpha description</p>', bbp_get_topic_tag_description( $args ) );
			$this->assertSame( array( array( 'alpha', $term['term_id'] ) ), $seen );
			$this->expectOutputString( '<p>Alpha description</p>' );
			bbp_topic_tag_description( $args );
		} finally {
			remove_filter( 'bbp_get_topic_tag_description', $filter, 10 );
		}
	}
}
