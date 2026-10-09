<?php

/**
 * BuddyPress notification tests.
 *
 * @group extend
 * @group buddypress
 * @group notifications
 */
class BBP_Tests_Extend_BuddyPress_Notifications extends BBP_UnitTestCase {

	private function format_multiple_reply_notification( $count ) {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_title'  => 'Plural Topic'
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'_bbp_forum_id' => $forum_id,
					'_bbp_topic_id' => $topic_id
				)
			)
		);

		return bbp_format_buddypress_notifications(
			'',
			$reply_id,
			0,
			$count,
			'object',
			'bbp_new_reply_' . $topic_id,
			bbp_get_component_name(),
			0
		);
	}

	/**
	 * @covers ::bbp_format_buddypress_notifications
	 */
	public function test_multiple_reply_notification_uses_english_plural() {
		$this->assertSame( 'You have 2 new replies to Plural Topic', $this->format_multiple_reply_notification( 2 )['text'] );
	}

	/**
	 * @covers ::bbp_format_buddypress_notifications
	 */
	public function test_multiple_reply_notification_uses_locale_plural_form() {
		$filter = function( $translation, $single, $plural, $number, $domain ) {
			if ( ( 21 === $number ) && ( 'bbpress' === $domain ) ) {
				$translation = 'You have %1$d localized replies to %2$s';
			}

			return $translation;
		};

		add_filter( 'ngettext', $filter, 10, 5 );
		$text = $this->format_multiple_reply_notification( 21 )['text'];
		remove_filter( 'ngettext', $filter, 10 );

		$this->assertSame( 'You have 21 localized replies to Plural Topic', $text );
	}

	/**
	 * @covers ::bbp_buddypress_add_notification
	 */
	public function test_only_published_replies_notify_topic_authors() {
		$topic_author = $this->factory->user->create();
		$reply_author = $this->factory->user->create();
		$forum_id     = $this->factory->forum->create();
		$topic_id     = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_author' => $topic_author
			)
		);

		foreach ( array( bbp_get_public_status_id(), bbp_get_pending_status_id(), bbp_get_spam_status_id() ) as $status ) {
			$reply_id = $this->factory->reply->create(
				array(
					'post_parent' => $topic_id,
					'post_author' => $reply_author,
					'post_status' => $status,
					'reply_meta'  => array(
						'_bbp_forum_id' => $forum_id,
						'_bbp_topic_id' => $topic_id
					)
				)
			);

			bbp_buddypress_add_notification( $reply_id, $topic_id, $forum_id, array(), $reply_author, false, 0 );

			$notifications = BP_Notifications_Notification::get(
				array(
					'user_id'          => $topic_author,
					'item_id'          => $reply_id,
					'component_name'   => bbp_get_component_name(),
					'component_action' => 'bbp_new_reply_' . $topic_id
				)
			);

			$this->assertCount( bbp_get_public_status_id() === $status ? 1 : 0, $notifications );
		}
	}
}
