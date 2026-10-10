<?php

/**
 * Tests for common localization functions.
 */
class BBP_Tests_Common_Locale extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_translate_user_role
	 */
	public function test_translate_user_role_uses_context_and_domain() {
		$calls  = array();
		$filter = function( $translation, $text, $context, $domain ) use ( &$calls ) {
			$calls[] = array( $text, $context, $domain );
			return ( array( 'Moderator', 'User role', 'bbpress' ) === end( $calls ) )
				? 'Translated Moderator'
				: $translation;
		};
		add_filter( 'gettext_with_context', $filter, 10, 4 );

		try {
			$this->assertSame( 'Translated Moderator', bbp_translate_user_role( 'Moderator' ) );
			$this->assertSame( array( array( 'Moderator', 'User role', 'bbpress' ) ), $calls );
		} finally {
			remove_filter( 'gettext_with_context', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_translate_user_role
	 */
	public function test_translate_user_role_supports_legacy_context_format() {
		$calls  = array();
		$filter = function( $translation, $text, $context, $domain ) use ( &$calls ) {
			$calls[] = array( $text, $context, $domain );
			return $translation;
		};
		add_filter( 'gettext_with_context', $filter, 10, 4 );

		try {
			$this->assertSame( 'Participant', bbp_translate_user_role( 'Participant|User role' ) );
			$this->assertSame( array( array( 'Participant', 'User role', 'bbpress' ) ), $calls );
		} finally {
			remove_filter( 'gettext_with_context', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_dummy_role_names
	 */
	public function test_dummy_role_names_register_catalog_strings() {
		$roles  = array();
		$filter = function( $translation, $text, $context, $domain ) use ( &$roles ) {
			if ( ( 'User role' === $context ) && ( 'bbpress' === $domain ) ) {
				$roles[] = $text;
			}
			return $translation;
		};
		add_filter( 'gettext_with_context', $filter, 10, 4 );

		try {
			bbp_dummy_role_names();
			$this->assertSame(
				array( 'Keymaster', 'Moderator', 'Participant', 'Spectator', 'Blocked' ),
				$roles
			);
		} finally {
			remove_filter( 'gettext_with_context', $filter, 10 );
		}
	}
}
