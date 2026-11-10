<?php
/**
 * Zones: which zone each centre belongs to and who attends each zone.
 *
 * @package Pse
 */

namespace Pse\Centre;

/**
 * Las zonas no salen del catálogo de centros: son dos listas que pega quien
 * coordina (ADR-0004). «Código de centro; zona» dice a qué zona va cada
 * solicitud, y «zona; correo» dice a quién se avisa y quién la ve: el
 * profesional es la cuenta del sitio que tiene ese correo.
 */
final class Zones {

	public const OPTION_CENTRES = 'pse_centre_zones';

	public const OPTION_STAFF = 'pse_zone_staff';

	/**
	 * Zone of each centre, code => zone.
	 *
	 * @return array<string, string>
	 */
	public static function centres(): array {
		$raw = get_option( self::OPTION_CENTRES, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Who attends each zone, zone => emails.
	 *
	 * @return array<string, string[]>
	 */
	public static function staff(): array {
		$raw = get_option( self::OPTION_STAFF, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Zone of a centre, or '' when nobody has assigned it one.
	 *
	 * @param string $code Centre code.
	 * @return string
	 */
	public static function of_centre( string $code ): string {
		return (string) ( self::centres()[ trim( $code ) ] ?? '' );
	}

	/**
	 * Every known zone, sorted.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		$zones = array_unique( array_merge( array_values( self::centres() ), array_keys( self::staff() ) ) );
		sort( $zones, SORT_NATURAL | SORT_FLAG_CASE );
		return array_values( array_map( 'strval', $zones ) );
	}

	/**
	 * Emails to notify for a zone.
	 *
	 * @param string $zone Zone.
	 * @return string[]
	 */
	public static function emails( string $zone ): array {
		$emails = self::staff()[ $zone ] ?? array();
		return is_array( $emails ) ? array_values( $emails ) : array();
	}

	/**
	 * Zones an email attends.
	 *
	 * @param string $email Email of the account.
	 * @return string[]
	 */
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

	/**
	 * Split a pasted list into pairs: one per line, separated by «;», tab or «,».
	 *
	 * @param string $text Pasted text.
	 * @return array<int, array{0:string, 1:string}>
	 */
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

	/**
	 * Read «code; zone» lines.
	 *
	 * @param string $text Pasted text.
	 * @return array<string, string> Code => zone. Lines without an 8-digit code are dropped.
	 */
	public static function parse_centres( string $text ): array {
		$out = array();
		foreach ( self::pairs( $text ) as $pair ) {
			if ( preg_match( '/^\d{8}$/', $pair[0] ) ) {
				$out[ $pair[0] ] = sanitize_text_field( $pair[1] );
			}
		}
		return $out;
	}

	/**
	 * Read «zone; email» lines.
	 *
	 * @param string $text Pasted text.
	 * @return array<string, string[]> Zone => emails. Lines without a valid email are dropped.
	 */
	public static function parse_staff( string $text ): array {
		$out = array();
		foreach ( self::pairs( $text ) as $pair ) {
			$zone  = sanitize_text_field( $pair[0] );
			$email = strtolower( sanitize_email( $pair[1] ) );
			if ( '' !== $zone && is_email( $email ) && ! in_array( $email, $out[ $zone ] ?? array(), true ) ) {
				$out[ $zone ][] = $email;
			}
		}
		return $out;
	}

	/**
	 * Store both lists.
	 *
	 * @param array<string, string>   $centres Code => zone.
	 * @param array<string, string[]> $staff   Zone => emails.
	 * @return void
	 */
	public static function save( array $centres, array $staff ): void {
		update_option( self::OPTION_CENTRES, $centres, false );
		update_option( self::OPTION_STAFF, $staff, false );
	}

	/**
	 * The centres list as the text it was pasted from.
	 *
	 * @return string
	 */
	public static function centres_text(): string {
		$lines = array();
		foreach ( self::centres() as $code => $zone ) {
			$lines[] = $code . '; ' . $zone;
		}
		return implode( "\n", $lines );
	}

	/**
	 * The staff list as the text it was pasted from.
	 *
	 * @return string
	 */
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
