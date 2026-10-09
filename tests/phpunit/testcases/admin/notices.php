<?php

class BBP_Tests_Admin_Notices_Redirect_Exception extends Exception {}

/**
 * Tests for reply administration notices.
 *
 * @group admin
 * @group replies
 */
class BBP_Tests_Admin_Notices extends BBP_UnitTestCase {

	private $get;
	private $request;
	private $request_method;
	private $request_uri;
	private $admin_notices;

	public function setUp(): void {
		parent::setUp();
		$this->get            = $_GET;
		$this->request        = $_REQUEST;
		$this->request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$this->request_uri    = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		if ( ! function_exists( 'bbp_admin_replies' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/replies.php';
		}
		$this->admin_notices = bbp_admin()->notices;
	}

	public function tearDown(): void {
		$_GET     = $this->get;
		$_REQUEST = $this->request;
		bbp_admin()->notices = $this->admin_notices;
		if ( is_null( $this->request_method ) ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $this->request_method;
		}
		if ( is_null( $this->request_uri ) ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}
		parent::tearDown();
	}

	/**
	 * @covers BBP_Replies_Admin::toggle_reply
	 * @covers BBP_Replies_Admin::toggle_reply_notice
	 * @ticket 3726
	 */
	public function test_unapproving_reply_redirects_to_success_notice() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$user_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		$reflection = new ReflectionClass( 'BBP_Replies_Admin' );
		$admin      = $reflection->newInstanceWithoutConstructor();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = '/wp-admin/edit.php?post_type=reply';
		$_GET = array(
			'action'   => 'bbp_toggle_reply_approve',
			'reply_id' => $reply_id,
			'_wpnonce' => wp_create_nonce( 'approve-reply_' . $reply_id ),
		);
		$_REQUEST = $_GET;
		$redirect = '';
		$stop = function ( $location ) use ( &$redirect ) {
			$redirect = $location;
			throw new BBP_Tests_Admin_Notices_Redirect_Exception();
		};
		add_filter( 'wp_redirect', $stop );
		try {
			try {
				$admin->toggle_reply();
				$this->fail( 'The reply toggle should redirect.' );
			} catch ( BBP_Tests_Admin_Notices_Redirect_Exception $error ) {
				$this->assertNotSame( '', $redirect );
			}
		} finally {
			remove_filter( 'wp_redirect', $stop );
		}

		$this->assertStringContainsString( 'bbp_reply_toggle_notice=unapproved', $redirect );
		parse_str( wp_parse_url( $redirect, PHP_URL_QUERY ), $_GET );
		bbp_admin()->notices = array();
		$admin->toggle_reply_notice();
		$this->assertStringContainsString( 'successfully unapproved', bbp_admin()->notices[0] );
	}
}
