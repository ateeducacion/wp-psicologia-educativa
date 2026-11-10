<?php
/**
 * The screens of the application.
 *
 * @package Pse
 */

namespace Pse\PublicFront;

use Pse\Access\Access;
use Pse\Centre\CentreCatalogue;
use Pse\Centre\Zones;
use Pse\Domain\Stats;
use Pse\Domain\Vocabulary;
use Pse\PostType\Requests;

/**
 * Seis pantallas, una por pestaña. Cada una pone su alcance —el centro o las
 * zonas de quien mira— antes de consultar: el visitante solo aporta filtros.
 */
final class Views {

	/**
	 * The screen of a tab.
	 *
	 * @param string $tab Tab; '' when the user has none.
	 * @return array{tab:string, title:string, subtitle?:string, actions?:string, body:string}
	 */
	public static function screen( string $tab ): array {
		$screens = array(
			'new'    => 'request_form',
			'centre' => 'centre_list',
			'zone'   => 'zone_list',
			'all'    => 'all_list',
			'stats'  => 'stats',
			'zones'  => 'zones',
		);
		if ( ! isset( $screens[ $tab ], Screen::tabs()[ $tab ] ) ) {
			return array(
				'tab'   => '',
				'title' => 'Sin acceso',
				'body'  => '<div class="alert alert-warning">Su cuenta no tiene ningún perfil en este aplicativo, o no tiene un centro asignado. Si cree que es un error, contacte con quien lo administra.</div>',
			);
		}
		return (array) call_user_func( array( self::class, $screens[ $tab ] ) );
	}

	/**
	 * The label of a centre: «name · code».
	 *
	 * @param string $code Centre code.
	 * @param string $name Centre name; '' takes it from the catalogue.
	 * @return string
	 */
	private static function centre_label( string $code, string $name = '' ): string {
		if ( '' === $name ) {
			$name = (string) ( CentreCatalogue::find( $code )['name'] ?? '' );
		}
		return trim( $name . ' · ' . $code, ' ·' );
	}

	/**
	 * The badge of a state.
	 *
	 * @param string $status State slug.
	 * @return string
	 */
	private static function badge( string $status ): string {
		return '<span class="pse-state pse-state-' . esc_attr( $status ) . '">' . esc_html( Vocabulary::state_label( $status ) ) . '</span>';
	}

	/**
	 * The form a centre asks with: nothing to fill in but the subject.
	 *
	 * @return array<string, string>
	 */
	private static function request_form(): array {
		$code = Access::centre_code();
		$body = '<div class="alert alert-warning pse-warn"><strong>No escriba datos del alumnado.</strong> Aquí solo se indica el asunto. La información necesaria se la pedirá directamente el profesional que atienda la solicitud.</div>';

		$body .= '<form method="post" action="' . esc_url( home_url( '/' ) ) . '">' . Actions::fields( 'create' );
		$body .= '<div class="pse-card"><h2>Centro que solicita</h2><div class="row g-3">';
		$body .= '<div class="col-md-8"><label class="form-label" for="pse-centre">Centro</label><input class="form-control" id="pse-centre" value="' . esc_attr( self::centre_label( $code ) ) . '" readonly></div>';
		$body .= '<div class="col-md-4"><label class="form-label" for="pse-date">Fecha de la solicitud</label><input class="form-control" id="pse-date" value="' . esc_attr( wp_date( 'd/m/Y' ) ) . '" readonly></div>';
		$body .= '</div><p class="form-text mb-0">El centro y la fecha se rellenan solos a partir de la cuenta con la que ha entrado.</p></div>';

		$body .= '<div class="pse-card"><h2>Motivo de la solicitud</h2><label class="form-label" for="pse-subject">Asunto <span class="text-danger" aria-hidden="true">*</span></label>';
		$body .= '<select class="form-select" id="pse-subject" name="pse_subject" required><option value="">Elija un asunto de la lista…</option>';
		foreach ( Vocabulary::subjects() as $slug => $label ) {
			$body .= '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
		}
		$body .= '</select><p class="form-text mb-0">Lista cerrada: no hay ningún campo de texto libre.</p></div>';
		$body .= '<button class="btn btn-primary pse-btn" type="submit">Enviar solicitud</button></form>';

		return array(
			'tab'      => 'new',
			'title'    => Screen::TITLES['new'],
			'subtitle' => 'Indique solo el asunto. El profesional de psicología educativa de su zona se pondrá en contacto con el centro.',
			'body'     => $body,
		);
	}

