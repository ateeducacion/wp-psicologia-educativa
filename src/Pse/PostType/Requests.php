<?php
/**
 * The intervention request: content type, fields, creation and queries.
 *
 * @package Pse
 */

namespace Pse\PostType;

use Pse\Access\Access;
use Pse\Centre\CentreCatalogue;
use Pse\Centre\Zones;
use Pse\Domain\Vocabulary;

/**
 * Una solicitud es una entrada con siete datos y ninguno es de una persona
 * atendida (ADR-0003): número, centro, municipio, isla, zona, asunto y estado.
 * El centro, el municipio y la zona se copian al crearla, para que la
 * estadística no cambie si luego cambia el catálogo o el reparto de zonas.
 */
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

	/**
	 * Meta keys of a request.
	 *
	 * @return string[]
	 */
	public static function keys(): array {
		return array( self::NUMBER, self::CENTRE_CODE, self::CENTRE_NAME, self::MUNICIPALITY, self::ISLAND, self::ZONE, self::SUBJECT, self::STATUS );
	}

	/**
	 * Register the content type and its fields.
	 *
	 * En el escritorio solo lo ve la administración: los perfiles trabajan en
	 * la portada, y ahí decide `Access`.
	 *
	 * @return void
	 */
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

	/**
	 * The next request number of a year: «2026-0001».
	 *
	 * @param int $year Year.
	 * @return string
	 */
	public static function next_number( int $year ): string {
		$all          = get_option( self::OPTION_SEQUENCE, array() );
		$all          = is_array( $all ) ? $all : array();
		$next         = (int) ( $all[ $year ] ?? 0 ) + 1;
		$all[ $year ] = $next;
		update_option( self::OPTION_SEQUENCE, $all, false );
		return sprintf( '%d-%04d', $year, $next );
	}

	/**
	 * Create a request for the centre of a user.
	 *
	 * @param int    $user_id User who asks.
	 * @param string $subject Subject slug.
	 * @return int|\WP_Error Request ID.
	 */
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

		/**
		 * Fires when a centre has asked for an intervention.
		 *
		 * @param int $id Request ID.
		 */
		do_action( 'pse_request_created', (int) $id );
		return (int) $id;
	}

	/**
	 * Change the state of a request, if the user may.
	 *
	 * @param int    $id      Request ID.
	 * @param string $status  State slug.
	 * @param int    $user_id User; 0 is the current one.
	 * @return bool
	 */
	public static function set_status( int $id, string $status, int $user_id = 0 ): bool {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || ! isset( Vocabulary::states()[ $status ] ) || ! Access::can_set_status( $post, $user_id ) ) {
			return false;
		}
		update_post_meta( $id, self::STATUS, $status );
		return true;
	}

	/**
	 * The fields of a request, ready to print or export.
	 *
	 * @param \WP_Post $post Request.
	 * @return array{id:int, number:string, date:string, centre_code:string, centre_name:string, municipality:string, island:string, zone:string, subject:string, status:string}
	 */
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

	/**
	 * Query requests.
	 *
	 * `centre` y `zones` son el alcance de quien mira y los pone la pantalla,
	 * no el visitante; el resto son los filtros del listado. Un alcance de
	 * zonas vacío no devuelve nada.
	 *
	 * @param array{centre?:string, zones?:string[], status?:string, subject?:string, zone?:string, municipality?:string, search?:string, paged?:int, per_page?:int} $args Scope and filters.
	 * @return \WP_Query
	 */
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
				'meta_query'             => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- el alcance y los filtros son campos de la solicitud.
			)
		);
	}

	/**
	 * Every request of a scope, as rows: for the statistics and the export.
	 *
	 * @param array<string, mixed> $args Scope and filters, as in `query()`.
	 * @return array<int, array<string, mixed>>
	 */
	public static function rows( array $args = array() ): array {
		$args['per_page'] = -1;
		$args['paged']    = 1;
		return array_map( array( self::class, 'row' ), self::query( $args )->posts );
	}
}
