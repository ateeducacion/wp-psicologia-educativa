<?php
/**
 * The notice a new request sends to whoever attends its zone.
 *
 * @package Pse
 */

namespace Pse\Notifications;

use Pse\Centre\Zones;
use Pse\PostType\Requests;

/**
 * El correo dice que hay una solicitud, de qué centro y con qué número, y
 * nada más: el asunto se consulta dentro del aplicativo, con sesión (ADR-0006).
 */
final class Mailer {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'pse_request_created', array( self::class, 'notify' ) );
	}

	/**
	 * The message of a request.
	 *
	 * @param int $id Request ID.
	 * @return array{to:string[], subject:string, body:string}|null Null when nobody attends the zone.
	 */
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

	/**
	 * Send the notice.
	 *
	 * @param int $id Request ID.
	 * @return bool Whether a message went out.
	 */
	public static function notify( $id ): bool {
		$message = self::message( (int) $id );
		return null !== $message && (bool) wp_mail( $message['to'], $message['subject'], $message['body'] );
	}
}
