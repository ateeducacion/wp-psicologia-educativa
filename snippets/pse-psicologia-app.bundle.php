<?php
/**
 * Snippet Name: PSE — Solicitudes de intervención (CPT)
 * Description: Solicitudes de intervención de psicología educativa: los centros solicitan, cada zona atiende las suyas y la coordinación las sigue. Código generado desde src/Pse — no editar a mano; ejecutar php build/pack-snippet.php.
 * Scope: global
 * Priority: 15
 *
 * @package Pse
 * @version 0.1.1
 */

// phpcs:disable








namespace Pse\Domain;





defined( 'ABSPATH' ) || exit;





if ( \defined( 'PSE_BUNDLE_LOADED' ) ) {
	return;
}
\define( 'PSE_BUNDLE_LOADED', true );









if ( ! class_exists( 'Pse\Domain\Vocabulary', false ) ) {
final class Vocabulary {

	public const STATUS_PENDING     = 'pending';
	public const STATUS_IN_PROGRESS = 'in_progress';
	public const STATUS_ATTENDED    = 'attended';






	public static function subjects(): array {
		$default = array(
			'suicidal_behaviour' => 'Conducta suicida o autolesiva',
			'bullying'           => 'Acoso o ciberacoso escolar',
			'gender'             => 'Violencia de género o identidad de género',
			'distress'           => 'Malestar emocional',
			'group'              => 'Intervención grupal o preventiva',
			'advice'             => 'Asesoramiento al profesorado o a las familias',
			'other'              => 'Otro asunto',
		);






		$subjects = (array) apply_filters( 'pse_subjects', $default );
		$out      = array();
		foreach ( $subjects as $slug => $label ) {
			$slug  = sanitize_key( (string) $slug );
			$label = trim( (string) $label );
			if ( '' !== $slug && '' !== $label ) {
				$out[ $slug ] = $label;
			}
		}
		return $out ? $out : $default;
	}







	public static function subject_label( string $slug ): string {
		return self::subjects()[ $slug ] ?? $slug;
	}






	public static function states(): array {
		return array(
			self::STATUS_PENDING     => 'Pendiente',
			self::STATUS_IN_PROGRESS => 'En atención',
			self::STATUS_ATTENDED    => 'Atendida',
		);
	}







	public static function state_label( string $slug ): string {
		return self::states()[ $slug ] ?? self::states()[ self::STATUS_PENDING ];
	}







	public static function state_plural( string $slug ): string {
		$plural = array(
			self::STATUS_PENDING     => 'Pendientes',
			self::STATUS_IN_PROGRESS => 'En atención',
			self::STATUS_ATTENDED    => 'Atendidas',
		);
		return $plural[ $slug ] ?? '';
	}
}
}








namespace Pse\Domain;





if ( ! class_exists( 'Pse\Domain\Stats', false ) ) {
final class Stats {







	public static function of( array $rows ): array {
		$out = array(
			'total'        => count( $rows ),
			'status'       => array_fill_keys( array_keys( Vocabulary::states() ), 0 ),
			'subject'      => array(),
			'municipality' => array(),
			'island'       => array(),
			'zone'         => array(),
			'month'        => array(),
		);
		foreach ( $rows as $row ) {
			foreach ( array( 'status', 'subject', 'municipality', 'island', 'zone' ) as $dimension ) {
				$value                       = '' !== (string) $row[ $dimension ] ? (string) $row[ $dimension ] : '—';
				$out[ $dimension ][ $value ] = ( $out[ $dimension ][ $value ] ?? 0 ) + 1;
			}
			$month                  = substr( (string) $row['date'], 0, 7 );
			$out['month'][ $month ] = ( $out['month'][ $month ] ?? 0 ) + 1;
		}
		foreach ( array( 'subject', 'municipality', 'island', 'zone' ) as $dimension ) {
			arsort( $out[ $dimension ] );
		}
		ksort( $out['month'] );
		return $out;
	}
}
}








namespace Pse\Centre;









if ( ! class_exists( 'Pse\Centre\CentreCatalogue', false ) ) {
final class CentreCatalogue {

	public const OPTION_CATALOGUE = 'pse_centres_catalogue';

	public const OPTION_STATUS = 'pse_centres_catalogue_status';






	public static function all(): array {
		$raw = get_option( self::OPTION_CATALOGUE, array() );
		return is_array( $raw ) ? $raw : array();
	}






	public static function all_active(): array {
		$all = self::all();
		$out = array();
		foreach ( $all as $code => $centre ) {
			if ( ! empty( $centre['active'] ) ) {
				$out[ $code ] = $centre;
			}
		}
		return $out;
	}













	public static function active_options( string $cep = '', string $island = '' ): array {
		$active = self::all_active();
		$key    = '' !== $cep && self::knows_cep() ? 'cep' : 'island';
		$value  = 'cep' === $key ? $cep : $island;
		if ( '' !== $value ) {
			$active = array_filter( $active, static fn( $centre ) => isset( $centre[ $key ] ) && (string) $centre[ $key ] === $value );
		}
		$out = array();
		foreach ( $active as $code => $centre ) {
			$name = isset( $centre['name'] ) ? trim( (string) $centre['name'] ) : '';
			if ( '' !== $name ) {
				$out[ (string) $code ] = $name;
			}
		}

		uasort(
			$out,
			static function ( string $a, string $b ): int {
				return strcoll( $a, $b );
			}
		);

		return $out;
	}










	public static function knows_cep(): bool {
		foreach ( self::all() as $centre ) {
			if ( isset( $centre['cep'] ) && '' !== trim( (string) $centre['cep'] ) ) {
				return true;
			}
		}
		return false;
	}











	public static function find_by_name( string $name ): ?array {
		$name = trim( $name );
		if ( '' === $name ) {
			return null;
		}
		foreach ( self::all() as $centre ) {
			if ( isset( $centre['name'] ) && trim( (string) $centre['name'] ) === $name ) {
				return $centre;
			}
		}
		return null;
	}







	public static function find( string $code ): ?array {
		$code = trim( $code );
		if ( '' === $code ) {
			return null;
		}
		$all = self::all();
		return isset( $all[ $code ] ) && is_array( $all[ $code ] ) ? $all[ $code ] : null;
	}











	public static function label( string $code, string $name = '' ): string {
		if ( '' === $name ) {
			$name = (string) ( self::find( $code )['name'] ?? '' );
		}
		return '' === $code ? $name : trim( $name . ' (' . $code . ')' );
	}







	public static function is_active( string $code ): bool {
		$centre = self::find( $code );
		return null !== $centre && ! empty( $centre['active'] );
	}






	public static function status(): array {
		$defaults = array(
			'schema_version'       => 0,
			'sha256'               => '',
			'catalogue_updated_at' => '',
			'last_checked_at'      => '',
			'last_success_at'      => '',
			'last_error'           => '',
			'record_count'         => 0,
			'active_count'         => 0,
		);

		$raw = get_option( self::OPTION_STATUS, array() );
		if ( ! is_array( $raw ) ) {
			return $defaults;
		}

		return array_merge( $defaults, $raw );
	}






	public static function total(): int {
		return count( self::all() );
	}






	public static function active_count(): int {
		return count( self::all_active() );
	}
}
}








namespace Pse\Centre;

use RuntimeException;








