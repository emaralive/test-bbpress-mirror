<?php

/**
 * BuddyPress Member Forum tests.
 *
 * @group extend
 * @group buddypress
 * @group members
 */
class BBP_Tests_Extend_BuddyPress_Members extends BBP_UnitTestCase {

	protected $displayed_user;
	protected $current_action;
	protected $members;

	public function setUp(): void {
		parent::setUp();

		$this->displayed_user = isset( buddypress()->displayed_user )
			? buddypress()->displayed_user
			: null;
		$this->current_action = buddypress()->current_action;
		$this->members        = ( new ReflectionClass( 'BBP_BuddyPress_Members' ) )->newInstanceWithoutConstructor();
	}

	public function tearDown(): void {
		if ( null === $this->displayed_user ) {
			unset( buddypress()->displayed_user );
		} else {
			buddypress()->displayed_user = $this->displayed_user;
		}

		buddypress()->current_action = $this->current_action;
		$this->clear_user_forum_query_vars();
		$this->set_permalink_structure();

		parent::tearDown();
	}

	/**
	 * @dataProvider profile_sections
	 * @covers ::BBP_BuddyPress_Members::set_member_forum_query_vars
	 */
	public function test_sets_query_var_for_another_members_profile( $slug_callback, $query_var ) {
		$displayed_user_id = $this->factory->user->create();
		$logged_in_user_id = $this->factory->user->create();

		$this->set_current_user( $logged_in_user_id );
		buddypress()->displayed_user = (object) array( 'id' => $displayed_user_id );
		buddypress()->current_action = call_user_func( $slug_callback );

		$this->assertFalse( bp_is_my_profile() );
		$this->assertSame( $logged_in_user_id, bp_loggedin_user_id() );
		$this->members->set_member_forum_query_vars();

		$this->assertTrue( bbp_get_wp_query()->$query_var );
	}

	/**
	 * @covers ::BBP_BuddyPress_Members::set_member_forum_query_vars
	 * @covers ::bbp_get_topics_pagination_base
	 */
	public function test_anonymous_topics_pagination_uses_displayed_members_section() {
		$this->set_anonymous_member_section( 'bbp_get_topic_archive_slug' );
		$this->set_permalink_structure( '/%postname%/' );

		$topics_url = function () {
			return 'https://example.org/members/target/forums/topics/';
		};

		add_filter( 'bbp_pre_get_user_topics_created_url', $topics_url, 99 );
		try {
			$this->members->set_member_forum_query_vars();

			$this->assertSame(
				'https://example.org/members/target/forums/topics/page/%#%/',
				bbp_get_topics_pagination_base()
			);
		} finally {
			remove_filter( 'bbp_pre_get_user_topics_created_url', $topics_url, 99 );
		}
	}

	/**
	 * @covers ::BBP_BuddyPress_Members::set_member_forum_query_vars
	 * @covers ::bbp_get_replies_pagination_base
	 */
	public function test_anonymous_replies_pagination_uses_displayed_members_section() {
		$this->set_anonymous_member_section( 'bbp_get_reply_archive_slug' );
		$this->set_permalink_structure( '/%postname%/' );

		$replies_url = function () {
			return 'https://example.org/members/target/forums/replies/';
		};

		add_filter( 'bbp_pre_get_user_replies_created_url', $replies_url, 99 );
		try {
			$this->members->set_member_forum_query_vars();

			$this->assertSame(
				'https://example.org/members/target/forums/replies/page/%#%/',
				bbp_get_replies_pagination_base()
			);
		} finally {
			remove_filter( 'bbp_pre_get_user_replies_created_url', $replies_url, 99 );
		}
	}

	/**
	 * @covers ::BBP_BuddyPress_Members::set_member_forum_query_vars
	 */
	public function test_ignores_non_user_pages() {
		unset( buddypress()->displayed_user );
		buddypress()->current_action = bbp_get_topic_archive_slug();

		$this->members->set_member_forum_query_vars();

		$this->assertFalse( isset( bbp_get_wp_query()->bbp_is_single_user_topics ) );
	}

	public function profile_sections() {
		return array(
			'topics'        => array( 'bbp_get_topic_archive_slug',       'bbp_is_single_user_topics'      ),
			'replies'       => array( 'bbp_get_reply_archive_slug',       'bbp_is_single_user_replies'     ),
			'engagements'   => array( 'bbp_get_user_engagements_slug',    'bbp_is_single_user_engagements' ),
			'favorites'     => array( 'bbp_get_user_favorites_slug',      'bbp_is_single_user_favs'        ),
			'subscriptions' => array( 'bbp_get_user_subscriptions_slug',  'bbp_is_single_user_subs'        ),
		);
	}

	private function set_anonymous_member_section( $slug_callback ) {
		$this->set_current_user( 0 );
		buddypress()->displayed_user = (object) array( 'id' => $this->factory->user->create() );
		buddypress()->current_action = call_user_func( $slug_callback );
	}

	private function clear_user_forum_query_vars() {
		foreach ( array(
			'bbp_is_single_user_topics',
			'bbp_is_single_user_replies',
			'bbp_is_single_user_engagements',
			'bbp_is_single_user_favs',
			'bbp_is_single_user_subs',
		) as $query_var ) {
			unset( bbp_get_wp_query()->$query_var );
		}
	}
}
