<?php
/**
 * Sync the versioned snippets/*.php files into the Code Snippets plugin.
 *
 * Runs under `wp eval-file` (Docker) and under Playground `runPHP`/require —
 * it must never depend on WP_CLI. Idempotent: existing snippets (matched by
 * exact name) are updated, never duplicated.
 *
 * Usage:
 *   npx wp-env run cli wp eval-file wp-content/pse-dev/scripts/sync-snippets.php
 *
 * @package Pse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/lib/snippet-sync.php';

if ( ! pse_code_snippets_is_active() ) {
	throw new RuntimeException( 'El plugin Code Snippets no está activo; no se pueden sincronizar los snippets.' );
}

$pse_snippets_dir = dirname( __DIR__ ) . '/snippets';

echo esc_html( sprintf( 'Sincronizando snippets desde %s ...', $pse_snippets_dir ) ) . "\n";

$pse_results = pse_sync_snippets_from_dir( $pse_snippets_dir );

if ( empty( $pse_results ) ) {
	throw new RuntimeException( 'No se ha encontrado ningún fichero de snippet que sincronizar.' );
}

$pse_created   = 0;
$pse_updated   = 0;
$pse_unchanged = 0;
$pse_errors    = 0;

foreach ( $pse_results as $pse_file => $pse_result ) {
	if ( 'error' === $pse_result['status'] ) {
		++$pse_errors;
		echo esc_html( sprintf( '- %s: ERROR — %s', $pse_file, $pse_result['error'] ) ) . "\n";
		continue;
	}

	switch ( $pse_result['status'] ) {
		case 'created':
			++$pse_created;
			$pse_action = 'creado';
			break;
		case 'updated':
			++$pse_updated;
			$pse_action = 'actualizado';
			break;
		case 'unchanged':
			++$pse_unchanged;
			$pse_action = $pse_result['reactivated'] ? 'sin cambios, reactivado' : 'sin cambios';
			break;
		default:
			++$pse_errors;
			echo esc_html( sprintf( '- %s: ERROR — estado de sincronización inesperado: %s', $pse_file, $pse_result['status'] ) ) . "\n";
			continue 2;
	}

	if ( $pse_result['active'] ) {
		$pse_state = '' === $pse_result['error']
			? 'activo'
			: sprintf( 'activo con AVISO — %s', $pse_result['error'] );
	} else {
		++$pse_errors;
		$pse_state = sprintf( 'ERROR al activar: %s', $pse_result['error'] );
	}

	echo esc_html(
		sprintf(
			'- %1$s → «%2$s» (ID %3$d): %4$s, %5$s',
			$pse_file,
			$pse_result['name'],
			$pse_result['id'],
			$pse_action,
			$pse_state
		)
	) . "\n";
}

echo esc_html(
	sprintf(
		'Resumen: %1$d creado(s), %2$d actualizado(s), %3$d sin cambios, %4$d error(es).',
		$pse_created,
		$pse_updated,
		$pse_unchanged,
		$pse_errors
	)
) . "\n";

if ( $pse_errors > 0 ) {
	throw new RuntimeException( 'La sincronización de snippets terminó con errores; revise el resumen anterior.' );
}