if ( ! class_exists( 'Pse\Centre\CentreCatalogueSync', false ) ) {
final class CentreCatalogueSync {

	public const OPTION_MANIFEST_URL = 'pse_centres_manifest_url';

	public const OPTION_CATALOGUE_URL = 'pse_centres_catalogue_url';

	public const OPTION_LOCK = 'pse_centres_sync_lock';

	public const LOCK_TTL = 300;

	public const CRON_HOOK = 'pse_centres_cron_sync';

	public const HTTP_TIMEOUT = 30;






	public static function register_cron(): void {
		add_action( self::CRON_HOOK, array( self::class, 'cron_sync' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 3600, 'daily', self::CRON_HOOK );
		}
	}






	public static function cron_sync(): void {
		try {
			self::sync( false );
		} catch ( RuntimeException $e ) {

			unset( $e );
		}
	}







	public static function is_valid_https_url( string $url ): bool {
		$url = trim( $url );
		if ( '' === $url ) {
			return false;
		}
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );
		return 'https' === strtolower( (string) $scheme ) && '' !== trim( (string) $host );
	}






	public static function manifest_url(): string {
		$url = '';
		if ( defined( 'PSE_CENTRES_MANIFEST_URL' ) ) {
			$url = (string) PSE_CENTRES_MANIFEST_URL;
		}
		if ( '' === $url ) {
			$url = (string) get_option( self::OPTION_MANIFEST_URL, '' );
		}





		$filtered = apply_filters( 'pse_centres_manifest_url', $url );
		return is_string( $filtered ) ? trim( $filtered ) : '';
	}







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





		$filtered = apply_filters( 'pse_centres_catalogue_url', $url );
		return is_string( $filtered ) ? trim( $filtered ) : '';
	}










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







	public static function release_lock( string $token ): bool {
		wp_cache_delete( self::OPTION_LOCK, 'options' );
		$current = get_option( self::OPTION_LOCK );
		if ( is_array( $current ) && isset( $current['token'] ) && hash_equals( (string) $current['token'], $token ) ) {
			global $wpdb;

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


			$manifest = self::manifest( self::download( $manifest_url, 'manifest.json', self::HTTP_TIMEOUT ) );


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


			$catalogue_url = self::catalogue_url( $manifest_url );
			if ( ! self::is_valid_https_url( $catalogue_url ) ) {
				throw new RuntimeException( 'URL del catálogo de centros inválida o no utiliza HTTPS.' );
			}
			$body = self::download( $catalogue_url, 'centros.min.json', 45 );


			if ( ! hash_equals( $manifest['sha256'], hash( 'sha256', $body ) ) ) {
				throw new RuntimeException( 'El hash SHA-256 del archivo descargado no coincide con el declarado en manifest.json.' );
			}


			$indexed      = self::catalogue( $body, $manifest['records'] );
			$active_count = count( array_filter( array_column( $indexed, 'active' ) ) );


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

			throw new RuntimeException( 'Error al descargar ' . $name . ': ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {

			throw new RuntimeException( sprintf( 'El servidor devolvió HTTP %d al solicitar %s.', $code, $name ) );
		}

		return wp_remote_retrieve_body( $response );
	}








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









	private static function catalogue( string $body, int $expected ): array {
		$items = json_decode( $body, true );
		if ( ! is_array( $items ) ) {
			throw new RuntimeException( 'El archivo centros.min.json no contiene un array JSON válido.' );
		}

		if ( $expected > 0 && count( $items ) !== $expected ) {
			$msg = sprintf( 'El número de registros (%d) no coincide con el declarado en el manifest (%d).', count( $items ), $expected );

			throw new RuntimeException( $msg );
		}

		$indexed = array();
		foreach ( $items as $idx => $item ) {
			$centre = self::record( $item, (int) $idx );
			if ( isset( $indexed[ $centre['code'] ] ) ) {
				$msg = sprintf( 'Código oficial duplicado en centros.min.json: "%s".', $centre['code'] );

				throw new RuntimeException( $msg );
			}
			$indexed[ $centre['code'] ] = $centre;
		}
		return $indexed;
	}









	private static function record( $item, int $idx ): array {
		if ( ! is_array( $item ) ) {
			$msg = sprintf( 'Registro en posición %d no es un objeto válido.', $idx );

			throw new RuntimeException( $msg );
		}

		$text = static fn( string $key ): string => isset( $item[ $key ] ) && is_scalar( $item[ $key ] ) ? trim( (string) $item[ $key ] ) : '';
		$code = $text( 'code' );
		if ( 1 !== preg_match( '/^\d{8}$/', $code ) ) {
			$msg = sprintf( 'Código de centro inválido en registro %d (debe tener exactamente 8 dígitos): "%s".', $idx, $code );

			throw new RuntimeException( $msg );
		}

		if ( '' === $text( 'name' ) ) {
			$msg = sprintf( 'Denominación vacía para el centro con código "%s".', $code );

			throw new RuntimeException( $msg );
		}

		if ( ! isset( $item['active'] ) || ! is_bool( $item['active'] ) ) {
			$msg = sprintf( 'El campo "active" debe ser booleano para el centro con código "%s".', $code );

			throw new RuntimeException( $msg );
		}

		return array(
			'code'         => $code,
			'name'         => $text( 'name' ),
			'island'       => $text( 'island' ),
			'municipality' => $text( 'municipality' ),
			'type'         => $text( 'type' ),
			'cep'          => $text( 'cep' ),

			'eoep'         => $text( 'eoep' ),
			'active'       => $item['active'],
		);
	}
}
}








namespace Pse\Centre;

use RuntimeException;









if ( ! class_exists( 'Pse\Centre\CentreSettings', false ) ) {
final class CentreSettings {




	public const PAGE = 'pse-centros';




	public const ACTION = 'pse_centres_sync';




	public const CAP = 'pse_manage_app';






	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}






	public static function menu(): void {
		add_options_page( 'Centros educativos', 'Centros educativos', self::CAP, self::PAGE, array( self::class, 'render' ) );
	}







	public static function url( string $outcome = '' ): string {
		$args = array( 'page' => self::PAGE );
		if ( '' !== $outcome ) {
			$args['pse_centres'] = $outcome;
		}
		return add_query_arg( $args, admin_url( 'options-general.php' ) );
	}






	public static function handle(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html( 'No tiene permiso para gestionar el catálogo de centros.' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );

		$url = isset( $_POST['manifest_url'] ) ? (string) wp_unslash( $_POST['manifest_url'] ) : null;
		wp_safe_redirect( self::url( self::submit( $url ) ) );
		exit;
	}










	public static function submit( ?string $url ): string {
		if ( null !== $url ) {
			$url = esc_url_raw( trim( $url ) );
			if ( '' !== $url && ! CentreCatalogueSync::is_valid_https_url( $url ) ) {
				return 'bad_url';
			}
			update_option( CentreCatalogueSync::OPTION_MANIFEST_URL, $url, false );
		}
		if ( '' === CentreCatalogueSync::manifest_url() ) {
			return 'no_source';
		}
		try {
			return CentreCatalogueSync::sync( true )['status'];
		} catch ( RuntimeException $e ) {

			unset( $e );
			return 'error';
		}
	}






	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html( 'No tiene permiso para gestionar el catálogo de centros.' ), '', array( 'response' => 403 ) );
		}
		$status  = CentreCatalogue::status();
		$total   = CentreCatalogue::total();
		$active  = CentreCatalogue::active_count();
		$stored  = (string) get_option( CentreCatalogueSync::OPTION_MANIFEST_URL, '' );
		$source  = CentreCatalogueSync::manifest_url();
		$notices = array(
			'updated'   => array( 'success', sprintf( 'Catálogo actualizado: %d centros, %d activos.', $total, $active ) ),
			'unchanged' => array( 'success', 'El catálogo ya estaba al día.' ),
			'error'     => array( 'error', 'No se ha podido actualizar el catálogo: ' . $status['last_error'] . ' Se sigue usando el que había.' ),
			'bad_url'   => array( 'error', 'La dirección tiene que empezar por https://. No se ha guardado.' ),
			'no_source' => array( 'warning', 'Falta la dirección del listado de centros: sin ella no se descarga nada.' ),
		);

		$outcome = isset( $_GET['pse_centres'] ) ? sanitize_key( wp_unslash( $_GET['pse_centres'] ) ) : '';
		$rows    = array(
			'Centros'                       => sprintf( '%d, de ellos %d activos', $total, $active ),
			'Fecha del catálogo'            => $status['catalogue_updated_at'],
			'Última comprobación'           => $status['last_checked_at'],
			'Última actualización correcta' => $status['last_success_at'],
			'Último error'                  => $status['last_error'],
		);
		?>
		<div class="wrap">
			<h1>Centros educativos</h1>
			<p class="description" style="max-width:46rem">
				El centro donde se imparte una acción se elige de este catálogo, por su código.
				Se copia de su fuente una vez al día; aquí se puede actualizar sin esperar.
				Si una actualización falla, se sigue usando la última copia buena.
			</p>
			<?php if ( isset( $notices[ $outcome ] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notices[ $outcome ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notices[ $outcome ][1] ); ?></p></div>
			<?php endif; ?>

			<table class="widefat striped" style="max-width:46rem">
				<tbody>
				<?php foreach ( $rows as $label => $value ) : ?>
					<tr>
						<th scope="row" style="width:40%"><?php echo esc_html( $label ); ?></th>
						<td><?php echo esc_html( '' !== (string) $value ? (string) $value : '—' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:46rem;margin-top:1.5rem">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>
				<p>
					<label for="pse-manifest-url"><strong>Dirección del <code>manifest.json</code></strong></label><br>
					<input type="url" class="large-text code" id="pse-manifest-url" name="manifest_url" value="<?php echo esc_attr( $stored ); ?>" placeholder="https://…/manifest.json">
				</p>
				<p class="description">
					El catálogo, <code>centros.min.json</code>, se busca junto a él.
					<?php if ( '' !== $source && $source !== $stored ) : ?>
						Este sitio la tiene fijada por configuración, y manda sobre la de aquí: <code><?php echo esc_html( $source ); ?></code>.
					<?php endif; ?>
				</p>
				<?php submit_button( 'Guardar y actualizar ahora', 'secondary' ); ?>
			</form>
		</div>
		<?php
	}
}
}








namespace Pse\Centre;








if ( ! class_exists( 'Pse\Centre\Zones', false ) ) {
final class Zones {

	public const OPTION_CENTRES = 'pse_centre_zones';

	public const OPTION_STAFF = 'pse_zone_staff';






	public static function centres(): array {
		$raw = get_option( self::OPTION_CENTRES, array() );
		return is_array( $raw ) ? $raw : array();
	}






	public static function staff(): array {
		$raw = get_option( self::OPTION_STAFF, array() );
		return is_array( $raw ) ? $raw : array();
	}









	public static function of_centre( string $code ): string {
		$code   = trim( $code );
		$pasted = (string) ( self::centres()[ $code ] ?? '' );
		if ( '' !== $pasted ) {
			return $pasted;
		}
		return self::label( (string) ( CentreCatalogue::find( $code )['eoep'] ?? '' ) );
	}










	public static function label( string $zone ): string {
		$zone = trim( $zone );
		if ( ! preg_match( '/^\d{8}$/', $zone ) ) {
			return $zone;
		}
		return (string) ( CentreCatalogue::find( $zone )['name'] ?? $zone );
	}






	public static function from_catalogue(): array {
		$codes = array();
		foreach ( CentreCatalogue::all() as $centre ) {
			$eoep = (string) ( $centre['eoep'] ?? '' );
			if ( '' !== $eoep ) {
				$codes[ $eoep ] = true;
			}
		}
		return array_map( array( self::class, 'label' ), array_map( 'strval', array_keys( $codes ) ) );
	}






	public static function all(): array {
		$zones = array_unique( array_merge( self::from_catalogue(), array_values( self::centres() ), array_keys( self::staff() ) ) );
		sort( $zones, SORT_NATURAL | SORT_FLAG_CASE );
		return array_values( array_map( 'strval', $zones ) );
	}







	public static function emails( string $zone ): array {
		$emails = self::staff()[ $zone ] ?? array();
		return is_array( $emails ) ? array_values( $emails ) : array();
	}







	public static function of_email( string $email ): array {
		$email = strtolower( trim( $email ) );
		$out   = array();
		if ( '' === $email ) {
			return $out;
		}
		foreach ( self::staff() as $zone => $emails ) {
			if ( in_array( $email, (array) $emails, true ) ) {
				$out[] = (string) $zone;
			}
		}
		return $out;
	}







	private static function pairs( string $text ): array {
		$out = array();
		foreach ( preg_split( '/\R/', $text ) as $line ) {
			$parts = preg_split( '/\s*[;\t,]\s*/', trim( (string) $line ), 2 );
			if ( is_array( $parts ) && 2 === count( $parts ) && '' !== $parts[0] && '' !== trim( $parts[1] ) ) {
				$out[] = array( $parts[0], trim( $parts[1] ) );
			}
		}
		return $out;
	}







	public static function parse_centres( string $text ): array {
		$out = array();
		foreach ( self::pairs( $text ) as $pair ) {
			if ( preg_match( '/^\d{8}$/', $pair[0] ) ) {
				$out[ $pair[0] ] = sanitize_text_field( self::label( $pair[1] ) );
			}
		}
		return $out;
	}







	public static function parse_staff( string $text ): array {
		$out = array();
		foreach ( self::pairs( $text ) as $pair ) {
			$zone  = sanitize_text_field( self::label( $pair[0] ) );
			$email = strtolower( sanitize_email( $pair[1] ) );
			if ( '' !== $zone && is_email( $email ) && ! in_array( $email, $out[ $zone ] ?? array(), true ) ) {
				$out[ $zone ][] = $email;
			}
		}
		return $out;
	}








	public static function save( array $centres, array $staff ): void {
		update_option( self::OPTION_CENTRES, $centres, false );
		update_option( self::OPTION_STAFF, $staff, false );
	}






	public static function centres_text(): string {
		$lines = array();
		foreach ( self::centres() as $code => $zone ) {
			$lines[] = $code . '; ' . $zone;
		}
		return implode( "\n", $lines );
	}






	public static function staff_text(): string {
		$lines = array();
		foreach ( self::staff() as $zone => $emails ) {
			foreach ( (array) $emails as $email ) {
				$lines[] = $zone . '; ' . $email;
			}
		}
		return implode( "\n", $lines );
	}
}
}








