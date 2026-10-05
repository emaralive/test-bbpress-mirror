<?php

/**
 * Tests for core option functions.
 *
 * @group core
 * @group options
 */
class BBP_Tests_Core_Options extends BBP_UnitTestCase {

	/**
	 * @ticket BBP3707
	 * @covers ::bbp_allow_threaded_replies
	 */
	public function test_bbp_allow_threaded_replies_preserves_the_legacy_filter_before_the_public_filter() {
		$old_options = bbpress()->options;
		$order       = array();
		$legacy      = function( $allow ) use ( &$order ) {
			$order[] = 'legacy';
			$this->assertTrue( $allow );
			return false;
		};
		$public      = function( $allow ) use ( &$order ) {
			$order[] = 'public';
			$this->assertFalse( $allow );
			return true;
		};

		bbpress()->options['_bbp_allow_threaded_replies'] = 1;
		add_filter( '_bbp_allow_threaded_replies', $legacy );
		add_filter( 'bbp_allow_threaded_replies', $public );
		try {
			$this->assertTrue( bbp_allow_threaded_replies() );
		} finally {
			remove_filter( '_bbp_allow_threaded_replies', $legacy );
			remove_filter( 'bbp_allow_threaded_replies', $public );
			bbpress()->options = $old_options;
		}

		$this->assertSame( array( 'legacy', 'public' ), $order );
	}
}
