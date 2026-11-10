<?php
/**
 * Snippet Name: PSE — Roles
 * Description: Registra los tres roles del aplicativo de solicitudes de intervención —dirección de centro, psicología educativa y coordinación— y la capacidad de administrarlo. Las capacidades propias las reparte el aplicativo.
 * Scope: global
 * Priority: 5
 *
 * @package Pse
 */

// Code Snippets evalúa esto, no lo incluye como fichero; la guarda va igual
// por si el código acaba algún día en un fichero servido.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'pse_role_definitions' ) ) {
	/**
	 * Role labels and the capabilities this snippet owns.
	 *
	 * Solo las capacidades generales. Las propias del aplicativo
	 * (`pse_request_intervention`, …) las reparte
	 * `Pse\Access\Access::grant_caps_to_roles()` en `init` 11, para no tener
	 * el mismo mapa escrito en dos sitios (ADR-0005).
	 *
	 * Los rótulos son genéricos a propósito: cómo se llama cada perfil en el
	 * organigrama de quien despliega es configuración y no se versiona
	 * (ADR-0002). El filtro `pse_role_labels` los cambia, uno a uno y por su
	 * slug; las capacidades no salen de aquí.
	 *
	 * @return array<string, array{label:string, caps:string[]}>
	 */
	function pse_role_definitions(): array {
		$roles = array(
			'pse_school_head'  => array(
				'label' => 'Dirección de centro',
				'caps'  => array( 'read' ),
			),
			'pse_psychologist' => array(
				'label' => 'Psicología educativa',
				'caps'  => array( 'read' ),
			),
			'pse_coordinator'  => array(
				'label' => 'Coordinación',
				'caps'  => array( 'read' ),
			),
		);

		/**
		 * Filters the label of each product role.
		 *
		 * @param array<string, string> $labels Role slug => label. Vacío por defecto.
		 */
		$labels = (array) apply_filters( 'pse_role_labels', array() );
		foreach ( $roles as $slug => $def ) {
			$label = isset( $labels[ $slug ] ) ? trim( (string) $labels[ $slug ] ) : '';
			if ( '' !== $label ) {
				$roles[ $slug ]['label'] = $label;
			}
		}
		return $roles;
	}
}

if ( ! function_exists( 'pse_role_slugs' ) ) {
	/**
	 * Return the product role slugs.
	 *
	 * @return string[]
	 */
	function pse_role_slugs(): array {
		return array_keys( pse_role_definitions() );
	}
}

if ( ! function_exists( 'pse_revoked_role_caps' ) ) {
	/**
	 * Capabilities this snippet granted once and no longer does, per role.
	 *
	 * El reparto es aditivo: lo que un rol deja de tener se dice aquí, o un
	 * sitio ya desplegado lo conservaría. Es lo único que se quita.
	 *
	 * @return array<string, string[]>
	 */
	function pse_revoked_role_caps(): array {
		return array();
	}
}

if ( ! function_exists( 'pse_forbidden_role_caps' ) ) {
	/**
	 * Capabilities no product role may ever hold.
	 *
	 * Administrar el aplicativo —sus ajustes— es de la
	 * administración, y `unfiltered_html` no se concede a nadie.
	 *
	 * @return string[]
	 */
	function pse_forbidden_role_caps(): array {
		return array( 'pse_manage_app', 'unfiltered_html' );
	}
}

if ( ! function_exists( 'pse_register_roles' ) ) {
	/**
	 * Idempotently register product roles and grant the app cap to administrators.
	 *
	 * Aditiva: crea el rol si falta y añade la capacidad si falta. Solo quita
	 * lo de {@see pse_revoked_role_caps()}; lo demás que se conceda a mano en el
	 * editor de roles sigue ahí en la siguiente carga. El rótulo sí es el del código: si cambia, el rol ya creado lo
	 * coge, o un sitio desplegado seguiría con el nombre viejo.
	 *
	 * @return void
	 */
	function pse_register_roles(): void {
		foreach ( pse_role_definitions() as $slug => $def ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				add_role( $slug, $def['label'], array() );
				$role = get_role( $slug );
			}
			$roles = wp_roles();
			if ( isset( $roles->roles[ $slug ] ) && $def['label'] !== $roles->roles[ $slug ]['name'] ) {
				$roles->roles[ $slug ]['name'] = $def['label'];
				$roles->role_names[ $slug ]    = $def['label'];
				if ( $roles->use_db ) {
					update_option( $roles->role_key, $roles->roles );
				}
			}
			foreach ( $role ? $def['caps'] : array() as $cap ) {
				if ( ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
			foreach ( $role ? ( pse_revoked_role_caps()[ $slug ] ?? array() ) : array() as $cap ) {
				if ( isset( $role->capabilities[ $cap ] ) ) {
					$role->remove_cap( $cap );
				}
			}
		}

		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( 'pse_manage_app' ) ) {
			$admin->add_cap( 'pse_manage_app' );
		}
	}
}

if ( ! function_exists( 'pse_roles_status' ) ) {
	/**
	 * Forbidden capabilities someone granted by hand to a product role.
	 *
	 * Este snippet no quita capacidades salvo las que ya no concede, así que
	 * avisa y quien administra decide.
	 *
	 * @return array<string, string[]> role => forbidden caps it holds.
	 */
	function pse_roles_status(): array {
		$out = array();
		foreach ( pse_role_slugs() as $slug ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			$bad = array_values( array_filter( pse_forbidden_role_caps(), array( $role, 'has_cap' ) ) );
			if ( $bad ) {
				$out[ $slug ] = $bad;
			}
		}
		return $out;
	}
}

add_action( 'init', 'pse_register_roles', 5 );
