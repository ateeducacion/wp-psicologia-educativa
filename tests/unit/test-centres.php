<?php
/**
 * Tests for the educational centres catalogue and the venue of an action.
 *
 * @package Pse
 */

use Pse\Centre\CentreCatalogue;
use Pse\Centre\CentreCatalogueSync;
use Pse\Centre\CentreSettings;

/**
 * El centro donde se imparte una acción se elige por su código, de una copia
 * local del catálogo de centros que se pone al día desde su fuente (ADR-0007).
 */
class Test_Centres extends WP_UnitTestCase {

	/**
	 * Manifest URL of the tests.
	 */
	private const MANIFEST = 'https://centros.example.org/manifest.json';

	/**
	 * Requests the fake source has answered.
	 *
	 * @var string[]
	 */
	private $requests = array();

	/**
	 * Roles, caps and meta in place; no catalogue.
	 */
	public function set_up() {
		parent::set_up();
		pse_register_roles();
		\Pse\Access\Access::grant_caps_to_roles();
		$this->requests = array();
		foreach ( array( CentreCatalogue::OPTION_CATALOGUE, CentreCatalogue::OPTION_STATUS, CentreCatalogueSync::OPTION_MANIFEST_URL, CentreCatalogueSync::OPTION_CATALOGUE_URL, CentreCatalogueSync::OPTION_LOCK ) as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Undo the fake source.
	 */
	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'pse_centres_manifest_url' );
		wp_clear_scheduled_hook( CentreCatalogueSync::CRON_HOOK );
		parent::tear_down();
	}