	/**
	 * The filters the visitor chose, limited to those a screen offers.
	 *
	 * @param string[] $allowed Filter names.
	 * @return array<string, string>
	 */
	private static function filters( array $allowed ): array {
		$out = array();
		foreach ( $allowed as $name ) {
			$value = Screen::arg( 'pse_f_' . $name );
			if ( '' !== $value ) {
				$out[ $name ] = $value;
			}
		}
		return $out;
	}

	/**
	 * The state chips of a list, each with its count.
	 *
	 * @param string               $tab     Tab.
	 * @param array<string, mixed> $scope   Scope and filters, without the state.
	 * @param string               $current Current state filter.
	 * @return string
	 */
	private static function chips( string $tab, array $scope, string $current ): string {
		unset( $scope['status'], $scope['paged'] );
		$keep = array( Screen::ARG_TAB => $tab );
		foreach ( array( 'subject', 'zone', 'municipality', 'search' ) as $name ) {
			$keep[ 'pse_f_' . $name ] = (string) ( $scope[ $name ] ?? '' );
		}
		$states = array( '' => 'Todas' );
		foreach ( array_keys( Vocabulary::states() ) as $state ) {
			$states[ $state ] = Vocabulary::state_plural( $state );
		}
		$out = '<div class="pse-chips" role="group" aria-label="Filtrar por estado">';
		foreach ( $states as $state => $label ) {
			$count = Requests::query( $scope + array_filter( array( 'status' => $state ) ) + array( 'per_page' => 1 ) )->found_posts;
			$on    = $state === $current;
			$out  .= '<a class="pse-chip' . ( $on ? ' is-on' : '' ) . '"' . ( $on ? ' aria-current="true"' : '' ) . ' href="' . esc_url( Screen::url( $keep + array( 'pse_f_status' => $state ) ) ) . '">' . esc_html( $label ) . ' <b>' . esc_html( number_format_i18n( $count ) ) . '</b></a>';
		}
		return $out . '</div>';
	}

