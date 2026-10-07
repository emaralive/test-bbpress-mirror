<?php

/**
 * Tests for the core filters.
 *
 * @group core
 * @group filters
 */
class BBP_Tests_Core_Filters extends BBP_UnitTestCase {

	/**
	 * @covers ::_bbp_filter_locale
	 * @covers ::bbp_plugin_locale
	 */
	public function test_deprecated_locale_filter_runs_only_for_the_bbpress_domain() {
		$filtered = array();
		$filter   = function ( $locale, $domain ) use ( &$filtered ) {
			$filtered[] = array( $locale, $domain );

			return 'bbpress-' . $locale;
		};

		add_filter( 'bbpress_locale', $filter, 10, 2 );
		try {
			$this->assertSame( 'en_US', apply_filters( 'plugin_locale', 'en_US', 'other-domain' ) );
			$this->assertSame( 'bbpress-en_US', apply_filters( 'plugin_locale', 'en_US', 'bbpress' ) );
			$this->assertSame( array( array( 'en_US', 'bbpress' ) ), $filtered );
		} finally {
			remove_filter( 'bbpress_locale', $filter, 10 );
		}
	}
}
