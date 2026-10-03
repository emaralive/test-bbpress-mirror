<?php

/**
 * Tests for the forum loop functions.
 *
 * @group forums
 * @group template
 * @group loop
 */
class BBP_Tests_Forums_Template_Forum_Loop extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_has_forums
	 */
	public function test_bbp_has_forums() {
		$root_id  = $this->factory->forum->create( array( 'post_title' => 'Root forum' ) );
		$child_id = $this->factory->forum->create( array(
			'post_title'  => 'Child forum',
			'post_parent' => $root_id,
		) );
		$bbp = bbpress();
		$previous_query = $bbp->forum_query;

		try {
			$this->assertTrue( bbp_has_forums() );
			$this->assertInstanceOf( 'WP_Query', $bbp->forum_query );
			$this->assertSame( bbp_get_forum_post_type(), $bbp->forum_query->get( 'post_type' ) );
			$this->assertSame( 0, $bbp->forum_query->get( 'post_parent' ) );
			$this->assertContains( bbp_get_public_status_id(), (array) $bbp->forum_query->get( 'post_status' ) );
			$this->assertSame( array( $root_id ), wp_list_pluck( $bbp->forum_query->posts, 'ID' ) );

			$this->assertTrue( bbp_has_forums( array( 'post_parent' => $root_id ) ) );
			$this->assertSame( array( $child_id ), wp_list_pluck( $bbp->forum_query->posts, 'ID' ) );

			$this->assertFalse( bbp_has_forums( array( 'post_parent' => -1 ) ) );
			$this->assertSame( array(), $bbp->forum_query->posts );
		} finally {
			$bbp->forum_query = $previous_query;
		}
	}

	/**
	 * @covers ::bbp_has_forums
	 */
	public function test_bbp_has_forums_uses_forum_search_request() {
		$matching_id = $this->factory->forum->create( array( 'post_title' => 'Needle forum' ) );
		$this->factory->forum->create( array( 'post_title' => 'Other forum' ) );
		$bbp = bbpress();
		$previous_query = $bbp->forum_query;
		$had_search = array_key_exists( 'fs', $_REQUEST );
		$previous_search = $had_search ? $_REQUEST['fs'] : null;
		$_REQUEST['fs'] = 'Needle';

		try {
			$this->assertTrue( bbp_has_forums() );
			$this->assertSame( 'Needle', $bbp->forum_query->get( 's' ) );
			$this->assertSame( array( $matching_id ), wp_list_pluck( $bbp->forum_query->posts, 'ID' ) );
		} finally {
			if ( $had_search ) {
				$_REQUEST['fs'] = $previous_search;
			} else {
				unset( $_REQUEST['fs'] );
			}
			$bbp->forum_query = $previous_query;
		}
	}

	/**
	 * @covers ::bbp_has_forums
	 */
	public function test_bbp_has_forums_filters_result_and_query() {
		$forum_id = $this->factory->forum->create();
		$bbp = bbpress();
		$previous_query = $bbp->forum_query;
		$filter = function( $has_forums, $query ) use ( $forum_id ) {
			$this->assertTrue( $has_forums );
			$this->assertInstanceOf( 'WP_Query', $query );
			$this->assertSame( array( $forum_id ), wp_list_pluck( $query->posts, 'ID' ) );
			return false;
		};
		add_filter( 'bbp_has_forums', $filter, 10, 2 );
		try {
			$this->assertFalse( bbp_has_forums() );
			$this->assertSame( array( $forum_id ), wp_list_pluck( $bbp->forum_query->posts, 'ID' ) );
		} finally {
			remove_filter( 'bbp_has_forums', $filter );
			$bbp->forum_query = $previous_query;
		}
	}

	/**
	 * @covers ::bbp_forums
	 */
	public function test_bbp_forums() {
		$forum_id = $this->factory->forum->create();
		$bbp = bbpress();
		$previous_query = $bbp->forum_query;

		try {
			$this->assertTrue( bbp_has_forums() );
			$this->assertTrue( bbp_forums() );
			bbp_the_forum();
			$this->assertSame( $forum_id, bbp_get_forum_id() );
			$this->assertFalse( bbp_forums() );
			$this->assertFalse( $bbp->forum_query->in_the_loop );
		} finally {
			wp_reset_postdata();
			$bbp->forum_query = $previous_query;
		}
	}

	/**
	 * @covers ::bbp_the_forum
	 */
	public function test_bbp_the_forum() {
		$first_id  = $this->factory->forum->create( array( 'post_title' => 'Alpha forum' ) );
		$second_id = $this->factory->forum->create( array( 'post_title' => 'Beta forum' ) );
		$bbp = bbpress();
		$previous_query = $bbp->forum_query;

		try {
			$this->assertTrue( bbp_has_forums() );
			$this->assertTrue( bbp_forums() );
			bbp_the_forum();
			$this->assertSame( $first_id, bbp_get_forum_id() );
			$this->assertSame( 0, $bbp->forum_query->current_post );

			$this->assertTrue( bbp_forums() );
			bbp_the_forum();
			$this->assertSame( $second_id, bbp_get_forum_id() );
			$this->assertSame( 1, $bbp->forum_query->current_post );
			$this->assertFalse( bbp_forums() );
		} finally {
			wp_reset_postdata();
			$bbp->forum_query = $previous_query;
		}
	}
}
