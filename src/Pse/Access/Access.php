<?php
/**
 * Who may do what: the single guard of the application.
 *
 * @package Pse
 */

namespace Pse\Access;

use Pse\Centre\Zones;
use Pse\PostType\Requests;

/**
 * Tres perfiles y una capacidad por cada cosa que se hace (ADR-0005). El centro
 * de quien solicita sale de su cuenta, nunca del formulario, y sin centro no se
 * solicita ni se ve nada. Esconder algo en un listado no es protegerlo: todo lo
 * que se pinta o se cambia pasa antes por esta clase.
 */
final class Access {

	public const CAP_REQUEST = 'pse_request_intervention';
	public const CAP_ATTEND  = 'pse_attend_requests';
	public const CAP_OVERSEE = 'pse_oversee_requests';
	public const CAP_ZONES   = 'pse_manage_zones';
	public const CAP_APP     = 'pse_manage_app';

	/**
	 * Capabilities of each role.
	 *
	 * El filtro `pse_role_caps` deja a quien despliega dárselas a un rol que ya
	 * tenga —el de las direcciones de centro que crea su inicio de sesión—.
	 *
	 * @return array<string, string[]>
	 */
	public static function role_caps(): array {
		$map = array(
			'pse_school_head'  => array( self::CAP_REQUEST ),
			'pse_psychologist' => array( self::CAP_ATTEND ),
			'pse_coordinator'  => array( self::CAP_OVERSEE, self::CAP_ZONES ),
			'administrator'    => array( self::CAP_REQUEST, self::CAP_ATTEND, self::CAP_OVERSEE, self::CAP_ZONES, self::CAP_APP ),
		);

		/**
		 * Filters which role holds which application capability.
		 *
		 * @param array<string, string[]> $map Role slug => capabilities.
		 */
		return (array) apply_filters( 'pse_role_caps', $map );
	}

	/**
	 * Give each role its capabilities. Additive and idempotent.
	 *
	 * @return void
	 */
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

	/**
	 * Centre code of a user, or '' when the account has none.
	 *
	 * @param int $user_id User; 0 is the current one.
	 * @return string
	 */
	public static function centre_code( int $user_id = 0 ): string {
		$user_id = $user_id ? $user_id : get_current_user_id();

		/**
		 * Filters the user meta key that holds the centre code.
		 *
		 * @param string $key Meta key.
		 */
		$key  = (string) apply_filters( 'pse_centre_code_meta_key', 'pse_centre_code' );
		$code = trim( (string) get_user_meta( $user_id, $key, true ) );
		return preg_match( '/^\d{8}$/', $code ) ? $code : '';
	}

	/**
	 * Zones a user attends: those listed with the email of the account.
	 *
	 * @param int $user_id User; 0 is the current one.
	 * @return string[]
	 */
	public static function zones( int $user_id = 0 ): array {
		$user = get_userdata( $user_id ? $user_id : get_current_user_id() );
		return $user ? Zones::of_email( (string) $user->user_email ) : array();
	}

	/**
	 * Whether a user may see a request.
	 *
	 * @param \WP_Post $post    Request.
	 * @param int      $user_id User; 0 is the current one.
	 * @return bool
	 */
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

	/**
	 * Whether a user may change the state of a request.
	 *
	 * Solo quien atiende la zona; la administración, cualquiera.
	 *
	 * @param \WP_Post $post    Request.
	 * @param int      $user_id User; 0 is the current one.
	 * @return bool
	 */
	public static function can_set_status( \WP_Post $post, int $user_id = 0 ): bool {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( Requests::TYPE !== $post->post_type || ! $user_id ) {
			return false;
		}
		return user_can( $user_id, 'manage_options' ) || self::attends( $post, $user_id );
	}

	/**
	 * Whether the request is in one of the zones the user attends.
	 *
	 * @param \WP_Post $post    Request.
	 * @param int      $user_id User.
	 * @return bool
	 */
	private static function attends( \WP_Post $post, int $user_id ): bool {
		$zone = (string) get_post_meta( $post->ID, Requests::ZONE, true );
		return user_can( $user_id, self::CAP_ATTEND ) && '' !== $zone && in_array( $zone, self::zones( $user_id ), true );
	}
}