	/**
	 * Centres of the tests: two active, one closed.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function centres(): array {
		return array(
			array(
				'code'         => '90000102',
				'name'         => 'IES Ejemplo',
				'island'       => 'Zona Sur',
				'municipality' => 'Municipio 2',
				'type'         => 'IES',
				'cep'          => '90000900',
				'active'       => true,
			),
			array(
				'code'         => '90000101',
				'name'         => 'CEIP Ejemplo',
				'island'       => 'Zona Norte',
				'municipality' => 'Municipio 1',
				'type'         => 'CEIP',
				'active'       => true,
			),
			array(
				'code'         => '90000103',
				'name'         => 'CEO Cerrado',
				'island'       => 'Zona Norte',
				'municipality' => 'Municipio 1',
				'type'         => 'CEO',
				'active'       => false,
			),
		);
	}

	/**
	 * Serve a catalogue from the fake source.
	 *
	 * @param string|array<int, mixed>|WP_Error $catalogue Records, a raw body or an error.
	 * @param array<string, mixed>              $manifest  Manifest overrides.
	 * @param int                               $code      HTTP status of the catalogue.
	 * @return void
	 */
	private function serve( $catalogue, array $manifest = array(), int $code = 200 ): void {
		$body = is_array( $catalogue ) ? (string) wp_json_encode( $catalogue ) : $catalogue;
		// Lo que dice el caso sustituye solo esa clave del manifest.
		$manifest = array_replace_recursive(
			array(
				'schema_version'       => 1,
				'catalogue_updated_at' => '2026-10-20T10:00:00Z',
				'files'                => array(
					'centros.min.json' => array(
						'sha256'  => is_string( $body ) ? hash( 'sha256', $body ) : str_repeat( 'a', 64 ),
						'records' => is_array( $catalogue ) ? count( $catalogue ) : 0,
					),
				),
			),
			$manifest
		);
		update_option( CentreCatalogueSync::OPTION_MANIFEST_URL, self::MANIFEST );
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $body, $manifest, $code ) {
				unset( $pre, $args );
				$this->requests[] = basename( $url );
				if ( 'manifest.json' === basename( $url ) ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => (string) wp_json_encode( $manifest ),
					);
				}
				return is_wp_error( $body ) ? $body : array(
					'response' => array( 'code' => $code ),
					'body'     => $body,
				);
			},
			10,
			3
		);
	}

	/**
	 * Sync and return the error message, or '' if it went well.
	 *
	 * @return string
	 */
	private function sync_error(): string {
		try {
			CentreCatalogueSync::sync( true );
			return '';
		} catch ( RuntimeException $e ) {
			return $e->getMessage();
		}
	}

	/**
	 * A logged-in user with a role.
	 *
	 * @param string $role Role.
	 * @return int
	 */
	private function as_role( string $role ): int {
		$id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $id );
		return $id;
	}

	/**
	 * Un catálogo bueno se guarda entero, indexado por código, con su estado;
	 * la lista ofrece solo los activos, por orden alfabético.
	 */
	public function test_a_valid_catalogue_is_stored() {
		$this->serve( $this->centres() );

		$result = CentreCatalogueSync::sync( true );

		$this->assertSame( 'updated', $result['status'] );
		$this->assertSame( 3, CentreCatalogue::total() );
		$this->assertSame( 2, CentreCatalogue::active_count() );
		$this->assertSame(
			array(
				'90000101' => 'CEIP Ejemplo',
				'90000102' => 'IES Ejemplo',
			),
			CentreCatalogue::active_options()
		);
		$this->assertTrue( CentreCatalogue::is_active( '90000101' ) );
		$this->assertFalse( CentreCatalogue::is_active( '90000103' ), 'un centro cerrado se guarda, pero no está activo' );
		$this->assertSame( 'CEO Cerrado', CentreCatalogue::find( '90000103' )['name'] );
		$this->assertSame( '90000900', CentreCatalogue::find( '90000102' )['cep'], 'el centro del profesorado del centro se guarda' );
		$this->assertSame( '', CentreCatalogue::find( '90000101' )['cep'], 'y si el catálogo no lo trae, queda vacío' );
		$this->assertTrue( CentreCatalogue::knows_cep() );
		$this->assertSame( array( '90000102' => 'IES Ejemplo' ), CentreCatalogue::active_options( '90000900' ), 'la lista se acota por el centro del profesorado' );
		$this->assertSame( array( '90000101' => 'CEIP Ejemplo' ), CentreCatalogue::active_options( '', 'Zona Norte' ), 'y por isla, cuando el catálogo no trae el centro de referencia' );
		$this->assertSame( '2026-10-20T10:00:00Z', CentreCatalogue::status()['catalogue_updated_at'] );
		$this->assertSame( '', CentreCatalogue::status()['last_error'] );
		$this->assertSame( array( 'manifest.json', 'centros.min.json' ), $this->requests, 'el catálogo se busca junto al manifest' );
	}

	/**
	 * Lo que llega mal no se guarda: se sigue con la última copia buena y el
	 * motivo queda en el estado.
	 */
	public function test_a_bad_download_keeps_the_last_good_catalogue() {
		$this->serve( $this->centres() );
		CentreCatalogueSync::sync( true );

		$twice   = $this->centres();
		$twice[] = $twice[0];
		$no_name = $this->centres();

		$no_name[1]['name'] = '';
		$cases              = array(
			'el hash no cuadra'     => array( '[]', array( 'files' => array( 'centros.min.json' => array( 'sha256' => str_repeat( 'a', 64 ) ) ) ), 200, 'SHA-256' ),
			'no es JSON'            => array( 'no es json', array(), 200, 'JSON' ),
			'un código repetido'    => array( $twice, array(), 200, 'duplicado' ),
			'un centro sin nombre'  => array( $no_name, array(), 200, 'Denominación vacía' ),
			'otro esquema'          => array( $this->centres(), array( 'schema_version' => 2 ), 200, 'esquema' ),
			'el servidor falla'     => array( $this->centres(), array(), 500, 'HTTP 500' ),
			'no hay red'            => array( new WP_Error( 'http_request_failed', 'sin conexión' ), array(), 200, 'sin conexión' ),
			'faltan registros'      => array( array_slice( $this->centres(), 0, 2 ), array( 'files' => array( 'centros.min.json' => array( 'records' => 3 ) ) ), 200, 'número de registros' ),
			'un código de 7 cifras' => array( array( array( 'code' => '9000010' ) + $this->centres()[0] ), array(), 200, '8 dígitos' ),
			'activo no es booleano' => array( array( array( 'active' => 'sí' ) + $this->centres()[0] ), array(), 200, 'booleano' ),
		);
		foreach ( $cases as $name => list( $catalogue, $manifest, $code, $message ) ) {
			$this->serve( $catalogue, $manifest, $code );
			$this->assertStringContainsString( $message, $this->sync_error(), $name );
			$this->assertSame( 3, CentreCatalogue::total(), $name );
			$this->assertStringContainsString( $message, CentreCatalogue::status()['last_error'], $name );
		}
	}

	/**
	 * Si el hash del manifest no ha cambiado, la copia diaria no descarga el
	 * catálogo otra vez; forzarla, sí.
	 */
	public function test_the_same_catalogue_is_not_downloaded_again() {
		$this->serve( $this->centres() );
		CentreCatalogueSync::sync( true );
		$this->requests = array();

		$this->assertSame( 'unchanged', CentreCatalogueSync::sync( false )['status'] );
		$this->assertSame( array( 'manifest.json' ), $this->requests );

		$this->assertSame( 'updated', CentreCatalogueSync::sync( true )['status'] );
	}

	/**
	 * Solo se descarga por https, y sin dirección no se descarga nada.
	 */
	public function test_only_https_sources() {
		$this->assertTrue( CentreCatalogueSync::is_valid_https_url( self::MANIFEST ) );
		$this->assertFalse( CentreCatalogueSync::is_valid_https_url( 'http://centros.example.org/manifest.json' ) );
		$this->assertFalse( CentreCatalogueSync::is_valid_https_url( 'https://' ) );
		$this->assertFalse( CentreCatalogueSync::is_valid_https_url( '' ) );

		$this->assertSame( '', CentreCatalogueSync::manifest_url(), 'no hay dirección por defecto' );
		$this->assertStringContainsString( 'HTTPS', $this->sync_error() );

		add_filter( 'pse_centres_manifest_url', static fn(): string => 'http://centros.example.org/manifest.json' );
		$this->assertStringContainsString( 'HTTPS', $this->sync_error() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Una sincronización a la vez; un candado olvidado caduca.
	 */
	public function test_one_sync_at_a_time() {
		$this->serve( $this->centres() );
		$token = CentreCatalogueSync::acquire_lock();
		$this->assertIsString( $token );

		$this->assertStringContainsString( 'otra sincronización', $this->sync_error() );
		$this->assertFalse( CentreCatalogueSync::release_lock( 'otro' ), 'solo lo suelta quien lo tiene' );
		$this->assertTrue( CentreCatalogueSync::release_lock( $token ) );
		$this->assertSame( '', $this->sync_error() );

		update_option(
			CentreCatalogueSync::OPTION_LOCK,
			array(
				'token' => 'viejo',
				'time'  => time() - CentreCatalogueSync::LOCK_TTL - 1,
			),
			false
		);
		$this->assertSame( '', $this->sync_error(), 'un candado caducado no bloquea' );
	}

	/**
	 * La copia se pone al día una vez al día, y un fallo en la tarea no rompe
	 * nada: queda en el estado.
	 */
	public function test_the_catalogue_is_updated_daily() {
		CentreCatalogueSync::register_cron();
		$this->assertNotFalse( wp_next_scheduled( CentreCatalogueSync::CRON_HOOK ) );
		$this->assertSame( 'daily', wp_get_schedule( CentreCatalogueSync::CRON_HOOK ) );

		CentreCatalogueSync::cron_sync();
		$this->assertStringContainsString( 'HTTPS', CentreCatalogue::status()['last_error'] );
	}

	/**
	 * La administración pone la dirección y actualiza el catálogo desde
	 * «Ajustes → Centros educativos»; nadie más entra.
	 */
	public function test_the_administration_sets_the_source_and_updates() {
		$this->as_role( 'administrator' );
		$this->assertSame( 'no_source', CentreSettings::submit( '' ) );
		$this->assertSame( 'bad_url', CentreSettings::submit( 'http://centros.example.org/manifest.json' ) );
		$this->assertSame( '', get_option( CentreCatalogueSync::OPTION_MANIFEST_URL, '' ), 'una dirección sin https no se guarda' );

		$this->serve( $this->centres() );
		delete_option( CentreCatalogueSync::OPTION_MANIFEST_URL );
		$this->assertSame( 'updated', CentreSettings::submit( self::MANIFEST ) );
		$this->assertSame( self::MANIFEST, get_option( CentreCatalogueSync::OPTION_MANIFEST_URL ) );
		$this->assertSame( 3, CentreCatalogue::total() );

		$this->serve( 'no es json' );
		$this->assertSame( 'error', CentreSettings::submit( null ), 'sin dirección en el formulario, se queda la que había' );
		$this->assertSame( 3, CentreCatalogue::total() );

		ob_start();
		CentreSettings::render();
		$page = ob_get_clean();
		$this->assertStringContainsString( '3, de ellos 2 activos', $page );
		$this->assertStringContainsString( 'JSON', $page, 'el último error se ve' );

		$this->as_role( 'pse_coordinator' );
		$this->expectException( WPDieException::class );
		CentreSettings::render();
	}
}
