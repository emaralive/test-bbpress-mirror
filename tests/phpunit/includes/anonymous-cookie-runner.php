<?php

// Exercise cookie headers under the built-in web server rather than CLI SAPI.
putenv( 'WP_TESTS_SKIP_INSTALL=1' );
if ( '1' === getenv( 'BBP_TEST_MULTISITE' ) ) {
	define( 'WP_TESTS_MULTISITE', true );
}

require dirname( __DIR__, 3 ) . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/bootstrap.php';

add_filter( 'comment_cookie_lifetime', function () {
	return HOUR_IN_SECONDS;
} );

bbp_set_current_anonymous_user_data( array(
	'bbp_anonymous_name'    => 'Guest User',
	'bbp_anonymous_email'   => 'guest@example.org',
	'bbp_anonymous_website' => 'https://example.org/',
) );

echo 'cookies-set';
