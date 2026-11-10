<?php
/**
 * Closed lists of the application.
 *
 * @package Pse
 */

namespace Pse\Domain;

/**
 * Closed lists of the application: subjects and states.
 *
 * Una solicitud solo dice de qué va con un asunto de esta lista: no hay texto
 * libre, para que nadie escriba datos del alumnado (ADR-0003). Los asuntos son
 * los de quien despliega y se cambian con el filtro `pse_subjects`; los estados
 * son del aplicativo.
 */
final class Vocabulary {

	public const STATUS_PENDING     = 'pending';
	public const STATUS_IN_PROGRESS = 'in_progress';
	public const STATUS_ATTENDED    = 'attended';

	/**
	 * Subjects a centre can choose, slug => label.
	 *
	 * @return array<string, string>
	 */
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
	 * States of a request, slug => label, in the order they happen.
	 *
	 * @return array<string, string>
	 */
	public static function states(): array {
		return array(
			self::STATUS_PENDING     => 'Pendiente',
			self::STATUS_IN_PROGRESS => 'En atención',
			self::STATUS_ATTENDED    => 'Atendida',
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
		);
		return $plural[ $slug ] ?? '';
	}
}
