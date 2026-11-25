<?php
/**
 * Closed lists of the application.
 *
 * @package Pse
 */

namespace Pse\Domain;

/**
 * Closed lists of the application: subjects, scopes, authorizations and states.
 *
 * Una solicitud solo dice de qué va con un asunto de esta lista: no hay texto
 * libre, para que nadie escriba datos del alumnado (ADR-0003). Los asuntos son
 * los de quien despliega y se cambian con el filtro `pse_subjects`; su orden es
 * el de prioridad. El tipo, la autorización y los estados son del aplicativo
 * (ADR-0009).
 */
final class Vocabulary {

	public const STATUS_PENDING     = 'pending';
	public const STATUS_IN_PROGRESS = 'in_progress';
	public const STATUS_ATTENDED    = 'attended';
	public const STATUS_WITHDRAWN   = 'withdrawn';

	/**
	 * Subjects a centre can choose, slug => label.
	 *
	 * @return array<string, string>
	 */
	public static function subjects(): array {
		$default = array(
			'suicidal_behaviour' => 'Conducta suicida o autolesiva',
			'bullying'           => 'Acoso o ciberacoso escolar',
			'gender_violence'    => 'Violencia de género',
			'gender_identity'    => 'Identidad de género',
			'distress'           => 'Malestar emocional',
			'advice'             => 'Asesoramiento al profesorado o a las familias',
			'other'              => 'Otro asunto',
		);

		/**
		 * Filters the subjects a centre can choose.
		 *
		 * @param array<string, string> $subjects Slug => label.
		 */
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

	/**
	 * Label of a subject; the slug itself when it is no longer in the list.
	 *
	 * @param string $slug Subject slug.
	 * @return string
	 */
	public static function subject_label( string $slug ): string {
		return self::subjects()[ $slug ] ?? $slug;
	}

	/**
	 * Whether the intervention is for one pupil or for a group, slug => label.
	 *
	 * @return array<string, string>
	 */
	public static function scopes(): array {
		return array(
			'individual' => 'Individual',
			'group'      => 'Grupal',
		);
	}

	/**
	 * What the centre declares about the family's authorization, slug => label.
	 *
	 * No hay opción «no tiene»: sin autorización no se solicita (ADR-0009).
	 *
	 * @return array<string, string>
	 */
	public static function authorizations(): array {
		return array(
			'authorized'   => 'Se cuenta con la autorización familiar firmada (alumnado menor de edad)',
			'not_required' => 'No requiere autorización familiar (alumnado mayor de edad)',
		);
	}

	/**
	 * The statement the centre makes when it declares the authorization.
	 *
	 * @return string
	 */
	public static function authorization_statement(): string {
		$default = 'Al enviar la solicitud, la dirección del centro declara bajo su responsabilidad que lo indicado sobre la autorización es cierto, y que el centro custodia la autorización y la aportará si se le requiere.';

		/**
		 * Filters the statement shown next to the authorization choice.
		 *
		 * @param string $statement Plain text.
		 */
		$statement = trim( (string) apply_filters( 'pse_authorization_statement', $default ) );
		return '' !== $statement ? $statement : $default;
	}

	/**
	 * Label of a value of a closed list; «—» when it is empty, the value itself when it is unknown.
	 *
	 * @param array<string, string> $labels Slug => label.
	 * @param string                $value Slug.
	 * @return string
	 */
	public static function label_in( array $labels, string $value ): string {
		if ( '' === $value ) {
			return '—';
		}
		return $labels[ $value ] ?? $value;
	}

	/**
	 * States of a request, slug => label, in the order they happen.
	 *
	 * @return array<string, string>
	 */
	public static function states(): array {
		return array(
			self::STATUS_PENDING     => 'Pendiente',
			self::STATUS_IN_PROGRESS => 'En atención',
			self::STATUS_ATTENDED    => 'Atendida',
			self::STATUS_WITHDRAWN   => 'Baja',
		);
	}

	/**
	 * Label of a state.
	 *
	 * @param string $slug State slug.
	 * @return string
	 */
	public static function state_label( string $slug ): string {
		return self::states()[ $slug ] ?? self::states()[ self::STATUS_PENDING ];
	}

	/**
	 * Plural label of a state, for the filter chips.
	 *
	 * @param string $slug State slug.
	 * @return string
	 */
	public static function state_plural( string $slug ): string {
		$plural = array(
			self::STATUS_PENDING     => 'Pendientes',
			self::STATUS_IN_PROGRESS => 'En atención',
			self::STATUS_ATTENDED    => 'Atendidas',
			self::STATUS_WITHDRAWN   => 'Bajas',
		);
		return $plural[ $slug ] ?? '';
	}
}