	/**
	 * A table of requests.
	 *
	 * @param \WP_Post[] $posts   Requests.
	 * @param string[]   $columns Columns to show, in order.
	 * @param string     $back    State filter to come back to after changing a state; only for the `change` column.
	 * @return string
	 */
	private static function table( array $posts, array $columns, string $back = '' ): string {
		$heads = array(
			'number'       => 'N.º',
			'date'         => 'Fecha',
			'centre'       => 'Centro',
			'municipality' => 'Municipio',
			'zone'         => 'Zona',
			'subject'      => 'Asunto',
			'status'       => 'Estado',
			'change'       => 'Cambiar estado',
		);
		if ( ! $posts ) {
			return '<p class="pse-empty">No hay solicitudes que mostrar.</p>';
		}
		$out = '<div class="table-responsive"><table class="table pse-table"><thead><tr>';
		foreach ( $columns as $column ) {
			$out .= '<th scope="col">' . esc_html( $heads[ $column ] ) . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		foreach ( $posts as $post ) {
			$row   = Requests::row( $post );
			$cells = array(
				'number'       => '<strong>' . esc_html( $row['number'] ) . '</strong>',
				'date'         => esc_html( mysql2date( 'd/m/Y', $post->post_date ) ),
				'centre'       => esc_html( $row['centre_name'] ) . ' <small class="text-secondary">' . esc_html( $row['centre_code'] ) . '</small>',
				'municipality' => esc_html( $row['municipality'] ),
				'zone'         => esc_html( $row['zone'] ),
				'subject'      => esc_html( Vocabulary::subject_label( $row['subject'] ) ),
				'status'       => self::badge( $row['status'] ),
				'change'       => in_array( 'change', $columns, true ) ? self::status_form( $post, $row, $back ) : '',
			);
			$out  .= '<tr>';
			foreach ( $columns as $column ) {
				$out .= '<td>' . $cells[ $column ] . '</td>';
			}
			$out .= '</tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/**
	 * The form that changes the state of a request.
	 *
	 * @param \WP_Post             $post Request.
	 * @param array<string, mixed> $row  Its fields.
	 * @param string               $back State filter to come back to.
	 * @return string
	 */
	private static function status_form( \WP_Post $post, array $row, string $back ): string {
		if ( ! Access::can_set_status( $post ) ) {
			return '';
		}
		$id   = 'pse-status-' . $row['id'];
		$out  = '<form class="pse-status-form" method="post" action="' . esc_url( home_url( '/' ) ) . '">' . Actions::fields( 'status' );
		$out .= '<input type="hidden" name="pse_id" value="' . esc_attr( (string) $row['id'] ) . '"><input type="hidden" name="pse_back" value="' . esc_attr( $back ) . '">';
		$out .= '<label class="visually-hidden" for="' . esc_attr( $id ) . '">' . esc_html( 'Estado de la solicitud ' . $row['number'] ) . '</label>';
		$out .= '<select class="form-select form-select-sm" id="' . esc_attr( $id ) . '" name="pse_status" data-pse-autosubmit>';
		foreach ( Vocabulary::states() as $state => $label ) {
			$out .= '<option value="' . esc_attr( $state ) . '"' . selected( $state, $row['status'], false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $out . '</select><button class="btn btn-sm btn-outline-primary" type="submit">Guardar</button></form>';
	}

	/**
	 * Previous and next page links.
	 *
	 * @param \WP_Query            $query Query.
	 * @param array<string, mixed> $args  Query args of the current screen.
	 * @return string
	 */
	private static function pagination( \WP_Query $query, array $args ): string {
		$pages = (int) $query->max_num_pages;
		$page  = max( 1, (int) $query->get( 'paged' ) );
		if ( $pages < 2 ) {
			return '';
		}
		$out = '<nav class="pse-pages" aria-label="Páginas"><span>' . esc_html( 'Página ' . $page . ' de ' . $pages ) . '</span>';
		if ( $page > 1 ) {
			$out .= '<a class="btn btn-sm btn-outline-secondary" href="' . esc_url( Screen::url( $args + array( 'pse_p' => $page - 1 ) ) ) . '">Anterior</a>';
		}
		if ( $page < $pages ) {
			$out .= '<a class="btn btn-sm btn-outline-secondary" href="' . esc_url( Screen::url( $args + array( 'pse_p' => $page + 1 ) ) ) . '">Siguiente</a>';
		}
		return $out . '</nav>';
	}

	/**
	 * The requests of the user's centre.
	 *
	 * @return array<string, string>
	 */
	private static function centre_list(): array {
		$scope = array( 'centre' => Access::centre_code() );
		$query = Requests::query( $scope + array( 'paged' => absint( Screen::arg( 'pse_p' ) ) ) );
		$body  = '';

		$created = get_post( absint( Screen::arg( 'pse_created' ) ) );
		if ( $created instanceof \WP_Post && Access::can_view( $created ) ) {
			$row   = Requests::row( $created );
			$told  = Zones::emails( $row['zone'] ) ? 'Se ha avisado por correo al profesional de su zona, que se pondrá en contacto con el centro.' : 'Queda registrada y a la vista de la coordinación.';
			$body .= '<div class="alert alert-success" role="status"><strong>' . esc_html( 'Solicitud n.º ' . $row['number'] . ' registrada.' ) . '</strong> ' . esc_html( $told ) . '</div>';
		}
		$body .= self::table( $query->posts, array( 'number', 'date', 'subject', 'status' ) );
		$body .= self::pagination( $query, array( Screen::ARG_TAB => 'centre' ) );
		$body .= '<p class="pse-note">Solo se muestran las solicitudes de su centro.</p>';
		return array(
			'tab'      => 'centre',
			'title'    => Screen::TITLES['centre'],
			'subtitle' => 'Un centro puede hacer tantas solicitudes como necesite. Aquí ve en qué estado está cada una.',
			'body'     => $body,
		);
	}

	/**
	 * The requests of the zones the user attends.
	 *
	 * @return array<string, string>
	 */
	private static function zone_list(): array {
		$zones   = Access::zones();
		$filters = self::filters( array( 'status' ) );
		$scope   = array( 'zones' => $zones ) + $filters;
		$query   = Requests::query( $scope + array( 'paged' => absint( Screen::arg( 'pse_p' ) ) ) );
		$status  = (string) ( $filters['status'] ?? '' );

		$body = '';
		if ( ! $zones ) {
			$body .= '<div class="alert alert-warning">Su correo no figura en ninguna zona todavía. La coordinación asigna las zonas en «Zonas».</div>';
		}
		$body .= self::chips( 'zone', $scope, $status );
		$body .= self::table( $query->posts, array( 'number', 'date', 'centre', 'municipality', 'subject', 'status', 'change' ), $status );
		$body .= self::pagination(
			$query,
			array(
				Screen::ARG_TAB => 'zone',
				'pse_f_status'  => $status,
			)
		);
		$body .= '<p class="pse-note">Solo ve las solicitudes de los centros de su zona. El resto de la información del caso se la solicita usted directamente al centro, fuera de este aplicativo.</p>';
		return array(
			'tab'      => 'zone',
			'title'    => Screen::TITLES['zone'],
			'subtitle' => $zones ? implode( ', ', $zones ) . '. Lo único que se cambia aquí es el estado.' : 'Lo único que se cambia aquí es el estado.',
			'body'     => $body,
		);
	}

	/**
	 * A filter select of the full list.
	 *
	 * @param string                $name    Filter name.
	 * @param string                $all     Label of the empty option.
	 * @param array<string, string> $options Value => label.
	 * @param string                $current Current value.
	 * @return string
	 */
	private static function select( string $name, string $all, array $options, string $current ): string {
		$out = '<select class="form-select" name="' . esc_attr( 'pse_f_' . $name ) . '" aria-label="' . esc_attr( $all ) . '"><option value="">' . esc_html( $all ) . '</option>';
		foreach ( $options as $value => $label ) {
			$out .= '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $value, $current, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $out . '</select>';
	}

	/**
	 * Every request, with filters and the export: the coordination's list.
	 *
	 * @return array<string, string>
	 */
	private static function all_list(): array {
		$filters = self::filters( array( 'status', 'subject', 'zone', 'municipality', 'search' ) );
		$query   = Requests::query( $filters + array( 'paged' => absint( Screen::arg( 'pse_p' ) ) ) );
		$args    = array( Screen::ARG_TAB => 'all' );
		foreach ( $filters as $name => $value ) {
			$args[ 'pse_f_' . $name ] = $value;
		}

		$municipalities = array_keys( Stats::of( Requests::rows() )['municipality'] );
		sort( $municipalities, SORT_LOCALE_STRING );
		$zones = Zones::all();

		$body  = '<form class="pse-filters" method="get" action="' . esc_url( home_url( '/' ) ) . '"><input type="hidden" name="' . esc_attr( Screen::ARG_TAB ) . '" value="all">';
		$body .= '<input type="hidden" name="pse_f_status" value="' . esc_attr( (string) ( $filters['status'] ?? '' ) ) . '">';
		$body .= '<input class="form-control" type="search" name="pse_f_search" value="' . esc_attr( (string) ( $filters['search'] ?? '' ) ) . '" placeholder="Centro, código o n.º de solicitud" aria-label="Buscar por centro, código o número de solicitud">';
		$body .= self::select( 'municipality', 'Todos los municipios', array_combine( $municipalities, $municipalities ), (string) ( $filters['municipality'] ?? '' ) );
		$body .= self::select( 'zone', 'Todas las zonas', array_combine( $zones, $zones ), (string) ( $filters['zone'] ?? '' ) );
		$body .= self::select( 'subject', 'Todos los asuntos', Vocabulary::subjects(), (string) ( $filters['subject'] ?? '' ) );
		$body .= '<button class="btn btn-outline-primary" type="submit">Filtrar</button></form>';

		$body .= self::chips( 'all', $filters, (string) ( $filters['status'] ?? '' ) );
		$body .= self::table( $query->posts, array( 'number', 'date', 'centre', 'municipality', 'zone', 'subject', 'status' ) );
		$body .= '<div class="pse-list-foot">' . self::pagination( $query, $args );
		$body .= '<a class="btn btn-primary pse-btn ms-auto" href="' . esc_url( Screen::url( $args + array( Screen::ARG_CSV => '1' ) ) ) . '">Exportar resultados como CSV</a></div>';
		$body .= '<p class="pse-note">La exportación incluye todas las páginas del listado filtrado.</p>';
		return array(
			'tab'      => 'all',
			'title'    => 'Solicitudes de intervención',
			'subtitle' => 'Todas las solicitudes de los centros, con su estado. Sin datos del alumnado.',
			'body'     => $body,
		);
	}

	/**
	 * The filtered list as CSV text.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows from `Requests::rows()`.
	 * @return string
	 */
	public static function csv( array $rows ): string {
		$lines = array( array( 'Número', 'Fecha', 'Código de centro', 'Centro', 'Municipio', 'Isla', 'Zona', 'Asunto', 'Estado' ) );
		foreach ( $rows as $row ) {
			$lines[] = array( $row['number'], $row['date'], $row['centre_code'], $row['centre_name'], $row['municipality'], $row['island'], $row['zone'], Vocabulary::subject_label( (string) $row['subject'] ), Vocabulary::state_label( (string) $row['status'] ) );
		}
		$out = '';
		foreach ( $lines as $line ) {
			$cells = array();
			foreach ( $line as $cell ) {
				// Una celda que empieza por «=», «+», «-» o «@» la ejecutaría la hoja de cálculo.
				$cell    = preg_replace( '/^([=+\-@])/', "'$1", (string) $cell );
				$cells[] = '"' . str_replace( '"', '""', (string) $cell ) . '"';
			}
			$out .= implode( ';', $cells ) . "\r\n";
		}
		return $out;
	}

	/**
	 * Send the filtered list as a CSV download.
	 *
	 * @return void
	 */
	public static function send_csv(): void {
		if ( ! current_user_can( Access::CAP_OVERSEE ) ) {
			return;
		}
		$csv = self::csv( Requests::rows( self::filters( array( 'status', 'subject', 'zone', 'municipality', 'search' ) ) ) );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="solicitudes-' . wp_date( 'Y-m-d' ) . '.csv"' );
		// El BOM es para que la hoja de cálculo lea las tildes.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV, no HTML; las celdas van entrecomilladas en csv().
		echo "\xEF\xBB\xBF" . $csv;
	}

	/**
	 * Horizontal bars of a count.
	 *
	 * @param array<string, int>    $counts Value => count.
	 * @param array<string, string> $labels Value => label; missing ones show the value.
	 * @return string
	 */
	private static function bars( array $counts, array $labels = array() ): string {
		if ( ! $counts ) {
			return '<p class="pse-empty">Sin datos todavía.</p>';
		}
		$max = max( 1, max( $counts ) );
		$out = '<dl class="pse-bars">';
		foreach ( $counts as $value => $count ) {
			$out .= '<dt>' . esc_html( $labels[ $value ] ?? (string) $value ) . '</dt><dd><span style="width:' . esc_attr( (string) round( 100 * $count / $max ) ) . '%"></span><b>' . esc_html( number_format_i18n( $count ) ) . '</b></dd>';
		}
		return $out . '</dl>';
	}

	/**
	 * How many requests, by each thing the coordination asks about.
	 *
	 * @return array<string, string>
	 */
	private static function stats(): array {
		$stats = Stats::of( Requests::rows() );

		$body = '<div class="pse-figures"><div class="pse-figure"><b>' . esc_html( number_format_i18n( $stats['total'] ) ) . '</b><span>Solicitudes</span></div>';
		foreach ( Vocabulary::states() as $state => $label ) {
			$body .= '<div class="pse-figure"><b>' . esc_html( number_format_i18n( $stats['status'][ $state ] ?? 0 ) ) . '</b><span>' . esc_html( Vocabulary::state_plural( $state ) ) . '</span></div>';
		}
		$body .= '</div><div class="row g-3">';

		$months = array();
		foreach ( $stats['month'] as $month => $count ) {
			$months[ $month ] = ucfirst( (string) wp_date( 'F Y', (int) strtotime( $month . '-01' ) ) );
		}
		$cards = array(
			'Por mes'       => self::bars( $stats['month'], $months ),
			'Por asunto'    => self::bars( $stats['subject'], Vocabulary::subjects() ),
			'Por zona'      => self::bars( $stats['zone'] ),
			'Por municipio' => self::bars( $stats['municipality'] ),
			'Por isla'      => self::bars( $stats['island'] ),
		);
		foreach ( $cards as $title => $card ) {
			$body .= '<div class="col-lg-6"><div class="pse-card h-100"><h2>' . esc_html( $title ) . '</h2>' . $card . '</div></div>';
		}
		$body .= '</div><p class="pse-note">Las cifras cuentan solicitudes, no alumnado: el aplicativo no guarda ningún dato que identifique a una persona atendida.</p>';
		return array(
			'tab'      => 'stats',
			'title'    => Screen::TITLES['stats'],
			'subtitle' => 'Número de solicitudes por mes, asunto, zona, municipio e isla.',
			'body'     => $body,
		);
	}

	/**
	 * The two lists that say where each request goes and who sees it.
	 *
	 * @return array<string, string>
	 */
	private static function zones(): array {
		$body  = '<form method="post" action="' . esc_url( home_url( '/' ) ) . '">' . Actions::fields( 'zones' ) . '<div class="row g-3">';
		$body .= '<div class="col-lg-6"><div class="pse-card h-100"><h2><label for="pse-centres">Centros de cada zona</label></h2><p class="form-text">Una línea por centro: código del centro, punto y coma y nombre de la zona.</p>';
		$body .= '<textarea class="form-control font-monospace" id="pse-centres" name="pse_centres" rows="14" placeholder="90000001; Zona 1">' . esc_textarea( Zones::centres_text() ) . '</textarea></div></div>';
		$body .= '<div class="col-lg-6"><div class="pse-card h-100"><h2><label for="pse-staff">Profesionales de cada zona</label></h2><p class="form-text">Una línea por profesional: nombre de la zona, punto y coma y su correo. Es el correo al que llega el aviso y el de la cuenta con la que entra.</p>';
		$body .= '<textarea class="form-control font-monospace" id="pse-staff" name="pse_staff" rows="14" placeholder="Zona 1; profesional@example.org">' . esc_textarea( Zones::staff_text() ) . '</textarea></div></div>';
		$body .= '</div><button class="btn btn-primary pse-btn mt-3" type="submit">Guardar las zonas</button></form>';
		$body .= '<p class="pse-note">Las líneas sin un código de ocho cifras o sin un correo válido se descartan al guardar. Cambiar el reparto no mueve las solicitudes ya registradas.</p>';
		return array(
			'tab'      => 'zones',
			'title'    => Screen::TITLES['zones'],
			'subtitle' => 'A qué zona va la solicitud de cada centro y quién la atiende.',
			'body'     => $body,
		);
	}
}
