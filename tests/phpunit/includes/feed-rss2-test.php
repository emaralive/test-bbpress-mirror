<?php

/**
 * Run an RSS2 feed in another process so its exit does not end PHPUnit.
 *
 * @param string $mode Feed fixture to run.
 * @return string RSS XML without the test bootstrap diagnostics.
 */
function bbp_test_run_feed_rss2( $mode ) {
	$script  = __DIR__ . '/feed-rss2-runner.php';
	$command = array(
		PHP_BINARY,
		'-d',
		'output_buffering=1048576',
		$script,
		$mode,
		is_multisite() ? 'multisite' : 'single',
	);
	$pipes   = array();
	$process = proc_open( $command, array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'redirect', 1 ),
	), $pipes, dirname( __DIR__, 3 ) );

	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'Could not start the feed test process.' );
	}

	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	fclose( $pipes[1] );
	$status = proc_close( $process );
	if ( 0 !== $status ) {
		throw new RuntimeException( 'Feed test process failed: ' . $output );
	}

	$start = strpos( $output, '<?xml' );
	if ( false === $start ) {
		throw new RuntimeException( 'Feed test process did not output XML: ' . $output );
	}

	return substr( $output, $start );
}
