<?php

/**
 * Tests for the `bbp_*_form_forum_post_type_*()` functions.
 *
 * @group forums
 * @group template
 * @group post_type
 */
class BBP_Tests_Forums_Template_Post_Type extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_forum_post_type
	 * @covers ::bbp_get_forum_post_type
	 */
	public function test_bbp_get_forum_post_type() {
		$fobj = get_post_type_object( 'forum' );

		// WordPress 4.6 introduced `WP_Post_Type` class
		if ( bbp_get_major_wp_version() < 4.6 ) {
			$this->assertInstanceOf( 'stdClass', $fobj );
		} else {
			$this->assertInstanceOf( 'WP_Post_Type', $fobj );
		}

		$this->assertEquals( 'forum', $fobj->name );

		// Test some defaults
		$this->assertTrue( is_post_type_hierarchical( 'forum' ) );

		$this->assertSame( 'forum', bbp_get_forum_post_type() );
		$this->expectOutputString( 'forum' );
		bbp_forum_post_type();
	}

	/**
	 * @covers ::bbp_get_forum_post_type
	 */
	public function test_bbp_get_forum_post_type_filter() {
		$filter = function( $post_type ) {
			$this->assertSame( 'forum', $post_type );
			return 'custom-forum';
		};
		add_filter( 'bbp_get_forum_post_type', $filter );
		try {
			$this->assertSame( 'custom-forum', bbp_get_forum_post_type() );
		} finally {
			remove_filter( 'bbp_get_forum_post_type', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_forum_post_type_labels
	 */
	public function test_bbp_get_forum_post_type_labels() {
		$labels = bbp_get_forum_post_type_labels();

		$this->assertSame(
			array(
				'name', 'menu_name', 'singular_name', 'all_items', 'add_new',
				'add_new_item', 'edit', 'edit_item', 'new_item', 'view',
				'view_item', 'view_items', 'search_items', 'not_found',
				'not_found_in_trash', 'filter_items_list', 'items_list',
				'items_list_navigation', 'parent_item_colon', 'archives',
				'attributes', 'insert_into_item', 'uploaded_to_this_item',
				'featured_image', 'set_featured_image', 'remove_featured_image',
				'use_featured_image', 'item_published', 'item_published_privately',
				'item_reverted_to_draft', 'item_scheduled', 'item_updated'
			),
			array_keys( $labels )
		);
		$this->assertSame( 'Forums', $labels['name'] );
		$this->assertSame( 'Forum', $labels['singular_name'] );
		$this->assertSame( 'No forums found', $labels['not_found'] );
		$this->assertSame( 'Forum updated.', $labels['item_updated'] );

		$filter = function( $default ) use ( $labels ) {
			$this->assertSame( $labels, $default );
			return array( 'name' => 'Discussion areas' );
		};
		add_filter( 'bbp_get_forum_post_type_labels', $filter );
		try {
			$this->assertSame( array( 'name' => 'Discussion areas' ), bbp_get_forum_post_type_labels() );
		} finally {
			remove_filter( 'bbp_get_forum_post_type_labels', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_forum_post_type_rewrite
	 */
	public function test_bbp_get_forum_post_type_rewrite() {
		$this->assertSame(
			array( 'slug' => bbp_get_forum_slug(), 'with_front' => false ),
			bbp_get_forum_post_type_rewrite()
		);

		$filter = function( $rewrite ) {
			$this->assertSame( bbp_get_forum_slug(), $rewrite['slug'] );
			$this->assertFalse( $rewrite['with_front'] );
			$rewrite['slug'] = 'discussion-area';
			return $rewrite;
		};
		add_filter( 'bbp_get_forum_post_type_rewrite', $filter );
		try {
			$this->assertSame(
				array( 'slug' => 'discussion-area', 'with_front' => false ),
				bbp_get_forum_post_type_rewrite()
			);
		} finally {
			remove_filter( 'bbp_get_forum_post_type_rewrite', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_forum_post_type_supports
	 */
	public function test_bbp_get_forum_post_type_supports() {
		$this->assertSame( array( 'title', 'editor', 'revisions' ), bbp_get_forum_post_type_supports() );

		$filter = function( $supports ) {
			$this->assertSame( array( 'title', 'editor', 'revisions' ), $supports );
			return array( 'title', 'comments' );
		};
		add_filter( 'bbp_get_forum_post_type_supports', $filter );
		try {
			$this->assertSame( array( 'title', 'comments' ), bbp_get_forum_post_type_supports() );
		} finally {
			remove_filter( 'bbp_get_forum_post_type_supports', $filter );
		}
	}
}
