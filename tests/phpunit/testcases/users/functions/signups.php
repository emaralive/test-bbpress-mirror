<?php

/**
 * Tests for forum roles during user signups and invitations.
 *
 * @group users
 * @group capabilities
 */
class BBP_Tests_Users_Functions_Signups extends BBP_UnitTestCase {

	protected $old_post;
	protected $old_errors;
	protected $granted_super_admin;

	public function setUp(): void {
		parent::setUp();
		$this->old_post   = $_POST;
		$this->old_errors = bbpress()->errors;
		bbpress()->errors = new WP_Error();
	}

	public function tearDown(): void {
		$_POST            = $this->old_post;
		bbpress()->errors = $this->old_errors;
		if ( $this->granted_super_admin ) {
			revoke_super_admin( $this->granted_super_admin );
			$this->granted_super_admin = 0;
		}
		parent::tearDown();
	}

	/**
	 * @covers ::bbp_add_user_form_role_field
	 */
	public function test_role_field_requires_promote_users_capability() {
		$this->set_current_user( 0 );
		ob_start();
		bbp_add_user_form_role_field();
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * @covers ::bbp_add_user_form_role_field
	 */
	public function test_role_field_selects_posted_role_for_keymaster() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
			$this->granted_super_admin = $admin_id;
		}
		bbp_set_user_role( $admin_id, bbp_get_keymaster_role() );
		$this->set_current_user( $admin_id );
		$_POST['bbp-forums-role'] = bbp_get_moderator_role();

