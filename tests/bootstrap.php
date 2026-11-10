<?php
/**
 * PHPUnit bootstrap file.
 *
 * This repo is not a plugin: the modular sources under src/Pse/ and the loose
 * snippet files under snippets/ are the code under test, so they are required
 * directly at muplugins_loaded instead of a plugin main file. The legacy
 * form plugin is intentionally NOT loaded: phase 1 leaves the sign-ups where
 * they are and the tests only cover what this repository registers.
 *
 * @package Pse
 */

use Yoast\WPTestUtils\WPIntegration;

require_once dirname( __DIR__ ) . '/vendor/yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php';

$_tests_dir = WPIntegration\get_path_to_wp_test_dir();
if ( false === $_tests_dir ) {
	echo PHP_EOL . 'ERROR: The WordPress native unit test bootstrap file could not be found. '
		. 'Please set either the WP_TESTS_DIR or the WP_DEVELOP_DIR environment variable, '
		. 'either in your OS or in a custom phpunit.xml file.' . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . 'includes/functions.php';

/**
 * Manually load the modular application and every versioned snippet file.
 *
 * @return void
 */
function pse_manually_load_snippets() {
	// Modular app source (preferred for tests; the bundle is for Code Snippets only).
	$bootstrap = dirname( __DIR__ ) . '/src/Pse/bootstrap.php';
	if ( is_readable( $bootstrap ) ) {
		require_once $bootstrap;
	}

	$snippet_files = glob( dirname( __DIR__ ) . '/snippets/*.php' );

	if ( false === $snippet_files ) {
		return;
	}

	foreach ( $snippet_files as $snippet_file ) {
		// El bundle generado inlinea las mismas clases que `src/Pse` acaba de
		// cargar: requerirlo también las redeclara y revienta el arranque.
		if ( false !== strpos( basename( $snippet_file ), '.bundle.php' ) ) {
			continue;
		}
		require $snippet_file;
	}
}

tests_add_filter( 'muplugins_loaded', 'pse_manually_load_snippets' );


// Start up the WP testing environment.
WPIntegration\bootstrap_it();
