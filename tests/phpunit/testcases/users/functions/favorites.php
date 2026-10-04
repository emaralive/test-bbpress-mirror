<?php

/**
 * Tests for the user component favorite functions.
 *
 * @group users
 * @group functions
 * @group favorites
 */
class BBP_Tests_Users_Functions_Favorites extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_get_topic_favoriters
	 */
	public function test_bbp_get_topic_favoriters() {
		$u = $this->factory->user->create_many( 3 );
		$t = $this->factory->topic->create();

		// Add topic favorites.
		bbp_add_user_favorite( $u[0], $t );
		bbp_add_user_favorite( $u[1], $t );

		$expected = array( $u[0], $u[1] );
		$favoriters = bbp_get_topic_favoriters( $t );

		$this->assertEqualSets( $expected, $favoriters );

		// Add topic favorites.
		bbp_add_user_favorite( $u[2], $t );

		$expected = array( $u[0], $u[1], $u[2] );
		$favoriters = bbp_get_topic_favoriters( $t );

		$this->assertEqualSets( $expected, $favoriters );

		// Remove user favorite.
		bbp_remove_user_favorite( $u[1], $t );

		$expected = array( $u[0], $u[2] );
		$favoriters = bbp_get_topic_favoriters( $t );

		$this->assertEqualSets( $expected, $favoriters );
	}

	/**
	 * @covers ::bbp_get_user_favorites
	 */
	public function test_bbp_get_user_favorites() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create_many( 3 );

		// Add topic favorites.
		bbp_add_user_favorite( $u, $t[0] );
		bbp_add_user_favorite( $u, $t[1] );
		bbp_add_user_favorite( $u, $t[2] );

		$expected = bbp_has_topics( array( 'post__in' => array( $t[0], $t[1], $t[2] ) ) );
		$favorites = bbp_get_user_favorites( $u );

		$this->assertEquals( $expected, $favorites );

		// Remove user favorite.
		bbp_remove_user_favorite( $u, $t[1] );

		$expected = bbp_has_topics( array( 'post__in' => array( $t[0], $t[2] ) ) );
		$favorites = bbp_get_user_favorites( $u );

		$this->assertEquals( $expected, $favorites );
	}

	/**
	 * @covers ::bbp_get_user_favorites_topic_ids
	 */
	public function test_bbp_get_user_favorites_topic_ids() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create_many( 3 );

		// Add topic favorites.
		bbp_add_user_favorite( $u, $t[0] );
		bbp_add_user_favorite( $u, $t[1] );
		bbp_add_user_favorite( $u, $t[2] );

		$favorites = bbp_get_user_favorites_topic_ids( $u );

		$this->assertEqualSets( array( $t[0], $t[1], $t[2] ), $favorites );

		// Remove user favorite.
		bbp_remove_user_favorite( $u, $t[1] );

		$favorites = bbp_get_user_favorites_topic_ids( $u );

		$this->assertEqualSets( array( $t[0], $t[2] ), $favorites );
	}

	/**
	 * @covers ::bbp_is_user_favorite
	 */
	public function test_bbp_is_user_favorite() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create();

		$favorite = bbp_is_user_favorite( $u, $t );

		$this->assertFalse( $favorite );

		// Add topic favorite.
		bbp_add_user_favorite( $u, $t );

		$favorite = bbp_is_user_favorite( $u, $t );

		$this->assertTrue( $favorite );
	}

	/**
	 * @covers ::bbp_add_user_favorite
	 */
	public function test_bbp_add_user_favorite() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create_many( 3 );

		// Add topic favorites.
		add_post_meta( $t[0], '_bbp_favorite', $u, false );

		// Add user favorite.
		bbp_add_user_favorite( $u, $t[1] );

		$favorites = bbp_get_user_favorites_topic_ids( $u );

		$this->assertEqualSets( array( $t[0], $t[1] ), $favorites );

		// Add user favorite.
		bbp_add_user_favorite( $u, $t[2] );

		$favorites = bbp_get_user_favorites_topic_ids( $u );

		$this->assertEqualSets( array( $t[0], $t[1], $t[2] ), $favorites );
	}

	/**
	 * @covers ::bbp_remove_user_favorite
	 */
	public function test_bbp_remove_user_favorite() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create_many( 3 );

		// Add topic favorites.
		foreach ( $t as $topic ) {
			add_post_meta( $topic, '_bbp_favorite', $u );
		}

		// Remove user favorite.
		bbp_remove_user_favorite( $u, $t[2] );

		$favorites = bbp_get_user_favorites_topic_ids( $u );

		$this->assertEqualSets( array( $t[0], $t[1] ), $favorites );

		// Remove user favorite.
		bbp_remove_user_favorite( $u, $t[1] );

		$favorites = bbp_get_user_favorites_topic_ids( $u );

		$this->assertEqualSets( array( $t[0] ), $favorites );
	}

	/**
	 * @covers ::bbp_favorites_handler
	 * @todo   Cover successful toggles in an integration test because bbp_redirect() exits.
	 */
	public function test_bbp_favorites_handler() {
		$old_get     = $_GET;
		$old_request = $_REQUEST;
		$old_user    = get_current_user_id();
		$user_id     = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$forum_id    = $this->factory->forum->create();
		$topic_id    = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		try {
			unset( $_GET['object_id'] );
			$this->assertFalse( bbp_favorites_handler( 'bbp_favorite_add' ) );

			$_GET['object_id'] = $topic_id;
			$this->assertFalse( bbp_favorites_handler( 'unsupported' ) );

			$_REQUEST['_wpnonce'] = 'invalid';
			$this->assertFalse( bbp_favorites_handler( 'bbp_favorite_add' ) );
			$this->assertSame( 'bbp_favorite_nonce', bbpress()->errors->get_error_code() );
			$this->assertFalse( bbp_is_user_favorite( $user_id, $topic_id ) );
		} finally {
			bbpress()->errors->remove( 'bbp_favorite_nonce' );
			$_GET = $old_get;
			$_REQUEST = $old_request;
			$this->set_current_user( $old_user );
		}
	}
}
