<?php
/**
 * Counts of requests for the statistics screen.
 *
 * @package Pse
 */

namespace Pse\Domain;

/**
 * La estadística cuenta solicitudes, no personas: el aplicativo no guarda a
 * quién se atiende (ADR-0003).
 */
final class Stats {

	/**
	 * Count rows by each dimension.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows from `Requests::rows()`.
	 * @return array{total:int, status:array<string,int>, subject:array<string,int>, municipality:array<string,int>, island:array<string,int>, zone:array<string,int>, month:array<string,int>}
	 */
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
