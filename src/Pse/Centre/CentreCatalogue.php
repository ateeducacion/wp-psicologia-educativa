<?php
/**
 * Local cached catalogue of educational centres.
 *
 * @package Pse
 */

namespace Pse\Centre;

/**
 * Acceso al catálogo de centros educativos cacheado localmente en WordPress.
 *
 * El catálogo maestro es externo y este aplicativo solo conserva una copia
 * local en una opción (autoload = false) para no hacer peticiones remotas al
 * pintar la ficha de la acción o al guardarla (ADR-0007). Es el mismo que usa
 * el aplicativo de eventos (ADR-0002).
 */
final class CentreCatalogue {

	public const OPTION_CATALOGUE = 'pse_centres_catalogue';

	public const OPTION_STATUS = 'pse_centres_catalogue_status';

	/**
	 * All stored centres (both active and inactive), indexed by 8-digit code.
	 *
	 * @return array<string, array{code:string, name:string, island:string, municipality:string, type:string, cep:string, eoep?:string, active:bool}>
	 */
	public static function all(): array {
		$raw = get_option( self::OPTION_CATALOGUE, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * All active centres, indexed by code.
	 *
	 * @return array<string, array{code:string, name:string, island:string, municipality:string, type:string, cep:string, eoep?:string, active:bool}>
	 */
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

	/**
	 * Map of active centres [ code => name ] sorted alphabetically by name.
	 *
	 * El centro de referencia manda sobre la isla: una isla puede tener varios
	 * ámbitos, y entonces acotar por isla ofrece centros que no son del ámbito.
	 * La isla solo se usa mientras la copia local no diga a qué centro de
	 * referencia pertenece cada centro (ADR-0007).
	 *
	 * @param string $cep    Keep only the centres whose reference centre is this code; '' for all.
	 * @param string $island Keep only the centres of this island, when the copy has no reference centre; '' for all.
	 * @return array<string, string>
	 */
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

	/**
	 * Whether the stored copy says which reference centre each centre belongs to.
	 *
	 * El campo llegó al catálogo maestro después que el aplicativo, así que una
	 * copia local sin sincronizar desde entonces no lo trae y hay que seguir
	 * acotando por isla.
	 *
	 * @return bool
	 */
	public static function knows_cep(): bool {
		foreach ( self::all() as $centre ) {
			if ( isset( $centre['cep'] ) && '' !== trim( (string) $centre['cep'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Find a centre by its name, as the catalogue writes it.
	 *
	 * El término del ámbito se llama exactamente como el centro del profesorado
	 * en el catálogo (ADR-0007), así que de un ámbito se llega a su centro y,
	 * con él, a su isla.
	 *
	 * @param string $name Name.
	 * @return array{code:string, name:string, island:string, municipality:string, type:string, cep:string, eoep?:string, active:bool}|null
	 */
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

	/**
	 * Find a centre by code (active or inactive).
	 *
	 * @param string $code 8-digit official code.
	 * @return array{code:string, name:string, island:string, municipality:string, type:string, cep:string, eoep?:string, active:bool}|null
	 */
	public static function find( string $code ): ?array {
		$code = trim( $code );
		if ( '' === $code ) {
			return null;
		}
		$all = self::all();
		return isset( $all[ $code ] ) && is_array( $all[ $code ] ) ? $all[ $code ] : null;
	}

	/**
	 * How a centre reads: «name (code)».
	 *
	 * Sin nombre, el del catálogo; sin código, el nombre solo, que es como
	 * llegó el centro escrito a mano del sistema anterior.
	 *
	 * @param string $code 8-digit official code, or ''.
	 * @param string $name Name to show; '' takes it from the catalogue.
	 * @return string
	 */
	public static function label( string $code, string $name = '' ): string {
		if ( '' === $name ) {
			$name = (string) ( self::find( $code )['name'] ?? '' );
		}
		return '' === $code ? $name : trim( $name . ' (' . $code . ')' );
	}

	/**
	 * Check if a centre exists and is currently active.
	 *
	 * @param string $code 8-digit official code.
	 * @return bool
	 */
	public static function is_active( string $code ): bool {
		$centre = self::find( $code );
		return null !== $centre && ! empty( $centre['active'] );
	}

	/**
	 * Current synchronization status and metadata.
	 *
	 * @return array{schema_version:int, sha256:string, catalogue_updated_at:string, last_checked_at:string, last_success_at:string, last_error:string, record_count:int, active_count:int}
	 */
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

	/**
	 * Total count of stored centres.
	 *
	 * @return int
	 */
	public static function total(): int {
		return count( self::all() );
	}

	/**
	 * Count of active centres.
	 *
	 * @return int
	 */
	public static function active_count(): int {
		return count( self::all_active() );
	}
}
