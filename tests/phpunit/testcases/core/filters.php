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

	/**
	 * @covers ::_bbp_has_forums_query
	 * @covers ::_bbp_has_topics_query
	 * @covers ::_bbp_has_replies_query
	 * @dataProvider get_deprecated_query_filters
	 */
	public function test_deprecated_query_filters_preserve_the_legacy_hooks( $parse_args_filter, $legacy_filter, $callback ) {
		$args     = array( 'post_type' => 'test' );
		$received = null;
		$filter   = function( $filtered_args ) use ( &$received ) {
			$received = $filtered_args;

			return 'filtered';
		};

		$this->assertSame( 10, has_filter( $parse_args_filter, $callback ) );

		add_filter( $legacy_filter, $filter );
		try {
			$this->assertSame( array( 'filtered' ), apply_filters( $parse_args_filter, $args ) );
			$this->assertSame( $args, $received );
		} finally {
			remove_filter( $legacy_filter, $filter, 10 );
		}
	}

	/**
	 * Data provider for deprecated query filters.
	 *
	 * @return array
	 */
	public function get_deprecated_query_filters() {
		return array(
			'forums' => array( 'bbp_after_has_forums_parse_args', 'bbp_has_forums_query', '_bbp_has_forums_query' ),
			'topics' => array( 'bbp_after_has_topics_parse_args', 'bbp_has_topics_query', '_bbp_has_topics_query' ),
			'replies' => array( 'bbp_after_has_replies_parse_args', 'bbp_has_replies_query', '_bbp_has_replies_query' ),
		);
	}

	/**
	 * Exact priorities ensure unfiltered-html requests can remove the intended callbacks.
	 *
	 * @dataProvider get_content_sanitization_filters
	 */
	public function test_content_sanitization_filters_are_registered_in_order( $filter ) {
		$this->assertSame( 10, has_filter( $filter, 'bbp_encode_bad' ) );
		$this->assertSame( 20, has_filter( $filter, 'bbp_code_trick' ) );
		$this->assertSame( 30, has_filter( $filter, 'bbp_filter_kses' ) );
		$this->assertSame( 40, has_filter( $filter, 'balanceTags' ) );
	}

	/**
	 * Test the content filter chain without depending on version-specific KSES output.
	 */
	public function test_content_sanitization_filters_process_content() {
		$content = apply_filters( 'bbp_new_topic_pre_content', '<script>x</script><strong>ok</strong> `<b>`' );

		$this->assertStringNotContainsString( '<script', $content );
		$this->assertStringContainsString( '<strong>ok</strong>', $content );
		$this->assertStringContainsString( '&lt;b&gt;', $content );
	}

	/**
	 * Data provider for forum, topic, and reply content filters.
	 *
	 * @return array
	 */
	public function get_content_sanitization_filters() {
		return array(
			'new forum'  => array( 'bbp_new_forum_pre_content' ),
			'new topic'  => array( 'bbp_new_topic_pre_content' ),
			'new reply'  => array( 'bbp_new_reply_pre_content' ),
			'edit forum' => array( 'bbp_edit_forum_pre_content' ),
			'edit topic' => array( 'bbp_edit_topic_pre_content' ),
			'edit reply' => array( 'bbp_edit_reply_pre_content' ),
		);
	}

	/**
	 * @dataProvider get_title_sanitization_filters
	 */
	public function test_title_sanitization_filters_use_wordpress_kses( $filter ) {
		$this->assertSame( 10, has_filter( $filter, 'wp_filter_kses' ) );
	}

	/**
	 * Data provider for forum, topic, and reply title filters.
	 *
	 * @return array
	 */
	public function get_title_sanitization_filters() {
		return array(
			'new forum'  => array( 'bbp_new_forum_pre_title' ),
			'new topic'  => array( 'bbp_new_topic_pre_title' ),
			'new reply'  => array( 'bbp_new_reply_pre_title' ),
			'edit forum' => array( 'bbp_edit_forum_pre_title' ),
			'edit topic' => array( 'bbp_edit_topic_pre_title' ),
			'edit reply' => array( 'bbp_edit_reply_pre_title' ),
		);
	}
}
