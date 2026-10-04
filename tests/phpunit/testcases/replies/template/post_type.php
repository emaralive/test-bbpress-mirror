<?php

/**
 * Tests for the `bbp_*_form_reply_post_type_*()` functions.
 *
 * @group replies
 * @group template
 * @group post_type
 */
class BBP_Tests_Replies_Template_Post_Type extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_reply_post_type
	 * @covers ::bbp_get_reply_post_type
	 */
	public function test_bbp_reply_post_type() {
		$t = $this->factory->topic->create();

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'topic_id' => $t,
			),
		) );

		$robj = get_post_type_object( 'reply' );

		// WordPress 4.6 introduced `WP_Post_Type` class
		if ( bbp_get_major_wp_version() < 4.6 ) {
			$this->assertInstanceOf( 'stdClass', $robj );
		} else {
			$this->assertInstanceOf( 'WP_Post_Type', $robj );
		}

		$this->assertEquals( 'reply', $robj->name );

		// Test some defaults
		$this->assertFalse( is_post_type_hierarchical( 'topic' ) );
		$reply_type = bbp_reply_post_type( $r );
		$this->expectOutputString( 'reply', $reply_type );

		$reply_type = bbp_get_reply_post_type( $r );
		$this->assertSame( 'reply', $reply_type );
	}

	/**
	 * @covers ::bbp_get_reply_post_type_labels
	 */
	public function test_bbp_get_reply_post_type_labels() {
		$labels = bbp_get_reply_post_type_labels();

		$this->assertSame( 'Replies', $labels['name'] );
		$this->assertSame( 'Reply', $labels['singular_name'] );
		$this->assertSame( 'Add Reply', $labels['add_new_item'] );
		$this->assertSame( 'Parent Topic:', $labels['parent_item_colon'] );
		$this->assertSame( 'Reply updated.', $labels['item_updated'] );
	}

	/**
	 * @covers ::bbp_get_reply_post_type_rewrite
	 */
	public function test_bbp_get_reply_post_type_rewrite() {
		$this->assertSame( array(
			'slug'       => bbp_get_reply_slug(),
			'with_front' => false,
		), bbp_get_reply_post_type_rewrite() );
	}

	/**
	 * @covers ::bbp_get_reply_post_type_supports
	 */
	public function test_bbp_get_reply_post_type_supports() {
		$this->assertSame( array( 'title', 'editor', 'revisions' ), bbp_get_reply_post_type_supports() );
	}
}
