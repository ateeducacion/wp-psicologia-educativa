<?php
/**
 * What the forms of the application do.
 *
 * @package Pse
 */

namespace Pse\PublicFront;

use Pse\Access\Access;
use Pse\Centre\Zones;
use Pse\PostType\Requests;

/**
 * Tres formularios y los tres por POST, con su nonce y su permiso comprobado
 * aquí, no en la pantalla que los pinta. Cada uno devuelve adónde volver:
 * POST → redirección → GET.
 */
final class Actions {

	public const FIELD = 'pse_action';

	public const NONCE = '_pse_nonce';

	/**
	 * The hidden fields every form carries.
	 *
	 * @param string $action Action slug.
	 * @return string
	 */
	public static function fields( string $action ): string {
		return wp_nonce_field( 'pse_' . $action, self::NONCE, true, false ) . '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="' . esc_attr( $action ) . '">';
	}

	/**
	 * A posted field.
	 *
	 * @param string $name Field.
	 * @return string
	 */
	private static function posted( string $name ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- handle() verifies the nonce before any handler reads.
		return isset( $_POST[ $name ] ) && is_scalar( $_POST[ $name ] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST[ $name ] ) ) : '';
	}

	/**
	 * Run the posted action.
	 *
	 * @return string Where to go next.
	 */
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

	/**
	 * A centre asks for an intervention.
	 *
	 * @return string
	 */
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

	/**
	 * Whoever attends the zone changes the state of a request.
	 *
	 * @return string
	 */
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

	/**
	 * The coordination pastes the two zone lists.
	 *
	 * @return string
	 */
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
