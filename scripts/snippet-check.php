<?php
/**
 * Run Code Snippets' own save-time validation over every PSE snippet.
 *
 * Saving an active snippet makes the plugin evaluate its code a second time
 * in the same request. A snippet without the double-eval guard either gets
 * disabled in silence («Cannot redeclare class») or kills the request. This
 * reproduces that check and fails loudly instead.
 *
 * Usage (Docker):
 *   npx wp-env run cli wp eval-file wp-content/pse-dev/scripts/snippet-check.php
 *
 * Runs under `wp eval-file`; never depends on WP_CLI. Idempotent: reads only.
 *
 * @package Pse
 */

if ( ! function_exists( 'Code_Snippets\get_snippets' ) || ! function_exists( 'Code_Snippets\test_snippet_code' ) ) {
	echo "AVISO: Code Snippets no está activo; no hay nada que comprobar.\n";
	return;
}

$pse_fallos = 0;

// First pass: the snippets that ran on this request must have actually loaded
// (a guard that trips too early leaves the classes declared but nothing booted).
// Lo que se comprueba es el efecto observable de cada snippet, no que exista
// una clase: el bundle registra el CPT en `init`, y el snippet de roles define
// sus funciones al cargarse.
$pse_cargado = array(
	'el aplicativo (CPT pse_request registrado)'  => post_type_exists( 'pse_request' ),
	'los roles (pse_register_roles() disponible)' => function_exists( 'pse_register_roles' ),
);
foreach ( $pse_cargado as $pse_que => $pse_ok ) {
	if ( ! $pse_ok ) {
		++$pse_fallos;
	}
	echo esc_html( sprintf( '%s Primera pasada: %s', $pse_ok ? '✓' : '✗', $pse_que ) ) . "\n";
}

// Second pass: what the plugin does when an active snippet is saved.
foreach ( Code_Snippets\get_snippets() as $pse_snippet ) {
	if ( 0 !== strpos( (string) $pse_snippet->name, 'PSE' ) ) {
		continue;
	}
	// 3.10.x leaves the verdict in `code_error` (message, line); a fatal in the
	// second eval() would not even get here, which is a verdict too.
	$pse_snippet->code_error = null;
	Code_Snippets\test_snippet_code( $pse_snippet );
	if ( empty( $pse_snippet->code_error ) ) {
		echo esc_html( sprintf( '✓ %s', $pse_snippet->name ) ) . "\n";
		continue;
	}
	++$pse_fallos;
	echo esc_html( sprintf( '✗ %s: %s', $pse_snippet->name, wp_json_encode( $pse_snippet->code_error, JSON_UNESCAPED_UNICODE ) ) ) . "\n";
}

if ( $pse_fallos > 0 ) {
	echo esc_html( sprintf( '%d snippet(s) no sobreviven al guardado de Code Snippets.', $pse_fallos ) ) . "\n";
	exit( 1 );
}
