<?php

/**
 * Tests for the reply loop functions.
 *
 * @group replies
 * @group template
 * @group loop
 */
class BBP_Tests_Replies_Template_Reply_Loop extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_has_replies
	 */
	public function test_bbp_has_replies() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		$this->assertTrue( bbp_has_replies( array(
			'post_type'   => bbp_get_reply_post_type(),
			'post_parent' => $topic_id,
		) ) );
		$this->assertSame( 1, bbpress()->reply_query->post_count );
		$this->assertSame( $reply_id, bbpress()->reply_query->posts[0]->ID );
		$this->assertFalse( bbp_has_replies( array(
			'post_type'   => bbp_get_reply_post_type(),
			'post_parent' => $reply_id,
		) ) );
	}

	/**
	 * @covers ::bbp_replies
	 */
	public function test_bbp_replies() {
		$topic_id = $this->factory->topic->create();
		$this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		bbp_has_replies( array(
			'post_type'   => bbp_get_reply_post_type(),
			'post_parent' => $topic_id,
		) );

		$this->assertTrue( bbp_replies() );
		bbp_the_reply();
		$this->assertFalse( bbp_replies() );
	}

	/**
	 * @covers ::bbp_the_reply
	 */
	public function test_bbp_the_reply() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		bbp_has_replies( array(
			'post_type'   => bbp_get_reply_post_type(),
			'post_parent' => $topic_id,
		) );

		$this->assertTrue( bbp_replies() );
		bbp_the_reply();
		$this->assertSame( $reply_id, get_the_ID() );
		$this->assertFalse( bbp_replies() );
	}
}
