<?php

// Run an RSS feed in a separate PHP process because the feed exits after output.
putenv( 'WP_TESTS_SKIP_INSTALL=1' );
if ( isset( $argv[2] ) && 'multisite' === $argv[2] ) {
	define( 'WP_TESTS_MULTISITE', true );
}

require dirname( __DIR__, 3 ) . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/bootstrap.php';

$mode = isset( $argv[1] ) ? $argv[1] : '';
if ( ! in_array( $mode, array( 'topics', 'replies' ), true ) ) {
	exit( 2 );
}

$forum_id = wp_insert_post( array(
	'post_type'   => bbp_get_forum_post_type(),
	'post_status' => bbp_get_public_status_id(),
	'post_title'  => 'Feed Test Forum',
) );
$topic_id = wp_insert_post( array(
	'post_type'    => bbp_get_topic_post_type(),
	'post_status'  => bbp_get_public_status_id(),
	'post_parent'  => $forum_id,
	'post_title'   => 'Feed Test Topic',
	'post_content' => 'Feed test topic content',
) );
$reply_id = wp_insert_post( array(
	'post_type'    => bbp_get_reply_post_type(),
	'post_status'  => bbp_get_public_status_id(),
	'post_parent'  => $topic_id,
	'post_title'   => 'Feed Test Reply',
	'post_content' => 'Feed test reply content',
) );
$protected_topic_id = wp_insert_post( array(
	'post_type'     => bbp_get_topic_post_type(),
	'post_status'   => bbp_get_public_status_id(),
	'post_parent'   => $forum_id,
	'post_title'    => 'Protected Feed Topic',
	'post_content'  => 'Protected topic secret content',
	'post_password' => 'secret',
) );
$private_topic_id = wp_insert_post( array(
	'post_type'    => bbp_get_topic_post_type(),
	'post_status'  => bbp_get_private_status_id(),
	'post_parent'  => $forum_id,
	'post_title'   => 'Private Feed Topic',
	'post_content' => 'Private topic secret content',
) );
$private_reply_id = wp_insert_post( array(
	'post_type'    => bbp_get_reply_post_type(),
	'post_status'  => bbp_get_public_status_id(),
	'post_parent'  => $private_topic_id,
	'post_title'   => 'Private Feed Reply',
	'post_content' => 'Private reply secret content',
) );

update_post_meta( $topic_id, '_bbp_forum_id', $forum_id );
update_post_meta( $topic_id, '_bbp_last_active_time', current_time( 'mysql', true ) );
update_post_meta( $reply_id, '_bbp_forum_id', $forum_id );
update_post_meta( $reply_id, '_bbp_topic_id', $topic_id );
update_post_meta( $protected_topic_id, '_bbp_forum_id', $forum_id );
update_post_meta( $protected_topic_id, '_bbp_last_active_time', current_time( 'mysql', true ) );
update_post_meta( $private_topic_id, '_bbp_forum_id', $forum_id );
update_post_meta( $private_reply_id, '_bbp_forum_id', $forum_id );
update_post_meta( $private_reply_id, '_bbp_topic_id', $private_topic_id );

register_shutdown_function( function () use ( $reply_id, $topic_id, $protected_topic_id, $private_topic_id, $private_reply_id, $forum_id ) {
	wp_delete_post( $private_reply_id, true );
	wp_delete_post( $reply_id, true );
	wp_delete_post( $private_topic_id, true );
	wp_delete_post( $protected_topic_id, true );
	wp_delete_post( $topic_id, true );
	wp_delete_post( $forum_id, true );
} );

if ( 'topics' === $mode ) {
	bbp_display_topics_feed_rss2( array( 'post__in' => array( $topic_id, $protected_topic_id ), 'posts_per_page' => 2 ) );
} else {
	bbp_display_replies_feed_rss2( array( 'post__in' => array( $reply_id, $private_reply_id ), 'posts_per_page' => 2 ) );
}
