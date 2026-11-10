<?php
/**
 * Demo users, centres, zones and requests for the local environment (wp eval-file / Playground).
 *
 * Idempotente: el usuario que ya existe se deja, y las solicitudes de
 * demostración se crean una sola vez. Todo inventado: los centros, los
 * municipios y las zonas no existen.
 *
 * @package Pse
 */

use Pse\Centre\CentreCatalogue;
use Pse\Centre\Zones;
use Pse\Domain\Vocabulary;
use Pse\PostType\Requests;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( Requests::class ) ) {
	throw new RuntimeException( 'El aplicativo no está cargado: ejecute antes `make bundle && make sync-snippets`.' );
}

/**
 * Ensure a demo user exists with a role.
 *
 * @param string $login  Login.
 * @param string $role   Role slug.
 * @param string $name   Display name.
 * @param string $centre Centre code of the account, or ''.
 * @return int User ID.
 * @throws RuntimeException If the user cannot be created.
 */
function pse_seed_user( string $login, string $role, string $name, string $centre = '' ): int {
	$user = get_user_by( 'login', $login );
	$id   = $user ? (int) $user->ID : wp_insert_user(
		array(
			'user_login'   => $login,
			'user_pass'    => 'password',
			'user_email'   => $login . '@example.org',
			'display_name' => $name,
			'role'         => $role,
		)
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( esc_html( $id->get_error_message() ) );
	}
	if ( '' !== $centre ) {
		update_user_meta( (int) $id, 'pse_centre_code', $centre );
	}
	if ( ! $user ) {
		echo esc_html( 'Usuario ' . $login . ' (' . $role . ').' ) . "\n";
	}
	return (int) $id;
}

// Centros inventados: código => nombre, municipio, isla y zona.
$pse_centres = array(
	'90000001' => array( 'IES Ejemplo Norte', 'Municipio A', 'Isla 1', 'Zona 1' ),
	'90000002' => array( 'CEIP Ejemplo Sur', 'Municipio A', 'Isla 1', 'Zona 1' ),
	'90000003' => array( 'CEO Ejemplo Este', 'Municipio B', 'Isla 1', 'Zona 1' ),
	'90000004' => array( 'IES Ejemplo Oeste', 'Municipio B', 'Isla 1', 'Zona 2' ),
	'90000005' => array( 'CEO Ejemplo Cumbre', 'Municipio C', 'Isla 1', 'Zona 2' ),
	'90000006' => array( 'IES Ejemplo Costa', 'Municipio D', 'Isla 2', 'Zona 3' ),
	'90000007' => array( 'CEIP Ejemplo Valle', 'Municipio D', 'Isla 2', 'Zona 3' ),
	'90000008' => array( 'IES Ejemplo Llano', 'Municipio E', 'Isla 2', 'Zona 3' ),
);

// El catálogo de centros, solo si no hay uno: en un sitio real lo trae su sincronización.
if ( ! CentreCatalogue::all() ) {
	$pse_catalogue = array();
	foreach ( $pse_centres as $pse_code => $pse_centre ) {
		$pse_catalogue[ $pse_code ] = array(
			'code'         => $pse_code,
			'name'         => $pse_centre[0],
			'island'       => $pse_centre[2],
			'municipality' => $pse_centre[1],
			'type'         => '',
			'cep'          => '',
			'active'       => true,
		);
	}
	update_option( CentreCatalogue::OPTION_CATALOGUE, $pse_catalogue, false );
	echo "Catálogo de centros de demostración.\n";
}

if ( ! Zones::centres() ) {
	Zones::save(
		array_map( static fn( array $centre ): string => $centre[3], $pse_centres ),
		array(
			'Zona 1' => array( 'psicologia@example.org' ),
			'Zona 2' => array( 'psicologia@example.org' ),
			'Zona 3' => array( 'psicologia2@example.org' ),
		)
	);
	echo "Zonas de demostración.\n";
}

$pse_head = pse_seed_user( 'direccion', 'pse_school_head', 'Dirección de ejemplo', '90000001' );
pse_seed_user( 'direccion2', 'pse_school_head', 'Dirección de ejemplo 2', '90000006' );
pse_seed_user( 'psicologia', 'pse_psychologist', 'Psicología de ejemplo' );
pse_seed_user( 'psicologia2', 'pse_psychologist', 'Psicología de ejemplo 2' );
pse_seed_user( 'coordinacion', 'pse_coordinator', 'Coordinación de ejemplo' );

// Solicitudes repartidas por las diez últimas semanas, siempre las mismas: sin
// azar, para que dos provisiones den la misma pantalla.
if ( ! Requests::query( array( 'per_page' => 1 ) )->found_posts ) {
	$pse_codes    = array_keys( $pse_centres );
	$pse_subjects = array_keys( Vocabulary::subjects() );
	$pse_total    = 48;
	for ( $pse_i = 0; $pse_i < $pse_total; $pse_i++ ) {
		$pse_code   = $pse_codes[ ( $pse_i * 3 + intdiv( $pse_i, 8 ) ) % count( $pse_codes ) ];
		$pse_centre = $pse_centres[ $pse_code ];
		$pse_days   = (int) round( ( $pse_total - 1 - $pse_i ) * 70 / $pse_total );
		$pse_status = Vocabulary::STATUS_ATTENDED;
		if ( $pse_days < 6 ) {
			$pse_status = Vocabulary::STATUS_PENDING;
		} elseif ( $pse_days < 21 ) {
			$pse_status = Vocabulary::STATUS_IN_PROGRESS;
		}
		$pse_date   = gmdate( 'Y-m-d 09:00:00', time() - $pse_days * DAY_IN_SECONDS );
		$pse_number = Requests::next_number( (int) substr( $pse_date, 0, 4 ) );
		wp_insert_post(
			array(
				'post_type'   => Requests::TYPE,
				'post_status' => 'publish',
				'post_title'  => $pse_number,
				'post_author' => $pse_head,
				'post_date'   => $pse_date,
				'meta_input'  => array(
					Requests::NUMBER       => $pse_number,
					Requests::CENTRE_CODE  => $pse_code,
					Requests::CENTRE_NAME  => $pse_centre[0],
					Requests::MUNICIPALITY => $pse_centre[1],
					Requests::ISLAND       => $pse_centre[2],
					Requests::ZONE         => $pse_centre[3],
					Requests::SUBJECT      => $pse_subjects[ ( $pse_i * 2 + intdiv( $pse_i, 5 ) ) % count( $pse_subjects ) ],
					Requests::STATUS       => $pse_status,
				),
			)
		);
	}
	echo esc_html( $pse_total . ' solicitudes de demostración.' ) . "\n";
}
