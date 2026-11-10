# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y versionado [SemVer](https://semver.org/lang/es/).

La versión de este fichero es la **única** fuente de verdad: `make bundle` la
lee de la cabecera superior y la escribe en el `@version` del bundle.
`make release` crea el tag y la release en GitHub; el despliegue de los snippets
en el sitio de destino se hace después.

## [0.1.1] — 2026-11-10

### Cambiado

- **La zona de cada centro sale del catálogo de centros**, de su equipo de orientación de zona, en cuanto la copia del catálogo lo trae. La coordinación ya solo tiene que decir quién atiende cada zona; la lista de centros queda para los que el catálogo deja sin zona o la tienen distinta
- En «Zonas», una zona se puede escribir por su nombre o por el código de su equipo, y la pantalla enseña las zonas que conoce el catálogo

## [0.1.0] — 2026-11-10

### Añadido

- **Solicitar una intervención**: la dirección del centro entra, el aplicativo pone su centro y la fecha, y solo elige un asunto de una lista cerrada. No hay ningún campo de texto libre: la solicitud no guarda datos de personas atendidas
- **Solicitudes de mi centro**: cada solicitud con su número, su asunto y su estado
- **Solicitudes de mi zona**: quien atiende una zona ve las de sus centros y cambia su estado —pendiente, en atención o atendida—
- **Aviso por correo** a quien atiende la zona, con el número y el centro pero sin el asunto
- **Solicitudes**: la coordinación las ve todas, las filtra por estado, asunto, zona y municipio, y las exporta en CSV
- **Estadísticas**: cuántas solicitudes por mes, asunto, zona, municipio e isla
- **Zonas**: la coordinación pega qué centros tiene cada zona y el correo de quien la atiende
- Tres roles —dirección de centro, psicología educativa y coordinación—, con filtros para que quien despliega use los roles y la meta de centro que ya tenga
- La plataforma de los aplicativos hermanos: empaquetado para Code Snippets, entorno con `wp-env`, vista previa en WordPress Playground, datos de demostración inventados y comprobaciones