		ob_start();
		bbp_add_user_form_role_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="bbp-forums-role"', $output );
		$this->assertMatchesRegularExpression( '/<option[^>]*selected=["\']selected["\'][^>]*value=["\']bbp_moderator["\']/', $output );
		$this->assertStringContainsString( 'value="' . bbp_get_keymaster_role() . '"', $output );
	}

	/**
	 * @covers ::bbp_add_user_form_role_field
	 */
	public function test_role_field_hides_keymaster_from_other_administrators() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $admin_id, bbp_get_participant_role() );
		$this->set_current_user( $admin_id );
		$this->assertTrue( current_user_can( 'promote_users' ) );
		$this->assertFalse( bbp_is_user_keymaster() );

		ob_start();
		bbp_add_user_form_role_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="bbp-forums-role"', $output );
		$this->assertStringNotContainsString( 'value="' . bbp_get_keymaster_role() . '"', $output );
	}

	/**
	 * @covers ::bbp_validate_signup_role
	 * @covers ::bbp_validate_activation_role
	 */
	public function test_signup_and_activation_role_validation() {
		$role = bbp_get_participant_role();
		$this->assertSame( $role, bbp_validate_signup_role( $role ) );
		$this->assertSame( $role, bbp_validate_activation_role( $role ) );

		$this->assertSame( '', bbp_validate_signup_role( '' ) );
		$this->assertContains( 'bbp_signup_role_empty', bbpress()->errors->get_error_codes() );
		$this->assertContains( 'bbp_signup_role_invalid', bbpress()->errors->get_error_codes() );

		bbpress()->errors = new WP_Error();
		$this->assertSame( '', bbp_validate_activation_role( 'not-a-forum-role' ) );
		$this->assertSame( array( 'bbp_signup_role_invalid' ), bbpress()->errors->get_error_codes() );
	}

	/**
	 * @covers ::bbp_validate_signup_role
	 */
	public function test_signup_role_filter_receives_validated_and_requested_roles() {
		$seen   = null;
		$record = function ( $validated, $requested ) use ( &$seen ) {
			$seen = array( $validated, $requested );
			return $validated;
		};
		$role = bbp_get_moderator_role();
		add_filter( 'bbp_validate_signup_role', $record, 10, 2 );

		try {
			$this->assertSame( $role, bbp_validate_signup_role( $role ) );
			$this->assertSame( array( $role, $role ), $seen );
		} finally {
			remove_filter( 'bbp_validate_signup_role', $record, 10 );
		}
	}

	/**
	 * @covers ::bbp_validate_registration_role
	 */
	public function test_registration_role_defaults_without_administrator_context() {
		$this->set_current_user( 0 );
		set_current_screen( 'front' );
		$this->assertSame( bbp_get_default_role(), bbp_validate_registration_role( bbp_get_moderator_role() ) );
	}

	/**
	 * @covers ::bbp_user_add_role_to_signup_meta
	 */
	public function test_signup_meta_preserves_existing_role_and_adds_default_role() {
		$meta = array( 'other' => 'keep', 'bbp_new_role' => bbp_get_moderator_role() );
		$this->assertSame( $meta, bbp_user_add_role_to_signup_meta( $meta ) );

		$this->set_current_user( 0 );
		set_current_screen( 'front' );
		$_POST['bbp-forums-role'] = bbp_get_moderator_role();
		$this->assertSame(
			array( 'other' => 'keep', 'bbp_new_role' => bbp_get_default_role() ),
			bbp_user_add_role_to_signup_meta( array( 'other' => 'keep' ) )
		);
	}

	/**
	 * @covers ::bbp_user_add_role_to_signup_meta
	 */
	public function test_signup_meta_is_unchanged_when_role_validation_fails() {
		$this->set_current_user( 0 );
		set_current_screen( 'front' );
		$invalid_default = function () {
			return 'not-a-forum-role';
		};
		add_filter( 'bbp_get_default_role', $invalid_default );

		try {
			$this->assertSame( array( 'other' => 'keep' ), bbp_user_add_role_to_signup_meta( array( 'other' => 'keep' ) ) );
			$this->assertContains( 'bbp_signup_role_invalid', bbpress()->errors->get_error_codes() );
		} finally {
			remove_filter( 'bbp_get_default_role', $invalid_default );
		}
	}

	/**
	 * @covers ::bbp_user_add_role_on_invite
	 */
	public function test_invitation_role_is_saved_only_for_valid_key() {
		$this->set_current_user( 0 );
		set_current_screen( 'front' );
		$key = 'bbp-test-invitation';
		update_option( 'new_user_' . $key, array( 'other' => 'keep' ) );

		try {
			bbp_user_add_role_on_invite( 0, '', $key );
			$this->assertSame(
				array( 'other' => 'keep', 'bbp_new_role' => bbp_get_default_role() ),
				get_option( 'new_user_' . $key )
			);

			update_option( 'new_user_' . $key, array( 'other' => 'keep' ) );
			bbp_user_add_role_on_invite( 0, '', array( $key ) );
			$this->assertSame( array( 'other' => 'keep' ), get_option( 'new_user_' . $key ) );
		} finally {
			delete_option( 'new_user_' . $key );
		}
	}

	/**
	 * @covers ::bbp_user_add_role_on_invite
	 */
	public function test_invitation_is_unchanged_when_role_validation_fails() {
		$this->set_current_user( 0 );
		set_current_screen( 'front' );
		$key = 'bbp-invalid-role-invitation';
		update_option( 'new_user_' . $key, array( 'other' => 'keep' ) );
		$invalid_default = function () {
			return 'not-a-forum-role';
		};
		add_filter( 'bbp_get_default_role', $invalid_default );

		try {
			bbp_user_add_role_on_invite( 0, '', $key );
			$this->assertSame( array( 'other' => 'keep' ), get_option( 'new_user_' . $key ) );
			$this->assertContains( 'bbp_signup_role_invalid', bbpress()->errors->get_error_codes() );
		} finally {
			remove_filter( 'bbp_get_default_role', $invalid_default );
			delete_option( 'new_user_' . $key );
		}
	}

	/**
	 * @covers ::bbp_user_add_role_on_activate
	 */
	public function test_activation_applies_valid_role_but_rejects_invalid_role() {
		$user_id = $this->factory->user->create();
		bbp_user_add_role_on_activate( $user_id, '', array( 'bbp_new_role' => bbp_get_moderator_role() ) );
		$this->assertSame( bbp_get_moderator_role(), bbp_get_user_role( $user_id ) );

		bbp_user_add_role_on_activate( $user_id, '', array( 'bbp_new_role' => 'not-a-forum-role' ) );
		$this->assertSame( bbp_get_moderator_role(), bbp_get_user_role( $user_id ) );
		$this->assertContains( 'bbp_signup_role_invalid', bbpress()->errors->get_error_codes() );
	}
}
