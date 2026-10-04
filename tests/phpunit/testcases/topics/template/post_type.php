<?php

/**
 * Tests for the `bbp_*_form_topic_post_type_*()` functions.
 *
 * @group topics
 * @group template
 * @group post_type
 */
class BBP_Tests_Topics_Template_Post_Type extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_topic_post_type
	 * @covers ::bbp_get_topic_post_type
	 */
	public function test_bbp_topic_post_type() {
		$t = $this->factory->topic->create();

		$tobj = get_post_type_object( 'topic' );

		// WordPress 4.6 introduced `WP_Post_Type` class
		if ( bbp_get_major_wp_version() < 4.6 ) {
			$this->assertInstanceOf( 'stdClass', $tobj );
		} else {
			$this->assertInstanceOf( 'WP_Post_Type', $tobj );
		}

		$this->assertEquals( 'topic', $tobj->name );

		// Test some defaults
		$this->assertFalse( is_post_type_hierarchical( 'topic' ) );

		$topic_type = bbp_topic_post_type( $t );
		$this->expectOutputString( 'topic', $topic_type );

		$topic_type = bbp_get_topic_post_type( $t );
		$this->assertSame( 'topic', $topic_type );
	}

	/**
	 * @covers ::bbp_get_topic_post_type_labels
	 */
	public function test_bbp_get_topic_post_type_labels() {
		$labels = bbp_get_topic_post_type_labels();

		$this->assertSame( 'Topics', $labels['name'] );
		$this->assertSame( 'Topic', $labels['singular_name'] );
		$this->assertSame( 'Add Topic', $labels['add_new_item'] );
		$this->assertSame( 'Forum:', $labels['parent_item_colon'] );
		$this->assertSame( 'Topic updated.', $labels['item_updated'] );
	}

	/**
	 * @covers ::bbp_get_topic_post_type_rewrite
	 */
	public function test_bbp_get_topic_post_type_rewrite() {
		$this->assertSame( array(
			'slug'       => bbp_get_topic_slug(),
			'with_front' => false,
		), bbp_get_topic_post_type_rewrite() );
	}

	/**
	 * @covers ::bbp_get_topic_post_type_supports
	 */
	public function test_bbp_get_topic_post_type_supports() {
		$this->assertSame( array( 'title', 'editor', 'revisions' ), bbp_get_topic_post_type_supports() );
	}
}
