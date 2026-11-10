<?php
/**
 * Synchronizer for the external educational centres catalogue.
 *
 * @package Pse
 */

namespace Pse\Centre;

use RuntimeException;

/**
 * Sincroniza el catálogo de centros educativos desde la fuente maestra externa.
 *
 * Utiliza manifest.json para verificar versiones y SHA-256 antes de descargar
 * centros.min.json. Reemplaza el catálogo local de forma atómica únicamente tras
 * validar íntegramente la estructura y el contenido.
 */
final class CentreCatalogueSync {

	public const OPTION_MANIFEST_URL = 'pse_centres_manifest_url';

	public const OPTION_CATALOGUE_URL = 'pse_centres_catalogue_url';

	public const OPTION_LOCK = 'pse_centres_sync_lock';

	public const LOCK_TTL = 300;

	public const CRON_HOOK = 'pse_centres_cron_sync';

	public const HTTP_TIMEOUT = 30;

	/**
	 * Register cron schedule and hook.
	 *
	 * @return void
	 */
	public static function register_cron(): void {
		add_action( self::CRON_HOOK, array( self::class, 'cron_sync' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 3600, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Cron callback to sync periodically.
	 *
	 * @return void
	 */
	public static function cron_sync(): void {
		try {
			self::sync( false );
		} catch ( RuntimeException $e ) {
			// Las excepciones en cron quedan registradas en el estado de diagnóstico.
			unset( $e );
		}
	}

	/**
	 * Verify if a URL is a valid absolute HTTPS URL.
	 *
	 * @param string $url URL to validate.
	 * @return bool
	 */
	public static function is_valid_https_url( string $url ): bool {
		$url = trim( $url );
		if ( '' === $url ) {
			return false;
		}
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );
		return 'https' === strtolower( (string) $scheme ) && '' !== trim( (string) $host );
	}

	/**
	 * Get the configured manifest URL.
	 *
	 * @return string
	 */
	public static function manifest_url(): string {
		$url = '';
		if ( defined( 'PSE_CENTRES_MANIFEST_URL' ) ) {
			$url = (string) PSE_CENTRES_MANIFEST_URL;
		}
		if ( '' === $url ) {
			$url = (string) get_option( self::OPTION_MANIFEST_URL, '' );
		}
		/**
		 * Filter the external manifest URL.
		 *
		 * @param string $url Manifest URL.
		 */
		$filtered = apply_filters( 'pse_centres_manifest_url', $url );
		return is_string( $filtered ) ? trim( $filtered ) : '';
	}

	/**
	 * Get the configured catalogue URL (centros.min.json).
	 *
	 * @param string $manifest_url Optional manifest URL to derive default path.
	 * @return string
	 */
	public static function catalogue_url( string $manifest_url = '' ): string {
		$url = '';
		if ( defined( 'PSE_CENTRES_CATALOGUE_URL' ) ) {
			$url = (string) PSE_CENTRES_CATALOGUE_URL;
		}
		if ( '' === $url ) {
			$url = (string) get_option( self::OPTION_CATALOGUE_URL, '' );
		}
		if ( '' === $url && '' !== $manifest_url ) {
			$dir = dirname( $manifest_url );
			if ( 'http:' === $dir || 'https:' === $dir ) {
				$dir = $manifest_url;
			}
			$url = trailingslashit( $dir ) . 'centros.min.json';
		}
		/**
		 * Filter the external catalogue URL.
		 *
		 * @param string $url Catalogue URL.
		 */
		$filtered = apply_filters( 'pse_centres_catalogue_url', $url );
		return is_string( $filtered ) ? trim( $filtered ) : '';
	}

	/**
	 * Acquire execution lock with an ownership token.
	 *
	 * Utiliza add_option() para garantizar concurrencia atómica sin condiciones
	 * de carrera. Si el candado previo expiró (proceso muerto), lo retira y
	 * lo vuelve a reclamar.
	 *
	 * @return string|false Token si se adquirió el candado, false si hay otra sincronización en curso.
	 */
	public static function acquire_lock() {
		$token   = wp_generate_password( 32, false );
		$payload = array(
			'token' => $token,
			'time'  => time(),
		);

		wp_cache_delete( self::OPTION_LOCK, 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		if ( add_option( self::OPTION_LOCK, $payload, '', 'no' ) ) {
			return $token;
		}

		$current = get_option( self::OPTION_LOCK );
		if ( is_array( $current ) && isset( $current['time'] ) ) {
			$elapsed = time() - (int) $current['time'];
			if ( $elapsed > self::LOCK_TTL ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-delete atómico de candado expirado.
				$deleted = $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM $wpdb->options WHERE option_name = %s AND option_value = %s",
						self::OPTION_LOCK,
						maybe_serialize( $current )
					)
				);

				if ( $deleted ) {
					wp_cache_delete( self::OPTION_LOCK, 'options' );
					wp_cache_delete( 'notoptions', 'options' );

					if ( add_option( self::OPTION_LOCK, $payload, '', 'no' ) ) {
						return $token;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Release execution lock, only if the token matches.
	 *
	 * @param string $token Ownership token.
	 * @return bool True if released, false otherwise.
	 */
	public static function release_lock( string $token ): bool {
		wp_cache_delete( self::OPTION_LOCK, 'options' );
		$current = get_option( self::OPTION_LOCK );
		if ( is_array( $current ) && isset( $current['token'] ) && hash_equals( (string) $current['token'], $token ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-delete atómico para liberar candado propio.
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $wpdb->options WHERE option_name = %s AND option_value = %s",
					self::OPTION_LOCK,
					maybe_serialize( $current )
				)
			);
			if ( $deleted ) {
				wp_cache_delete( self::OPTION_LOCK, 'options' );
				wp_cache_delete( 'notoptions', 'options' );
				return true;
			}
		}
		return false;
	}

	/**
	 * Run synchronization from external source.
	 *
	 * @param bool $force Whether to force download even if SHA-256 is unchanged.
	 * @return array{status:string, sha256:string, records:int, active:int}
	 * @throws RuntimeException On download, validation or checksum failure.
	 */
	public static function sync( bool $force = false ): array {
		$token = self::acquire_lock();
		if ( false === $token ) {
			throw new RuntimeException( 'Hay otra sincronización de centros en curso. Espere a que finalice.' );
		}

		$status = CentreCatalogue::status();

		try {
			$manifest_url = self::manifest_url();
			if ( ! self::is_valid_https_url( $manifest_url ) ) {
				throw new RuntimeException( 'URL de manifest de centros inválida o no utiliza HTTPS.' );
			}

			// 1. Descargar y validar manifest.json.
			$manifest = self::manifest( self::download( $manifest_url, 'manifest.json', self::HTTP_TIMEOUT ) );

			// 2. Comparar hash: si no ha cambiado y no se fuerza, salir.
			if ( ! $force && $manifest['sha256'] === $status['sha256'] && ! empty( $status['sha256'] ) && CentreCatalogue::total() > 0 ) {
				$status['last_checked_at'] = current_time( 'mysql' );
				$status['last_error']      = '';
				update_option( CentreCatalogue::OPTION_STATUS, $status, false );

				return array(
					'status'  => 'unchanged',
					'sha256'  => $manifest['sha256'],
					'records' => $status['record_count'],
					'active'  => $status['active_count'],
				);
			}

			// 3. Descargar centros.min.json.
			$catalogue_url = self::catalogue_url( $manifest_url );
			if ( ! self::is_valid_https_url( $catalogue_url ) ) {
				throw new RuntimeException( 'URL del catálogo de centros inválida o no utiliza HTTPS.' );
			}
			$body = self::download( $catalogue_url, 'centros.min.json', 45 );

			// 4. Verificar SHA-256.
			if ( ! hash_equals( $manifest['sha256'], hash( 'sha256', $body ) ) ) {
				throw new RuntimeException( 'El hash SHA-256 del archivo descargado no coincide con el declarado en manifest.json.' );
			}

			// 5. Validar el contenido entero antes de tocar nada.
			$indexed      = self::catalogue( $body, $manifest['records'] );
			$active_count = count( array_filter( array_column( $indexed, 'active' ) ) );

			// 6. Reemplazo atómico.
			update_option( CentreCatalogue::OPTION_CATALOGUE, $indexed, false );

			$status = array(
				'schema_version'       => 1,
				'sha256'               => $manifest['sha256'],
				'catalogue_updated_at' => $manifest['updated_at'],
				'last_checked_at'      => current_time( 'mysql' ),
				'last_success_at'      => current_time( 'mysql' ),
				'last_error'           => '',
				'record_count'         => count( $indexed ),
				'active_count'         => $active_count,
			);
			update_option( CentreCatalogue::OPTION_STATUS, $status, false );

			return array(
				'status'  => 'updated',
				'sha256'  => $manifest['sha256'],
				'records' => count( $indexed ),
				'active'  => $active_count,
			);
		} catch ( RuntimeException $e ) {
			$status['last_checked_at'] = current_time( 'mysql' );
			$status['last_error']      = $e->getMessage();
			update_option( CentreCatalogue::OPTION_STATUS, $status, false );

			throw $e;
		} finally {
			self::release_lock( $token );
		}
	}

	/**
	 * Download a file over HTTPS and return its body.
	 *
	 * @param string $url     URL.
	 * @param string $name    File name, for the error messages.
	 * @param int    $timeout Seconds.
	 * @return string
	 * @throws RuntimeException When the request fails or does not return 200.
	 */
	private static function download( string $url, string $name, int $timeout ): string {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'    => $timeout,
				'sslverify'  => true,
				'user-agent' => 'WordPress/Pse',
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( 'Error al descargar ' . $name . ': ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( sprintf( 'El servidor devolvió HTTP %d al solicitar %s.', $code, $name ) );
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Validate manifest.json and read what the sync needs from it.
	 *
	 * @param string $body Manifest body.
	 * @return array{sha256:string, records:int, updated_at:string}
	 * @throws RuntimeException When it is not a compatible manifest.
	 */
	private static function manifest( string $body ): array {
		$manifest = json_decode( $body, true );
		if ( ! is_array( $manifest ) ) {
			throw new RuntimeException( 'El archivo manifest.json no contiene un JSON válido.' );
		}

		if ( empty( $manifest['schema_version'] ) || 1 !== (int) $manifest['schema_version'] ) {
			throw new RuntimeException( 'Versión de esquema incompatible en manifest.json.' );
		}

		$file = $manifest['files']['centros.min.json'] ?? array();
		if ( empty( $file['sha256'] ) ) {
			throw new RuntimeException( 'El manifest.json no declara la entrada de centros.min.json con su sha256.' );
		}

		return array(
			'sha256'     => trim( (string) $file['sha256'] ),
			'records'    => (int) ( $file['records'] ?? 0 ),
			'updated_at' => isset( $manifest['catalogue_updated_at'] ) && is_scalar( $manifest['catalogue_updated_at'] ) ? trim( (string) $manifest['catalogue_updated_at'] ) : '',
		);
	}

	/**
	 * Validate centros.min.json whole and index it by code.
	 *
	 * @param string $body     Catalogue body.
	 * @param int    $expected Records the manifest declares; 0 if it does not say.
	 * @return array<string, array{code:string, name:string, island:string, municipality:string, type:string, cep:string, eoep:string, active:bool}>
	 * @throws RuntimeException On the first record that is wrong.
	 */
	private static function catalogue( string $body, int $expected ): array {
		$items = json_decode( $body, true );
		if ( ! is_array( $items ) ) {
			throw new RuntimeException( 'El archivo centros.min.json no contiene un array JSON válido.' );
		}

		if ( $expected > 0 && count( $items ) !== $expected ) {
			$msg = sprintf( 'El número de registros (%d) no coincide con el declarado en el manifest (%d).', count( $items ), $expected );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( $msg );
		}

		$indexed = array();
		foreach ( $items as $idx => $item ) {
			$centre = self::record( $item, (int) $idx );
			if ( isset( $indexed[ $centre['code'] ] ) ) {
				$msg = sprintf( 'Código oficial duplicado en centros.min.json: "%s".', $centre['code'] );
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new RuntimeException( $msg );
			}
			$indexed[ $centre['code'] ] = $centre;
		}
		return $indexed;
	}

	/**
	 * Validate one record of the catalogue.
	 *
	 * @param mixed $item Record.
	 * @param int   $idx  Position, for the error messages.
	 * @return array{code:string, name:string, island:string, municipality:string, type:string, cep:string, eoep:string, active:bool}
	 * @throws RuntimeException When the record is wrong.
	 */
	private static function record( $item, int $idx ): array {
		if ( ! is_array( $item ) ) {
			$msg = sprintf( 'Registro en posición %d no es un objeto válido.', $idx );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( $msg );
		}

		$text = static fn( string $key ): string => isset( $item[ $key ] ) && is_scalar( $item[ $key ] ) ? trim( (string) $item[ $key ] ) : '';
		$code = $text( 'code' );
		if ( 1 !== preg_match( '/^\d{8}$/', $code ) ) {
			$msg = sprintf( 'Código de centro inválido en registro %d (debe tener exactamente 8 dígitos): "%s".', $idx, $code );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( $msg );
		}

		if ( '' === $text( 'name' ) ) {
			$msg = sprintf( 'Denominación vacía para el centro con código "%s".', $code );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( $msg );
		}

		if ( ! isset( $item['active'] ) || ! is_bool( $item['active'] ) ) {
			$msg = sprintf( 'El campo "active" debe ser booleano para el centro con código "%s".', $code );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( $msg );
		}

		return array(
			'code'         => $code,
			'name'         => $text( 'name' ),
			'island'       => $text( 'island' ),
			'municipality' => $text( 'municipality' ),
			'type'         => $text( 'type' ),
			'cep'          => $text( 'cep' ),
			// El equipo de orientación de zona del centro, por su código: de él sale la zona (ADR-0004, adenda).
			'eoep'         => $text( 'eoep' ),
			'active'       => $item['active'],
		);
	}
}
