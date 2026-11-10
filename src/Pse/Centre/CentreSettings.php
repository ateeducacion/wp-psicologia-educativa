<?php
/**
 * Settings screen of the educational centres catalogue.
 *
 * @package Pse
 */

namespace Pse\Centre;

use RuntimeException;

/**
 * «Ajustes → Centros educativos»: de dónde sale el catálogo y cómo está.
 *
 * La administración pone aquí la dirección del `manifest.json` del listado de
 * centros, si el despliegue no la fija con la constante o el filtro, y puede
 * actualizarlo sin esperar a la copia diaria. No hay dirección por defecto:
 * es de cada despliegue (ADR-0007).
 */
final class CentreSettings {

	/**
	 * Menu slug.
	 */
	public const PAGE = 'pse-centros';

	/**
	 * Nonce action and `admin-post.php` action of the form.
	 */
	public const ACTION = 'pse_centres_sync';

	/**
	 * Who manages the catalogue: the site administration.
	 */
	public const CAP = 'pse_manage_app';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Add the page under Settings.
	 *
	 * @return void
	 */
	public static function menu(): void {
		add_options_page( 'Centros educativos', 'Centros educativos', self::CAP, self::PAGE, array( self::class, 'render' ) );
	}

	/**
	 * The page URL, with the outcome of the last submission.
	 *
	 * @param string $outcome `updated`, `unchanged`, `error`, `bad_url`, `no_source` or ''.
	 * @return string
	 */
	public static function url( string $outcome = '' ): string {
		$args = array( 'page' => self::PAGE );
		if ( '' !== $outcome ) {
			$args['pse_centres'] = $outcome;
		}
		return add_query_arg( $args, admin_url( 'options-general.php' ) );
	}

	/**
	 * Form handler: check, apply and go back to the page.
	 *
	 * @return void
	 */
	public static function handle(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html( 'No tiene permiso para gestionar el catálogo de centros.' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se sanea en submit().
		$url = isset( $_POST['manifest_url'] ) ? (string) wp_unslash( $_POST['manifest_url'] ) : null;
		wp_safe_redirect( self::url( self::submit( $url ) ) );
		exit;
	}

	/**
	 * Save the manifest URL, when sent, and update the catalogue now.
	 *
	 * Sin nonce ni redirección: eso es de {@see handle()}. Una dirección que no
	 * sea https no se guarda y no se descarga nada.
	 *
	 * @param string|null $url Manifest URL from the form; null leaves it as it is.
	 * @return string Outcome: `updated`, `unchanged`, `error`, `bad_url` or `no_source`.
	 */
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
			// El motivo queda en el estado, que la pantalla enseña.
			unset( $e );
			return 'error';
		}
	}

	/**
	 * The page.
	 *
	 * @return void
	 */
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo elige qué aviso se pinta.
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
