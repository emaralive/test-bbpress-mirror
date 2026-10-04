<?php

/**
 * Tests for the topic loop functions.
 *
 * @group topics
 * @group template
 * @group loop
 */
class BBP_Tests_Topics_Template_Topic_Loop extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_has_topics
	 */
	public function test_bbp_has_topics() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );

		$this->assertTrue( bbp_has_topics( array(
			'post_parent'    => $forum_id,
			'posts_per_page' => -1,
		) ) );
		$this->assertSame( 1, bbpress()->topic_query->post_count );
		$this->assertSame( $topic_id, bbpress()->topic_query->posts[0]->ID );
		$this->assertFalse( bbp_has_topics( array( 'post_parent' => $topic_id ) ) );
	}

	/**
	 * @covers ::bbp_topics
	 */
	public function test_bbp_topics() {
		$forum_id = $this->factory->forum->create();
		$this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		bbp_has_topics( array( 'post_parent' => $forum_id ) );

		$this->assertTrue( bbp_topics() );
		bbp_the_topic();
		$this->assertFalse( bbp_topics() );
	}

	/**
	 * @covers ::bbp_the_topic
	 */
	public function test_bbp_the_topic() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		bbp_has_topics( array( 'post_parent' => $forum_id ) );

		$this->assertTrue( bbp_topics() );
		bbp_the_topic();
		$this->assertSame( $topic_id, get_the_ID() );
		$this->assertFalse( bbp_topics() );
	}
}