namespace Pse\Access;

use Pse\Centre\Zones;
use Pse\PostType\Requests;







if ( ! class_exists( 'Pse\Access\Access', false ) ) {
final class Access {

	public const CAP_REQUEST = 'pse_request_intervention';
	public const CAP_ATTEND  = 'pse_attend_requests';
	public const CAP_OVERSEE = 'pse_oversee_requests';
	public const CAP_ZONES   = 'pse_manage_zones';
	public const CAP_APP     = 'pse_manage_app';









	public static function role_caps(): array {
		$map = array(
			'pse_school_head'  => array( self::CAP_REQUEST ),
			'pse_psychologist' => array( self::CAP_ATTEND ),
			'pse_coordinator'  => array( self::CAP_OVERSEE, self::CAP_ZONES ),
			'administrator'    => array( self::CAP_REQUEST, self::CAP_ATTEND, self::CAP_OVERSEE, self::CAP_ZONES, self::CAP_APP ),
		);






		return (array) apply_filters( 'pse_role_caps', $map );
	}






	public static function grant_caps_to_roles(): void {
		$known = array( self::CAP_REQUEST, self::CAP_ATTEND, self::CAP_OVERSEE, self::CAP_ZONES, self::CAP_APP );
		foreach ( self::role_caps() as $slug => $caps ) {
			$role = get_role( (string) $slug );
			if ( ! $role ) {
				continue;
			}
			foreach ( array_intersect( (array) $caps, $known ) as $cap ) {
				if ( ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}







	public static function centre_code( int $user_id = 0 ): string {
		$user_id = $user_id ? $user_id : get_current_user_id();






		$key  = (string) apply_filters( 'pse_centre_code_meta_key', 'pse_centre_code' );
		$code = trim( (string) get_user_meta( $user_id, $key, true ) );
		return preg_match( '/^\d{8}$/', $code ) ? $code : '';
	}







	public static function zones( int $user_id = 0 ): array {
		$user = get_userdata( $user_id ? $user_id : get_current_user_id() );
		return $user ? Zones::of_email( (string) $user->user_email ) : array();
	}








	public static function can_view( \WP_Post $post, int $user_id = 0 ): bool {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( Requests::TYPE !== $post->post_type || ! $user_id ) {
			return false;
		}
		if ( user_can( $user_id, self::CAP_OVERSEE ) ) {
			return true;
		}
		if ( self::attends( $post, $user_id ) ) {
			return true;
		}
		$centre = self::centre_code( $user_id );
		return user_can( $user_id, self::CAP_REQUEST ) && '' !== $centre && (string) get_post_meta( $post->ID, Requests::CENTRE_CODE, true ) === $centre;
	}










	public static function can_set_status( \WP_Post $post, int $user_id = 0 ): bool {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( Requests::TYPE !== $post->post_type || ! $user_id ) {
			return false;
		}
		return user_can( $user_id, 'manage_options' ) || self::attends( $post, $user_id );
	}








	private static function attends( \WP_Post $post, int $user_id ): bool {
		$zone = (string) get_post_meta( $post->ID, Requests::ZONE, true );
		return user_can( $user_id, self::CAP_ATTEND ) && '' !== $zone && in_array( $zone, self::zones( $user_id ), true );
	}
}
}








namespace Pse\PostType;

use Pse\Access\Access;
use Pse\Centre\CentreCatalogue;
use Pse\Centre\Zones;
use Pse\Domain\Vocabulary;







if ( ! class_exists( 'Pse\PostType\Requests', false ) ) {
final class Requests {

	public const TYPE = 'pse_request';

	public const NUMBER       = 'pse_number';
	public const CENTRE_CODE  = 'pse_centre_code';
	public const CENTRE_NAME  = 'pse_centre_name';
	public const MUNICIPALITY = 'pse_municipality';
	public const ISLAND       = 'pse_island';
	public const ZONE         = 'pse_zone';
	public const SUBJECT      = 'pse_subject';
	public const STATUS       = 'pse_status';

	public const OPTION_SEQUENCE = 'pse_sequence';

	public const PER_PAGE = 25;






	public static function keys(): array {
		return array( self::NUMBER, self::CENTRE_CODE, self::CENTRE_NAME, self::MUNICIPALITY, self::ISLAND, self::ZONE, self::SUBJECT, self::STATUS );
	}









	public static function register(): void {
		$cap = Access::CAP_APP;
		register_post_type(
			self::TYPE,
			array(
				'labels'       => array(
					'name'          => 'Solicitudes de intervención',
					'singular_name' => 'Solicitud de intervención',
					'menu_name'     => 'Solicitudes',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => false,
				'menu_icon'    => 'dashicons-heart',
				'supports'     => array( 'title' ),
				'map_meta_cap' => false,
				'capabilities' => array(
					'edit_post'              => $cap,
					'read_post'              => $cap,
					'delete_post'            => $cap,
					'edit_posts'             => $cap,
					'edit_others_posts'      => $cap,
					'publish_posts'          => $cap,
					'read_private_posts'     => $cap,
					'delete_posts'           => $cap,
					'delete_private_posts'   => $cap,
					'delete_published_posts' => $cap,
					'delete_others_posts'    => $cap,
					'edit_private_posts'     => $cap,
					'edit_published_posts'   => $cap,
					'create_posts'           => $cap,
				),
			)
		);
		foreach ( self::keys() as $key ) {
			register_post_meta(
				self::TYPE,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static fn() => current_user_can( Access::CAP_APP ),
				)
			);
		}
	}







	public static function next_number( int $year ): string {
		$all          = get_option( self::OPTION_SEQUENCE, array() );
		$all          = is_array( $all ) ? $all : array();
		$next         = (int) ( $all[ $year ] ?? 0 ) + 1;
		$all[ $year ] = $next;
		update_option( self::OPTION_SEQUENCE, $all, false );
		return sprintf( '%d-%04d', $year, $next );
	}








	public static function create( int $user_id, string $subject ) {
		if ( ! user_can( $user_id, Access::CAP_REQUEST ) ) {
			return new \WP_Error( 'pse_denied', 'No tiene permiso para solicitar una intervención.' );
		}
		$code = Access::centre_code( $user_id );
		if ( '' === $code ) {
			return new \WP_Error( 'pse_no_centre', 'Su cuenta no tiene un centro asignado.' );
		}
		if ( ! isset( Vocabulary::subjects()[ $subject ] ) ) {
			return new \WP_Error( 'pse_subject', 'Elija un asunto de la lista.' );
		}
		$centre = CentreCatalogue::find( $code );
		$number = self::next_number( (int) current_time( 'Y' ) );
		$id     = wp_insert_post(
			array(
				'post_type'   => self::TYPE,
				'post_status' => 'publish',
				'post_title'  => $number,
				'post_author' => $user_id,
				'meta_input'  => array(
					self::NUMBER       => $number,
					self::CENTRE_CODE  => $code,
					self::CENTRE_NAME  => (string) ( $centre['name'] ?? '' ),
					self::MUNICIPALITY => (string) ( $centre['municipality'] ?? '' ),
					self::ISLAND       => (string) ( $centre['island'] ?? '' ),
					self::ZONE         => Zones::of_centre( $code ),
					self::SUBJECT      => $subject,
					self::STATUS       => Vocabulary::STATUS_PENDING,
				),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}






		do_action( 'pse_request_created', (int) $id );
		return (int) $id;
	}









	public static function set_status( int $id, string $status, int $user_id = 0 ): bool {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || ! isset( Vocabulary::states()[ $status ] ) || ! Access::can_set_status( $post, $user_id ) ) {
			return false;
		}
		update_post_meta( $id, self::STATUS, $status );
		return true;
	}







	public static function row( \WP_Post $post ): array {
		$status = (string) get_post_meta( $post->ID, self::STATUS, true );
		return array(
			'id'           => (int) $post->ID,
			'number'       => (string) get_post_meta( $post->ID, self::NUMBER, true ),
			'date'         => (string) mysql2date( 'Y-m-d', $post->post_date ),
			'centre_code'  => (string) get_post_meta( $post->ID, self::CENTRE_CODE, true ),
			'centre_name'  => (string) get_post_meta( $post->ID, self::CENTRE_NAME, true ),
			'municipality' => (string) get_post_meta( $post->ID, self::MUNICIPALITY, true ),
			'island'       => (string) get_post_meta( $post->ID, self::ISLAND, true ),
			'zone'         => (string) get_post_meta( $post->ID, self::ZONE, true ),
			'subject'      => (string) get_post_meta( $post->ID, self::SUBJECT, true ),
			'status'       => isset( Vocabulary::states()[ $status ] ) ? $status : Vocabulary::STATUS_PENDING,
		);
	}











	public static function query( array $args = array() ): \WP_Query {
		$meta = array();
		if ( isset( $args['centre'] ) ) {
			$meta[] = array(
				'key'   => self::CENTRE_CODE,
				'value' => '' !== $args['centre'] ? $args['centre'] : '-',
			);
		}
		if ( isset( $args['zones'] ) ) {
			$meta[] = array(
				'key'     => self::ZONE,
				'value'   => $args['zones'] ? array_values( $args['zones'] ) : array( '-' ),
				'compare' => 'IN',
			);
		}
		$filters = array(
			'status'       => self::STATUS,
			'subject'      => self::SUBJECT,
			'zone'         => self::ZONE,
			'municipality' => self::MUNICIPALITY,
		);
		foreach ( $filters as $arg => $key ) {
			if ( ! empty( $args[ $arg ] ) ) {
				$meta[] = array(
					'key'   => $key,
					'value' => (string) $args[ $arg ],
				);
			}
		}
		$search = trim( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$meta[] = array(
				'relation' => 'OR',
				array(
					'key'     => self::CENTRE_NAME,
					'value'   => $search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => self::CENTRE_CODE,
					'value'   => $search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => self::NUMBER,
					'value'   => $search,
					'compare' => 'LIKE',
				),
			);
		}
		return new \WP_Query(
			array(
				'post_type'              => self::TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => (int) ( $args['per_page'] ?? self::PER_PAGE ),
				'paged'                  => max( 1, (int) ( $args['paged'] ?? 1 ) ),
				'orderby'                => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'meta_query'             => $meta, 
			)
		);
	}







	public static function rows( array $args = array() ): array {
		$args['per_page'] = -1;
		$args['paged']    = 1;
		return array_map( array( self::class, 'row' ), self::query( $args )->posts );
	}
}
}








namespace Pse\Notifications;

use Pse\Centre\Zones;
use Pse\PostType\Requests;





if ( ! class_exists( 'Pse\Notifications\Mailer', false ) ) {
final class Mailer {






	public static function register(): void {
		add_action( 'pse_request_created', array( self::class, 'notify' ) );
	}







	public static function message( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || Requests::TYPE !== $post->post_type ) {
			return null;
		}
		$row = Requests::row( $post );
		$to  = Zones::emails( $row['zone'] );
		if ( ! $to ) {
			return null;
		}
		$centre = trim( $row['centre_name'] . ' (' . $row['centre_code'] . ')' );
		$body   = "Ha entrado una nueva solicitud de intervención de un centro de su zona.\n\n"
			. 'N.º de solicitud: ' . $row['number'] . "\n"
			. 'Centro: ' . $centre . "\n"
			. 'Fecha: ' . mysql2date( 'd/m/Y', $post->post_date ) . "\n\n"
			. "Por seguridad, este correo no incluye el asunto. Entre en el aplicativo para consultarlo y póngase en contacto con el centro:\n"
			. home_url( '/' ) . "\n\n"
			. 'Mensaje automático. No responda a este correo.';
		return array(
			'to'      => $to,
			'subject' => 'Nueva solicitud de intervención n.º ' . $row['number'],
			'body'    => $body,
		);
	}







	public static function notify( $id ): bool {
		$message = self::message( (int) $id );
		return null !== $message && (bool) wp_mail( $message['to'], $message['subject'], $message['body'] );
	}
}
}








namespace Pse\PublicFront;






if ( ! class_exists( 'Pse\PublicFront\Assets', false ) ) {
final class Assets {






	private static $inline = array();







	public static function set_inline( array $assets ): void {
		self::$inline = $assets;
	}







	public static function contents( string $rel ): string {
		if ( isset( self::$inline[ $rel ] ) ) {
			return self::$inline[ $rel ];
		}
		$path = defined( 'PSE_SRC_DIR' ) ? dirname( PSE_SRC_DIR, 2 ) . '/assets/' . $rel : '';

		return '' !== $path && is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}






	public static function css(): string {
		return self::contents( 'css/pse-app.css' );
	}
}
}








namespace Pse\PublicFront;

use Pse\Access\Access;
use Pse\Centre\Zones;
use Pse\PostType\Requests;






if ( ! class_exists( 'Pse\PublicFront\Actions', false ) ) {
final class Actions {

	public const FIELD = 'pse_action';

	public const NONCE = '_pse_nonce';







	public static function fields( string $action ): string {
		return wp_nonce_field( 'pse_' . $action, self::NONCE, true, false ) . '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="' . esc_attr( $action ) . '">';
	}







	private static function posted( string $name ): string {

		return isset( $_POST[ $name ] ) && is_scalar( $_POST[ $name ] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST[ $name ] ) ) : '';
	}






	public static function handle(): string {
		$action   = sanitize_key( self::posted( self::FIELD ) );
		$handlers = array(
			'create' => 'create',
			'status' => 'status',
			'zones'  => 'zones',
		);
		$nonce    = self::posted( self::NONCE );
		if ( ! isset( $handlers[ $action ] ) || ! wp_verify_nonce( $nonce, 'pse_' . $action ) ) {
			return Screen::url( array( Screen::ARG_NOTICE => 'denied' ) );
		}
		return (string) call_user_func( array( self::class, $handlers[ $action ] ) );
	}






	private static function create(): string {
		$id = Requests::create( get_current_user_id(), sanitize_key( self::posted( 'pse_subject' ) ) );
		if ( is_wp_error( $id ) ) {
			$notices = array(
				'pse_subject'   => 'subject',
				'pse_no_centre' => 'centre',
			);
			return Screen::url(
				array(
					Screen::ARG_TAB    => 'new',
					Screen::ARG_NOTICE => $notices[ $id->get_error_code() ] ?? 'denied',
				)
			);
		}
		return Screen::url(
			array(
				Screen::ARG_TAB => 'centre',
				'pse_created'   => $id,
			)
		);
	}






	private static function status(): string {
		$done = Requests::set_status( absint( self::posted( 'pse_id' ) ), sanitize_key( self::posted( 'pse_status' ) ) );
		return Screen::url(
			array(
				Screen::ARG_TAB    => 'zone',
				'pse_f_status'     => sanitize_key( self::posted( 'pse_back' ) ),
				Screen::ARG_NOTICE => $done ? 'saved' : 'denied',
			)
		);
	}






	private static function zones(): string {
		if ( ! current_user_can( Access::CAP_ZONES ) ) {
			return Screen::url( array( Screen::ARG_NOTICE => 'denied' ) );
		}
		Zones::save( Zones::parse_centres( self::posted( 'pse_centres' ) ), Zones::parse_staff( self::posted( 'pse_staff' ) ) );
		return Screen::url(
			array(
				Screen::ARG_TAB    => 'zones',
				Screen::ARG_NOTICE => 'saved',
			)
		);
	}
}
}








namespace Pse\PublicFront;

use Pse\Access\Access;
use Pse\Centre\CentreCatalogue;
use Pse\Centre\Zones;
use Pse\Domain\Stats;
use Pse\Domain\Vocabulary;
use Pse\PostType\Requests;





if ( ! class_exists( 'Pse\PublicFront\Views', false ) ) {
final class Views {







	public static function screen( string $tab ): array {
		$screens = array(
			'new'    => 'request_form',
			'centre' => 'centre_list',
			'zone'   => 'zone_list',
			'all'    => 'all_list',
			'stats'  => 'stats',
			'zones'  => 'zones',
		);
		if ( ! isset( $screens[ $tab ], Screen::tabs()[ $tab ] ) ) {
			return array(
				'tab'   => '',
				'title' => 'Sin acceso',
				'body'  => '<div class="alert alert-warning">Su cuenta no tiene ningún perfil en este aplicativo, o no tiene un centro asignado. Si cree que es un error, contacte con quien lo administra.</div>',
			);
		}
		return (array) call_user_func( array( self::class, $screens[ $tab ] ) );
	}








	private static function centre_label( string $code, string $name = '' ): string {
		if ( '' === $name ) {
			$name = (string) ( CentreCatalogue::find( $code )['name'] ?? '' );
		}
		return trim( $name . ' · ' . $code, ' ·' );
	}







	private static function badge( string $status ): string {
		return '<span class="pse-state pse-state-' . esc_attr( $status ) . '">' . esc_html( Vocabulary::state_label( $status ) ) . '</span>';
	}






	private static function request_form(): array {
		$code = Access::centre_code();
		$body = '<div class="alert alert-warning pse-warn"><strong>No escriba datos del alumnado.</strong> Aquí solo se indica el asunto. La información necesaria se la pedirá directamente el profesional que atienda la solicitud.</div>';

		$body .= '<form method="post" action="' . esc_url( home_url( '/' ) ) . '">' . Actions::fields( 'create' );
		$body .= '<div class="pse-card"><h2>Centro que solicita</h2><div class="row g-3">';
		$body .= '<div class="col-md-8"><label class="form-label" for="pse-centre">Centro</label><input class="form-control" id="pse-centre" value="' . esc_attr( self::centre_label( $code ) ) . '" readonly></div>';
		$body .= '<div class="col-md-4"><label class="form-label" for="pse-date">Fecha de la solicitud</label><input class="form-control" id="pse-date" value="' . esc_attr( wp_date( 'd/m/Y' ) ) . '" readonly></div>';
		$body .= '</div><p class="form-text mb-0">El centro y la fecha se rellenan solos a partir de la cuenta con la que ha entrado.</p></div>';

		$body .= '<div class="pse-card"><h2>Motivo de la solicitud</h2><label class="form-label" for="pse-subject">Asunto <span class="text-danger" aria-hidden="true">*</span></label>';
		$body .= '<select class="form-select" id="pse-subject" name="pse_subject" required><option value="">Elija un asunto de la lista…</option>';
		foreach ( Vocabulary::subjects() as $slug => $label ) {
			$body .= '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
		}
		$body .= '</select><p class="form-text mb-0">Lista cerrada: no hay ningún campo de texto libre.</p></div>';
		$body .= '<button class="btn btn-primary pse-btn" type="submit">Enviar solicitud</button></form>';

		return array(
			'tab'      => 'new',
			'title'    => Screen::TITLES['new'],
			'subtitle' => 'Indique solo el asunto. El profesional de psicología educativa de su zona se pondrá en contacto con el centro.',
			'body'     => $body,
		);
	}







	private static function filters( array $allowed ): array {
		$out = array();
		foreach ( $allowed as $name ) {
			$value = Screen::arg( 'pse_f_' . $name );
			if ( '' !== $value ) {
				$out[ $name ] = $value;
			}
		}
		return $out;
	}









	private static function chips( string $tab, array $scope, string $current ): string {
		unset( $scope['status'], $scope['paged'] );
		$keep = array( Screen::ARG_TAB => $tab );
		foreach ( array( 'subject', 'zone', 'municipality', 'search' ) as $name ) {
			$keep[ 'pse_f_' . $name ] = (string) ( $scope[ $name ] ?? '' );
		}
		$states = array( '' => 'Todas' );
		foreach ( array_keys( Vocabulary::states() ) as $state ) {
			$states[ $state ] = Vocabulary::state_plural( $state );
		}
		$out = '<div class="pse-chips" role="group" aria-label="Filtrar por estado">';
		foreach ( $states as $state => $label ) {
			$count = Requests::query( $scope + array_filter( array( 'status' => $state ) ) + array( 'per_page' => 1 ) )->found_posts;
			$on    = $state === $current;
			$out  .= '<a class="pse-chip' . ( $on ? ' is-on' : '' ) . '"' . ( $on ? ' aria-current="true"' : '' ) . ' href="' . esc_url( Screen::url( $keep + array( 'pse_f_status' => $state ) ) ) . '">' . esc_html( $label ) . ' <b>' . esc_html( number_format_i18n( $count ) ) . '</b></a>';
		}
		return $out . '</div>';
	}









	private static function table( array $posts, array $columns, string $back = '' ): string {
		$heads = array(
			'number'       => 'N.º',
			'date'         => 'Fecha',
			'centre'       => 'Centro',
			'municipality' => 'Municipio',
			'zone'         => 'Zona',
			'subject'      => 'Asunto',
			'status'       => 'Estado',
			'change'       => 'Cambiar estado',
		);
		if ( ! $posts ) {
			return '<p class="pse-empty">No hay solicitudes que mostrar.</p>';
		}
		$out = '<div class="table-responsive"><table class="table pse-table"><thead><tr>';
		foreach ( $columns as $column ) {
			$out .= '<th scope="col">' . esc_html( $heads[ $column ] ) . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		foreach ( $posts as $post ) {
			$row   = Requests::row( $post );
			$cells = array(
				'number'       => '<strong>' . esc_html( $row['number'] ) . '</strong>',
				'date'         => esc_html( mysql2date( 'd/m/Y', $post->post_date ) ),
				'centre'       => esc_html( $row['centre_name'] ) . ' <small class="text-secondary">' . esc_html( $row['centre_code'] ) . '</small>',
				'municipality' => esc_html( $row['municipality'] ),
				'zone'         => esc_html( $row['zone'] ),
				'subject'      => esc_html( Vocabulary::subject_label( $row['subject'] ) ),
				'status'       => self::badge( $row['status'] ),
				'change'       => in_array( 'change', $columns, true ) ? self::status_form( $post, $row, $back ) : '',
			);
			$out  .= '<tr>';
			foreach ( $columns as $column ) {
				$out .= '<td>' . $cells[ $column ] . '</td>';
			}
			$out .= '</tr>';
		}
		return $out . '</tbody></table></div>';
	}









	private static function status_form( \WP_Post $post, array $row, string $back ): string {
		if ( ! Access::can_set_status( $post ) ) {
			return '';
		}
		$id   = 'pse-status-' . $row['id'];
		$out  = '<form class="pse-status-form" method="post" action="' . esc_url( home_url( '/' ) ) . '">' . Actions::fields( 'status' );
		$out .= '<input type="hidden" name="pse_id" value="' . esc_attr( (string) $row['id'] ) . '"><input type="hidden" name="pse_back" value="' . esc_attr( $back ) . '">';
		$out .= '<label class="visually-hidden" for="' . esc_attr( $id ) . '">' . esc_html( 'Estado de la solicitud ' . $row['number'] ) . '</label>';
		$out .= '<select class="form-select form-select-sm" id="' . esc_attr( $id ) . '" name="pse_status" data-pse-autosubmit>';
		foreach ( Vocabulary::states() as $state => $label ) {
			$out .= '<option value="' . esc_attr( $state ) . '"' . selected( $state, $row['status'], false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $out . '</select><button class="btn btn-sm btn-outline-primary" type="submit">Guardar</button></form>';
	}








	private static function pagination( \WP_Query $query, array $args ): string {
		$pages = (int) $query->max_num_pages;
		$page  = max( 1, (int) $query->get( 'paged' ) );
		if ( $pages < 2 ) {
			return '';
		}
		$out = '<nav class="pse-pages" aria-label="Páginas"><span>' . esc_html( 'Página ' . $page . ' de ' . $pages ) . '</span>';
		if ( $page > 1 ) {
			$out .= '<a class="btn btn-sm btn-outline-secondary" href="' . esc_url( Screen::url( $args + array( 'pse_p' => $page - 1 ) ) ) . '">Anterior</a>';
		}
		if ( $page < $pages ) {
			$out .= '<a class="btn btn-sm btn-outline-secondary" href="' . esc_url( Screen::url( $args + array( 'pse_p' => $page + 1 ) ) ) . '">Siguiente</a>';
		}
		return $out . '</nav>';
	}






	private static function centre_list(): array {
		$scope = array( 'centre' => Access::centre_code() );
		$query = Requests::query( $scope + array( 'paged' => absint( Screen::arg( 'pse_p' ) ) ) );
		$body  = '';

		$created = get_post( absint( Screen::arg( 'pse_created' ) ) );
		if ( $created instanceof \WP_Post && Access::can_view( $created ) ) {
			$row   = Requests::row( $created );
			$told  = Zones::emails( $row['zone'] ) ? 'Se ha avisado por correo al profesional de su zona, que se pondrá en contacto con el centro.' : 'Queda registrada y a la vista de la coordinación.';
			$body .= '<div class="alert alert-success" role="status"><strong>' . esc_html( 'Solicitud n.º ' . $row['number'] . ' registrada.' ) . '</strong> ' . esc_html( $told ) . '</div>';
		}
		$body .= self::table( $query->posts, array( 'number', 'date', 'subject', 'status' ) );
		$body .= self::pagination( $query, array( Screen::ARG_TAB => 'centre' ) );
		$body .= '<p class="pse-note">Solo se muestran las solicitudes de su centro.</p>';
		return array(
			'tab'      => 'centre',
			'title'    => Screen::TITLES['centre'],
			'subtitle' => 'Un centro puede hacer tantas solicitudes como necesite. Aquí ve en qué estado está cada una.',
			'body'     => $body,
		);
	}






	private static function zone_list(): array {
		$zones   = Access::zones();
		$filters = self::filters( array( 'status' ) );
		$scope   = array( 'zones' => $zones ) + $filters;
		$query   = Requests::query( $scope + array( 'paged' => absint( Screen::arg( 'pse_p' ) ) ) );
		$status  = (string) ( $filters['status'] ?? '' );

		$body = '';
		if ( ! $zones ) {
			$body .= '<div class="alert alert-warning">Su correo no figura en ninguna zona todavía. La coordinación asigna las zonas en «Zonas».</div>';
		}
		$body .= self::chips( 'zone', $scope, $status );
		$body .= self::table( $query->posts, array( 'number', 'date', 'centre', 'municipality', 'subject', 'status', 'change' ), $status );
		$body .= self::pagination(
			$query,
			array(
				Screen::ARG_TAB => 'zone',
				'pse_f_status'  => $status,
			)
		);
		$body .= '<p class="pse-note">Solo ve las solicitudes de los centros de su zona. El resto de la información del caso se la solicita usted directamente al centro, fuera de este aplicativo.</p>';
		return array(
			'tab'      => 'zone',
			'title'    => Screen::TITLES['zone'],
			'subtitle' => $zones ? implode( ', ', $zones ) . '. Lo único que se cambia aquí es el estado.' : 'Lo único que se cambia aquí es el estado.',
			'body'     => $body,
		);
	}










	private static function select( string $name, string $all, array $options, string $current ): string {
		$out = '<select class="form-select" name="' . esc_attr( 'pse_f_' . $name ) . '" aria-label="' . esc_attr( $all ) . '"><option value="">' . esc_html( $all ) . '</option>';
		foreach ( $options as $value => $label ) {
			$out .= '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $value, $current, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $out . '</select>';
	}






	private static function all_list(): array {
		$filters = self::filters( array( 'status', 'subject', 'zone', 'municipality', 'search' ) );
		$query   = Requests::query( $filters + array( 'paged' => absint( Screen::arg( 'pse_p' ) ) ) );
		$args    = array( Screen::ARG_TAB => 'all' );
		foreach ( $filters as $name => $value ) {
			$args[ 'pse_f_' . $name ] = $value;
		}

		$municipalities = array_keys( Stats::of( Requests::rows() )['municipality'] );
		sort( $municipalities, SORT_LOCALE_STRING );
		$zones = Zones::all();

		$body  = '<form class="pse-filters" method="get" action="' . esc_url( home_url( '/' ) ) . '"><input type="hidden" name="' . esc_attr( Screen::ARG_TAB ) . '" value="all">';
		$body .= '<input type="hidden" name="pse_f_status" value="' . esc_attr( (string) ( $filters['status'] ?? '' ) ) . '">';
		$body .= '<input class="form-control" type="search" name="pse_f_search" value="' . esc_attr( (string) ( $filters['search'] ?? '' ) ) . '" placeholder="Centro, código o n.º de solicitud" aria-label="Buscar por centro, código o número de solicitud">';
		$body .= self::select( 'municipality', 'Todos los municipios', array_combine( $municipalities, $municipalities ), (string) ( $filters['municipality'] ?? '' ) );
		$body .= self::select( 'zone', 'Todas las zonas', array_combine( $zones, $zones ), (string) ( $filters['zone'] ?? '' ) );
		$body .= self::select( 'subject', 'Todos los asuntos', Vocabulary::subjects(), (string) ( $filters['subject'] ?? '' ) );
		$body .= '<button class="btn btn-outline-primary" type="submit">Filtrar</button></form>';

		$body .= self::chips( 'all', $filters, (string) ( $filters['status'] ?? '' ) );
		$body .= self::table( $query->posts, array( 'number', 'date', 'centre', 'municipality', 'zone', 'subject', 'status' ) );
		$body .= '<div class="pse-list-foot">' . self::pagination( $query, $args );
		$body .= '<a class="btn btn-primary pse-btn ms-auto" href="' . esc_url( Screen::url( $args + array( Screen::ARG_CSV => '1' ) ) ) . '">Exportar resultados como CSV</a></div>';
		$body .= '<p class="pse-note">La exportación incluye todas las páginas del listado filtrado.</p>';
		return array(
			'tab'      => 'all',
			'title'    => 'Solicitudes de intervención',
			'subtitle' => 'Todas las solicitudes de los centros, con su estado. Sin datos del alumnado.',
			'body'     => $body,
		);
	}







	public static function csv( array $rows ): string {
		$lines = array( array( 'Número', 'Fecha', 'Código de centro', 'Centro', 'Municipio', 'Isla', 'Zona', 'Asunto', 'Estado' ) );
		foreach ( $rows as $row ) {
			$lines[] = array( $row['number'], $row['date'], $row['centre_code'], $row['centre_name'], $row['municipality'], $row['island'], $row['zone'], Vocabulary::subject_label( (string) $row['subject'] ), Vocabulary::state_label( (string) $row['status'] ) );
		}
		$out = '';
		foreach ( $lines as $line ) {
			$cells = array();
			foreach ( $line as $cell ) {

				$cell    = preg_replace( '/^([=+\-@])/', "'$1", (string) $cell );
				$cells[] = '"' . str_replace( '"', '""', (string) $cell ) . '"';
			}
			$out .= implode( ';', $cells ) . "\r\n";
		}
		return $out;
	}






	public static function send_csv(): void {
		if ( ! current_user_can( Access::CAP_OVERSEE ) ) {
			return;
		}
		$csv = self::csv( Requests::rows( self::filters( array( 'status', 'subject', 'zone', 'municipality', 'search' ) ) ) );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="solicitudes-' . wp_date( 'Y-m-d' ) . '.csv"' );


		echo "\xEF\xBB\xBF" . $csv;
	}








	private static function bars( array $counts, array $labels = array() ): string {
		if ( ! $counts ) {
			return '<p class="pse-empty">Sin datos todavía.</p>';
		}
		$max = max( 1, max( $counts ) );
		$out = '<dl class="pse-bars">';
		foreach ( $counts as $value => $count ) {
			$out .= '<dt>' . esc_html( $labels[ $value ] ?? (string) $value ) . '</dt><dd><span style="width:' . esc_attr( (string) round( 100 * $count / $max ) ) . '%"></span><b>' . esc_html( number_format_i18n( $count ) ) . '</b></dd>';
		}
		return $out . '</dl>';
	}






	private static function stats(): array {
		$stats = Stats::of( Requests::rows() );

		$body = '<div class="pse-figures"><div class="pse-figure"><b>' . esc_html( number_format_i18n( $stats['total'] ) ) . '</b><span>Solicitudes</span></div>';
		foreach ( Vocabulary::states() as $state => $label ) {
			$body .= '<div class="pse-figure"><b>' . esc_html( number_format_i18n( $stats['status'][ $state ] ?? 0 ) ) . '</b><span>' . esc_html( Vocabulary::state_plural( $state ) ) . '</span></div>';
		}
		$body .= '</div><div class="row g-3">';

		$months = array();
		foreach ( $stats['month'] as $month => $count ) {
			$months[ $month ] = ucfirst( (string) wp_date( 'F Y', (int) strtotime( $month . '-01' ) ) );
		}
		$cards = array(
			'Por mes'       => self::bars( $stats['month'], $months ),
			'Por asunto'    => self::bars( $stats['subject'], Vocabulary::subjects() ),
			'Por zona'      => self::bars( $stats['zone'] ),
			'Por municipio' => self::bars( $stats['municipality'] ),
			'Por isla'      => self::bars( $stats['island'] ),
		);
		foreach ( $cards as $title => $card ) {
			$body .= '<div class="col-lg-6"><div class="pse-card h-100"><h2>' . esc_html( $title ) . '</h2>' . $card . '</div></div>';
		}
		$body .= '</div><p class="pse-note">Las cifras cuentan solicitudes, no alumnado: el aplicativo no guarda ningún dato que identifique a una persona atendida.</p>';
		return array(
			'tab'      => 'stats',
			'title'    => Screen::TITLES['stats'],
			'subtitle' => 'Número de solicitudes por mes, asunto, zona, municipio e isla.',
			'body'     => $body,
		);
	}






	private static function zones(): array {
		$body  = '<form method="post" action="' . esc_url( home_url( '/' ) ) . '">' . Actions::fields( 'zones' ) . '<div class="row g-3">';
		$body .= '<div class="col-lg-6"><div class="pse-card h-100"><h2><label for="pse-centres">Centros con otra zona</label></h2><p class="form-text">La zona de cada centro sale del catálogo de centros. Aquí solo van los que no la tienen o la tienen distinta. Una línea por centro: código del centro, punto y coma y la zona.</p>';
		$body .= '<textarea class="form-control font-monospace" id="pse-centres" name="pse_centres" rows="14" placeholder="90000001; Zona 1">' . esc_textarea( Zones::centres_text() ) . '</textarea></div></div>';
		$body .= '<div class="col-lg-6"><div class="pse-card h-100"><h2><label for="pse-staff">Profesionales de cada zona</label></h2><p class="form-text">Una línea por profesional: la zona, punto y coma y su correo. Es el correo al que llega el aviso y el de la cuenta con la que entra.</p>';
		$body .= '<textarea class="form-control font-monospace" id="pse-staff" name="pse_staff" rows="14" placeholder="Zona 1; profesional@example.org">' . esc_textarea( Zones::staff_text() ) . '</textarea></div></div>';
		$body .= '</div><button class="btn btn-primary pse-btn mt-3" type="submit">Guardar las zonas</button></form>';
		$known = Zones::from_catalogue();
		sort( $known, SORT_NATURAL | SORT_FLAG_CASE );
		$body .= '<p class="pse-note">La zona se puede escribir por su nombre o por el código de su equipo. Las líneas sin un código de ocho cifras o sin un correo válido se descartan al guardar. Cambiar el reparto no mueve las solicitudes ya registradas.</p>';
		if ( $known ) {
			$body .= '<details class="pse-note"><summary>' . esc_html( 'Zonas del catálogo de centros (' . count( $known ) . ')' ) . '</summary><p>' . esc_html( implode( ' · ', $known ) ) . '</p></details>';
		}
		return array(
			'tab'      => 'zones',
			'title'    => Screen::TITLES['zones'],
			'subtitle' => 'Quién atiende cada zona, y los centros cuya zona no dice el catálogo.',
			'body'     => $body,
		);
	}
}
}








namespace Pse\PublicFront;

use Pse\Access\Access;









if ( ! class_exists( 'Pse\PublicFront\Screen', false ) ) {
final class Screen {

	public const ARG_TAB    = 'pse_tab';
	public const ARG_NOTICE = 'pse_notice';
	public const ARG_CSV    = 'pse_csv';




	public const APP_NAME = 'Solicitudes de intervención';




	public const TITLES = array(
		'new'    => 'Solicitar intervención',
		'centre' => 'Solicitudes de mi centro',
		'zone'   => 'Solicitudes de mi zona',
		'all'    => 'Solicitudes',
		'stats'  => 'Estadísticas',
		'zones'  => 'Zonas',
	);




	public const BOOTSTRAP = array(
		'css'     => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
		'css_sri' => 'sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB',
		'js'      => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js',
		'js_sri'  => 'sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI',
	);




	public const BOOTSTRAP_ICONS = array(
		'css'     => 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.2/font/bootstrap-icons.min.css',
		'css_sri' => 'sha384-nWN3KqaaVcbEsCjLSV+2jMjh62Iny/+mN3srM8TZeM8N6aj7eDl5wwYggjAJ4/sJ',
	);






	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'route' ), 1 );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 2 );
		add_filter( 'show_admin_bar', array( self::class, 'show_admin_bar' ), 100 );
	}













	public static function show_admin_bar( $show = false ): bool {
		unset( $show );
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$key = 'wpfront_ure_user_switching_stack_' . COOKIEHASH;
		if ( ! class_exists( '\\WPFront\\URE\\WPFront_User_Role_Editor_Utils' ) || ! isset( $_COOKIE[ $key ] ) || ! is_string( $_COOKIE[ $key ] ) ) {
			return false;
		}

		$stack = explode( '-', (string) \WPFront\URE\WPFront_User_Role_Editor_Utils::decrypt( sanitize_text_field( wp_unslash( $_COOKIE[ $key ] ) ) ), 4 );
		if ( count( $stack ) < 3 || COOKIEHASH !== $stack[0] || ! ctype_digit( $stack[1] ) ) {
			return false;
		}
		$age   = time() - (int) $stack[1];
		$users = explode( ',', $stack[2] );
		return $age >= 0 && $age <= 12 * HOUR_IN_SECONDS && ctype_digit( $users[0] ) && user_can( (int) $users[0], 'manage_options' );
	}








	public static function login_redirect( $redirect_to, $requested ) {
		if ( '' === (string) $requested || admin_url() === (string) $requested ) {
			return home_url( '/' );
		}
		return (string) $redirect_to;
	}






	public static function tabs(): array {
		$tabs = array();
		$caps = array(
			'new'    => Access::CAP_REQUEST,
			'centre' => Access::CAP_REQUEST,
			'zone'   => Access::CAP_ATTEND,
			'all'    => Access::CAP_OVERSEE,
			'stats'  => Access::CAP_OVERSEE,
			'zones'  => Access::CAP_ZONES,
		);
		foreach ( $caps as $tab => $cap ) {

			$own = Access::CAP_REQUEST !== $cap || '' !== Access::centre_code();
			if ( $own && current_user_can( $cap ) ) {
				$tabs[ $tab ] = self::TITLES[ $tab ];
			}
		}
		return $tabs;
	}







	public static function url( array $args = array() ): string {
		return add_query_arg( array_filter( $args, static fn( $v ) => '' !== $v && null !== $v ), home_url( '/' ) );
	}







	public static function arg( string $name ): string {

		return isset( $_GET[ $name ] ) && is_scalar( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $name ] ) ) : '';
	}






	public static function route(): void {
		if ( ! is_front_page() ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			self::leave( wp_login_url( home_url( '/' ) ) );
		}
		nocache_headers();


		if ( isset( $_POST[ Actions::FIELD ] ) ) {
			self::leave( Actions::handle() );
		}

		$tabs = self::tabs();
		$tab  = sanitize_key( self::arg( self::ARG_TAB ) );
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = (string) array_key_first( $tabs );
		}
		if ( 'all' === $tab && '' !== self::arg( self::ARG_CSV ) ) {
			Views::send_csv();
			exit;
		}


		echo self::document( Views::screen( $tab ) );
		exit;
	}















	public static function deployment_chrome(): array {
		$chrome = (array) apply_filters(
			'pse_chrome',
			array(
				'head'   => '',
				'brand'  => '',
				'footer' => '',
			)
		);
		return array(
			'head'   => (string) ( $chrome['head'] ?? '' ),
			'brand'  => (string) ( $chrome['brand'] ?? '' ),
			'footer' => (string) ( $chrome['footer'] ?? '' ),
		);
	}







	public static function document( array $screen ): string {
		$chrome = self::deployment_chrome();
		$out    = '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
		$out   .= '<title>' . esc_html( $screen['title'] . ' · ' . self::APP_NAME ) . '</title>';


		$out .= '<link rel="stylesheet" href="' . esc_url( self::BOOTSTRAP['css'] ) . '" integrity="' . esc_attr( self::BOOTSTRAP['css_sri'] ) . '" crossorigin="anonymous">';

		$out .= '<link rel="stylesheet" href="' . esc_url( self::BOOTSTRAP_ICONS['css'] ) . '" integrity="' . esc_attr( self::BOOTSTRAP_ICONS['css_sri'] ) . '" crossorigin="anonymous">';
		$bar  = self::admin_bar();
		$out .= '<style>' . Assets::css() . '</style>' . $bar['head'] . $chrome['head'] . '</head><body class="pse' . ( '' !== $bar['body'] ? ' admin-bar' : '' ) . '">' . $bar['body'];
		$out .= self::chrome( $screen['tab'], $chrome['brand'] );

		$out .= '<div class="pse-head"><div class="container d-flex flex-wrap align-items-start gap-3">';
		$out .= '<div class="me-auto"><h1 class="pse-h1">' . esc_html( $screen['title'] ) . '</h1>';
		if ( ! empty( $screen['subtitle'] ) ) {
			$out .= '<p class="pse-sub">' . esc_html( $screen['subtitle'] ) . '</p>';
		}
		$out .= '</div>' . ( $screen['actions'] ?? '' ) . '</div></div>';

		$out .= '<main class="container py-4">' . self::notice() . $screen['body'] . '</main>';


		$out .= '<footer class="pse-foot"><div class="container">' . $chrome['footer'] . '</div></footer>';

		$out .= '<script src="' . esc_url( self::BOOTSTRAP['js'] ) . '" integrity="' . esc_attr( self::BOOTSTRAP['js_sri'] ) . '" crossorigin="anonymous"></script>';
		$out .= '<script>' . Assets::contents( 'js/pse-app.js' ) . '</script>';
		$out .= $bar['foot'] . '</body></html>';
		return $out;
	}












	private static function admin_bar(): array {
		global $wp_admin_bar;
		$none = array(
			'head' => '',
			'body' => '',
			'foot' => '',
		);
		if ( ! is_admin_bar_showing() || ! is_object( $wp_admin_bar ) ) {
			return $none;
		}
		ob_start();
		wp_print_styles( array( 'admin-bar' ) );
		$head = (string) ob_get_clean() . '<style>html{margin-top:32px!important}@media screen and (max-width:782px){html{margin-top:46px!important}}</style>';
		ob_start();
		wp_admin_bar_render();
		$body = (string) ob_get_clean();
		ob_start();
		wp_print_scripts( array( 'admin-bar' ) );
		return array(
			'head' => $head,
			'body' => $body,
			'foot' => (string) ob_get_clean(),
		);
	}












	private static function chrome( string $current, string $brand = '' ): string {
		$user = wp_get_current_user();
		$name = (string) $user->display_name;
		$out  = '<header class="pse-top"><div class="container pse-top-in">' . $brand;
		$out .= '<a class="pse-brand" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( self::APP_NAME ) . '</a>';
		$out .= '<details class="pse-yo"><summary><span class="pse-avatar" aria-hidden="true">' . esc_html( strtoupper( mb_substr( $name, 0, 1 ) ) ) . '</span>';
		$out .= '<span class="pse-yo-txt"><strong>' . esc_html( $name ) . '</strong><small>' . esc_html( self::role_label( $user ) ) . '</small></span></summary><div class="pse-yo-menu">';
		if ( current_user_can( 'manage_options' ) ) {
			$out .= '<a href="' . esc_url( admin_url() ) . '">Escritorio</a>';
		}

		if ( current_user_can( 'switch_users' ) ) {
			$out .= '<a href="' . esc_url( admin_url( 'users.php' ) ) . '">Ver como otro usuario</a>';
		}
		$out .= '<a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Salir</a></div></details>';
		$out .= '</div></header>';

		$out .= '<nav class="pse-nav" aria-label="Secciones"><div class="container"><ul class="nav">';
		foreach ( self::tabs() as $key => $tab ) {
			$active = $key === $current;
			$out   .= '<li class="nav-item"><a class="nav-link' . ( $active ? ' active' : '' ) . '"' . ( $active ? ' aria-current="page"' : '' ) . ' href="' . esc_url( self::url( array( self::ARG_TAB => $key ) ) ) . '">' . esc_html( $tab ) . '</a></li>';
		}
		$out .= '</ul></div></nav>';
		return $out;
	}







	public static function role_label( \WP_User $user ): string {
		$names = wp_roles()->role_names;
		foreach ( array( 'administrator', 'pse_coordinator', 'pse_psychologist', 'pse_school_head' ) as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return 'administrator' === $role ? 'Administración' : translate_user_role( $names[ $role ] ?? $role );
			}
		}
		return '';
	}






	public static function notice(): string {
		$texts = array(
			'saved'   => array( 'success', 'Guardado.' ),
			'denied'  => array( 'danger', 'No tiene permiso para hacer eso.' ),
			'subject' => array( 'warning', 'Elija un asunto de la lista.' ),
			'centre'  => array( 'warning', 'Su cuenta no tiene un centro asignado: no puede solicitar una intervención.' ),
		);
		$key   = sanitize_key( self::arg( self::ARG_NOTICE ) );
		return isset( $texts[ $key ] ) ? '<div class="alert alert-' . esc_attr( $texts[ $key ][0] ) . '" role="status">' . esc_html( $texts[ $key ][1] ) . '</div>' : '';
	}







	public static function leave( string $url ): void {
		wp_safe_redirect( $url );
		exit;
	}
}
}








