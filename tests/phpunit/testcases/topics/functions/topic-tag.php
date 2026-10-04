<?php

/**
 * Tests for the topic component functions.
 *
 * @group topics
 * @group topic_tags
 * @group functions
 * @group topic
 * @group topic_tag
 */
class BBP_Tests_Topics_Functions_Topic_Tag extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_edit_topic_tag_handler
	 * @todo   Cover successful edits in an integration test because bbp_redirect() exits.
	 */
	public function test_bbp_edit_topic_tag_handler() {
		$old_post    = $_POST;
		$old_request = $_REQUEST;
		$term        = wp_insert_term( 'Original', bbp_get_topic_tag_tax_id() );
		$this->assertFalse( is_wp_error( $term ) );

		try {
			unset( $_POST['tag-id'] );
			$this->assertNull( bbp_edit_topic_tag_handler( 'bbp-update-topic-tag' ) );

			$_POST['tag-id'] = $term['term_id'];
			$this->assertNull( bbp_edit_topic_tag_handler( 'unsupported' ) );

			$_REQUEST['_wpnonce'] = 'invalid';
			$this->assertNull( bbp_edit_topic_tag_handler( 'bbp-update-topic-tag' ) );
			$this->assertSame( 'bbp_manage_topic_tag_update_nonce', bbpress()->errors->get_error_code() );
			bbpress()->errors->remove( 'bbp_manage_topic_tag_update_nonce' );

			$this->assertNull( bbp_edit_topic_tag_handler( 'bbp-merge-topic-tag' ) );
			$this->assertSame( 'bbp_manage_topic_tag_merge_nonce', bbpress()->errors->get_error_code() );
			bbpress()->errors->remove( 'bbp_manage_topic_tag_merge_nonce' );

			$this->assertNull( bbp_edit_topic_tag_handler( 'bbp-delete-topic-tag' ) );
			$this->assertSame( 'bbp_manage_topic_tag_delete_nonce', bbpress()->errors->get_error_code() );
			$this->assertSame( 'Original', get_term( $term['term_id'], bbp_get_topic_tag_tax_id() )->name );
		} finally {
			bbpress()->errors->remove( 'bbp_manage_topic_tag_update_nonce' );
			bbpress()->errors->remove( 'bbp_manage_topic_tag_merge_nonce' );
			bbpress()->errors->remove( 'bbp_manage_topic_tag_delete_nonce' );
			$_POST = $old_post;
			$_REQUEST = $old_request;
		}
	}

	/**
	 * @covers ::bbp_spam_topic_tags
	 */
	public function test_bbp_spam_topic_tags() {
		$topic_id = $this->factory->topic->create();
		$taxonomy = bbp_get_topic_tag_tax_id();
		wp_set_object_terms( $topic_id, array( 'alpha', 'beta' ), $taxonomy );

		$this->assertSame( array( $taxonomy => '' ), bbp_spam_topic_tags( $topic_id ) );
		$this->assertSame( array( 'alpha', 'beta' ), get_post_meta( $topic_id, '_bbp_spam_topic_tags', true ) );

		$empty_topic_id = $this->factory->topic->create();
		$this->assertSame( array( $taxonomy => '' ), bbp_spam_topic_tags( $empty_topic_id ) );
		$this->assertFalse( metadata_exists( 'post', $empty_topic_id, '_bbp_spam_topic_tags' ) );
	}

	/**
	 * @covers ::bbp_unspam_topic_tags
	 */
	public function test_bbp_unspam_topic_tags() {
		$topic_id = $this->factory->topic->create();
		$taxonomy = bbp_get_topic_tag_tax_id();
		update_post_meta( $topic_id, '_bbp_spam_topic_tags', array( 'alpha', 'beta' ) );

		$this->assertSame( array( $taxonomy => array( 'alpha', 'beta' ) ), bbp_unspam_topic_tags( $topic_id ) );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_spam_topic_tags' ) );
		$this->assertSame( array( $taxonomy => '' ), bbp_unspam_topic_tags( $topic_id ) );
	}

	/**
	 * @covers ::bbp_get_topic_tag_names
	 */
	public function test_bbp_get_topic_tag_names() {
		$topic_id = $this->factory->topic->create();
		$this->assertSame( '', bbp_get_topic_tag_names( $topic_id ) );
		wp_set_object_terms( $topic_id, array( 'alpha', 'beta' ), bbp_get_topic_tag_tax_id() );
		$this->assertSame( 'alpha, beta', bbp_get_topic_tag_names( $topic_id ) );
		$this->assertSame( 'alpha | beta', bbp_get_topic_tag_names( $topic_id, ' | ' ) );
	}

	/**
	 * @covers ::bbp_check_topic_tag_edit
	 * @todo   Cover denied edits in an integration test because bbp_redirect() exits.
	 */
	public function test_bbp_check_topic_tag_edit() {
		$this->assertNull( bbp_check_topic_tag_edit() );
	}
}
