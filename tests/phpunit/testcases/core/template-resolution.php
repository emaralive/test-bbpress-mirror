<?php

/**
 * Tests for core template resolution regressions.
 *
 * @group core
 * @group template_functions
 * @ticket BBP3716
 */
class BBP_Tests_Core_Template_Resolution extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_locate_template
	 */
	public function test_locate_template_accepts_an_empty_candidate_array() {
		$this->assertFalse( bbp_locate_template( array() ) );
	}

	/**
	 * @covers ::bbp_get_template_stack
	 */
	public function test_get_template_stack_restores_current_filter_when_stack_is_empty() {
		global $wp_current_filter, $wp_filter;

		$tag        = 'bbp_template_stack';
		$had_filter = isset( $wp_filter[ $tag ] );
		$old_filter = $had_filter ? $wp_filter[ $tag ] : null;
		$before     = $wp_current_filter;

		unset( $wp_filter[ $tag ] );
		try {
			$this->assertSame( array(), bbp_get_template_stack() );
			$this->assertSame( $before, $wp_current_filter );
		} finally {
			$wp_current_filter = $before;
			if ( $had_filter ) {
				$wp_filter[ $tag ] = $old_filter;
			}
		}
	}
}