namespace Pse;

use Pse\Access\Access;
use Pse\Centre\CentreCatalogueSync;
use Pse\Centre\CentreSettings;
use Pse\Notifications\Mailer;
use Pse\PostType\Requests;
use Pse\PublicFront\Screen;









if ( ! class_exists( 'Pse\App', false ) ) {
final class App {






	public static function boot(): void {
		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;


		CentreCatalogueSync::register_cron();
		CentreSettings::register();
		add_action( 'init', array( Requests::class, 'register' ), 10 );
		add_action( 'init', array( Access::class, 'grant_caps_to_roles' ), 11 );
		Mailer::register();


		Screen::register();
	}
}
}


\Pse\PublicFront\Assets::set_inline( array (
  'css/pse-app.css' => '/* La piel de los aplicativos hermanos (eventos y
   registro de visitas), con los mismos colores, cabecera, pestañas y pie. */

.pse {
	--pse-brand: #1b4f8a;
	--pse-brand-cont: #e3ecf7;
	--pse-line: #e4e7eb;
	--pse-soft: #f7f8fa;
	--pse-sup-2: #f4f6f9;
	--pse-text: #1c2024;
	--pse-text-2: #5a6672;
	--pse-foot-bg: #05395c;
	--pse-ok: #1f6b45;
	--pse-ok-cont: #e4f0e8;
	--pse-wait: #8a5a12;
	--pse-wait-cont: #f9efe0;
	--pse-bad: #a8342c;
	--pse-bad-cont: #fae9e7;
	--pse-e1: 0 1px 2px rgba(16, 24, 40, .05), 0 1px 3px rgba(16, 24, 40, .07);
	--pse-e2: 0 2px 4px rgba(16, 24, 40, .05), 0 4px 12px rgba(16, 24, 40, .09);
	--pse-r: 12px;
	--pse-width: 1200px;
	--bs-primary: #1b4f8a;
	--bs-primary-rgb: 27, 79, 138;
	--bs-link-color: #1b4f8a;
	--bs-link-color-rgb: 27, 79, 138;
	--bs-link-hover-color: #123a68;
	background: var(--pse-soft);
	color: var(--pse-text);
	/* El pie, abajo del todo aunque haya poco contenido. */
	min-height: 100vh;
	display: flex;
	flex-direction: column;
}
.pse main { flex: 1 0 auto; }
.pse .container { max-width: var(--pse-width); }

/* Cabecera: marca del despliegue (si la hay), nombre del aplicativo y la cuenta */
.pse-top { background: #fff; border-bottom: 1px solid var(--pse-line); }
.pse-top-in { display: flex; align-items: center; gap: 14px; min-height: 72px; flex-wrap: wrap; padding-top: 10px; padding-bottom: 10px; }
.pse-brand { color: var(--pse-brand); font-weight: 700; font-size: 18px; letter-spacing: -.02em; text-decoration: none; }
.pse-yo { margin-left: auto; position: relative; }
.pse-yo > summary { list-style: none; display: flex; align-items: center; gap: 10px; cursor: pointer; border-radius: 12px; padding: 4px 6px; }
.pse-yo > summary::-webkit-details-marker { display: none; }
.pse-yo > summary:hover, .pse-yo[open] > summary { background: var(--pse-sup-2); }
.pse-avatar { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 50%; background: var(--pse-brand-cont); color: var(--pse-brand); font-size: 13px; font-weight: 700; }
.pse-yo-txt { display: flex; flex-direction: column; line-height: 1.25; }
.pse-yo-txt strong { font-size: 14px; font-weight: 600; }
.pse-yo-txt small { font-size: 12.5px; color: var(--pse-text-2); }
.pse-yo-menu { position: absolute; right: 0; top: calc(100% + 6px); min-width: 12rem; padding: 6px; background: #fff; border: 1px solid var(--pse-line); border-radius: 12px; box-shadow: var(--pse-e2); z-index: 1050; display: grid; }
.pse-yo-menu a { padding: 10px 12px; border-radius: 8px; color: var(--pse-text); text-decoration: none; font-size: 14px; }
.pse-yo-menu a:hover { background: var(--pse-sup-2); color: var(--pse-brand); }

/* Pestañas */
.pse-nav { background: #fff; border-bottom: 1px solid var(--pse-line); }
.pse-nav .nav { flex-wrap: nowrap; overflow-x: auto; gap: 4px; }
.pse-nav .nav-link { display: flex; align-items: center; min-height: 48px; padding: 0 16px; font-size: 14.5px; color: var(--pse-text-2); border-bottom: 3px solid transparent; white-space: nowrap; }
.pse-nav .nav-link:hover { color: var(--pse-brand); }
.pse-nav .nav-link.active { color: var(--pse-brand); font-weight: 700; border-bottom-color: var(--pse-brand); }

/* Título de la pantalla */
.pse-head { padding: 28px 0 4px; }
/* Sin título a la vista —lo dice la pestaña activa— la banda solo lleva los
   botones de la pantalla, y no necesita tanto aire. */
.pse-head.is-bare { padding: 14px 0 0; }
.pse-h1 { font-size: 27px; font-weight: 700; letter-spacing: -.02em; margin: 0 0 4px; }

/* Pie: solo lo que pone quien despliega */
.pse-foot { background: var(--pse-foot-bg); color: #fff; font-size: 13px; padding: 18px 0; }
.pse-foot .container { display: flex; flex-wrap: wrap; gap: 8px 24px; align-items: baseline; }
.pse-foot a { color: #fff; text-decoration: underline; }
.pse-foot a:hover { color: #fff; text-decoration: none; }
.pse-foot-credit { color: rgba(255, 255, 255, 0.72); }
.pse-foot-legal { margin-left: auto; display: flex; flex-wrap: wrap; gap: 8px 18px; }

/* Tarjetas, avisos y botones */
.pse-card { background: #fff; border: 1px solid var(--pse-line); border-radius: var(--pse-r); padding: 22px; margin-bottom: 18px; }
.pse-card h2 { font-size: 15px; font-weight: 700; margin: 0 0 14px; }
.pse-card h2 label { margin: 0; }
.pse .form-label { font-weight: 600; font-size: 14.5px; }
.pse-warn { border-radius: var(--pse-r); }
.pse-btn { border-radius: 999px; padding: .6rem 1.5rem; font-weight: 700; }
.pse-note { margin: 16px 0 0; color: var(--pse-text-2); font-size: 13.5px; }
.pse-empty { margin: 0; padding: 24px; background: #fff; border-radius: var(--pse-r); color: var(--pse-text-2); }

/* Filtros y estados */
.pse-filters { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
.pse-filters .form-control { flex: 2 1 16rem; }
.pse-filters .form-select { flex: 1 1 11rem; width: auto; }
.pse-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
.pse-chip { padding: 6px 14px; border-radius: 999px; border: 1px solid var(--pse-line); background: #fff; color: var(--pse-text); font-size: 14px; text-decoration: none; }
.pse-chip b { margin-left: 4px; padding: 1px 7px; border-radius: 999px; background: var(--pse-sup-2); font-size: 12px; }
.pse-chip.is-on { background: var(--pse-brand); border-color: var(--pse-brand); color: #fff; font-weight: 700; }
.pse-chip.is-on b { background: rgba(255, 255, 255, .25); }
.pse-state { display: inline-block; padding: .25em .7em; border-radius: 999px; font-size: 12px; font-weight: 700; white-space: nowrap; }
.pse-state-pending { background: var(--pse-wait-cont); color: var(--pse-wait); }
.pse-state-in_progress { background: var(--pse-brand-cont); color: var(--pse-brand); }
.pse-state-attended { background: var(--pse-ok-cont); color: var(--pse-ok); }

/* Listados */
.pse-table { background: #fff; font-size: 14px; margin: 0; }
.pse-table th { font-size: 12.5px; color: var(--pse-text-2); font-weight: 600; white-space: nowrap; }
.pse-table td, .pse-table th { padding: 12px 10px; vertical-align: middle; }
.pse-status-form { display: flex; gap: 6px; align-items: center; }
.pse-status-form .form-select { width: auto; }
.pse-list-foot { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-top: 18px; }
.pse-pages { display: flex; gap: 8px; align-items: center; color: var(--pse-text-2); font-size: 14px; }

/* Estadísticas */
.pse-figures { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; margin-bottom: 18px; }
.pse-figure { background: #fff; border-radius: var(--pse-r); box-shadow: var(--pse-e1); padding: 16px 18px; }
.pse-figure b { display: block; font-size: 32px; line-height: 1.1; letter-spacing: -.03em; }
.pse-figure span { font-size: 12.5px; color: var(--pse-text-2); }
.pse-bars { display: grid; grid-template-columns: minmax(8rem, 16rem) 1fr; gap: 8px 12px; margin: 0; font-size: 13.5px; align-items: center; }
.pse-bars dt { font-weight: 400; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pse-bars dd { display: flex; align-items: center; gap: 8px; margin: 0; }
.pse-bars dd span { display: block; height: 16px; min-width: 2px; background: var(--pse-brand); border-radius: 3px; }
.pse-bars dd b { font-weight: 400; font-variant-numeric: tabular-nums; }

/* Los botones, del color del aplicativo y no del azul de Bootstrap */
.pse .btn-primary { --bs-btn-bg: var(--pse-brand); --bs-btn-border-color: var(--pse-brand); --bs-btn-hover-bg: #123a68; --bs-btn-hover-border-color: #123a68; --bs-btn-active-bg: #123a68; --bs-btn-active-border-color: #123a68; }
.pse .btn-outline-primary { --bs-btn-color: var(--pse-brand); --bs-btn-border-color: var(--pse-brand); --bs-btn-hover-bg: var(--pse-brand); --bs-btn-hover-border-color: var(--pse-brand); --bs-btn-active-bg: var(--pse-brand); --bs-btn-active-border-color: var(--pse-brand); }
',
  'js/pse-app.js' => '/*
 * Lo único que hace el guion: al elegir un estado, enviar su formulario.
 *
 * Sin JavaScript el formulario funciona igual, con su botón «Guardar»; con
 * él, el botón sobra y se esconde.
 */
( function () {
	function onChange( event ) {
		const select = event.target;
		if ( ! select || ! select.matches || ! select.matches( \'select[data-pse-autosubmit]\' ) || ! select.form ) {
			return;
		}
		if ( typeof select.form.requestSubmit === \'function\' ) {
			select.form.requestSubmit();
		} else {
			select.form.submit();
		}
	}

	function hideButtons() {
		document.querySelectorAll( \'select[data-pse-autosubmit]\' ).forEach( function ( select ) {
			const button = select.form ? select.form.querySelector( \'button[type="submit"]\' ) : null;
			if ( button ) {
				button.classList.add( \'visually-hidden\' );
			}
		} );
	}

	document.addEventListener( \'change\', onChange );
	hideButtons();
	window.pseApp = { onChange, hideButtons };
}() );
',
) );

if ( \class_exists( \Pse\App::class ) ) {
	\Pse\App::boot();
}

// phpcs:enable
