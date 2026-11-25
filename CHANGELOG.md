# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y versionado [SemVer](https://semver.org/lang/es/).

La versión de este fichero es la **única** fuente de verdad: `make bundle` la
lee de la cabecera superior y la escribe en el `@version` del bundle.
`make release` crea el tag y la release en GitHub; el despliegue de los snippets
en el sitio de destino se hace después.

## [0.2.1] — 2026-11-25

### Cambiado

- Con la redacción de la coordinación: el estado «No realizada» pasa a llamarse **«Baja»**, y las opciones de la autorización, a «Se cuenta con la autorización familiar firmada (alumnado menor de edad)» y «No requiere autorización familiar (alumnado mayor de edad)». Las solicitudes ya registradas conservan su valor

## [0.2.0] — 2026-11-25

### Añadido

- **Tipo de intervención**: al solicitar, el centro indica si la intervención es individual o grupal. Sale en los listados, se puede filtrar y se cuenta en «Estadísticas»
- **Autorización**: al solicitar, el centro declara si tiene la autorización de la familia (menor de edad) o si no la precisa (mayor de edad), junto a un texto de declaración responsable. Sin autorización no se puede solicitar; el documento lo guarda el centro y no se sube al aplicativo
- **Estado «No realizada»**, para la intervención que no se llega a hacer, por ejemplo porque la familia retira la autorización
- El CSV exportado lleva dos columnas más al final: tipo y autorización

### Cambiado

- **Asuntos por defecto**: «Violencia de género» e «Identidad de género» pasan a ser dos asuntos, e «Intervención grupal o preventiva» deja de ser un asunto, porque ahora es el tipo. El orden de la lista es el de prioridad
- Las solicitudes anteriores a esta versión muestran «—» en tipo y autorización

### Pendiente antes de desplegar

- La lista definitiva de asuntos de la coordinación, en el filtro `pse_subjects` del snippet de despliegue
- El texto legal de la declaración de autorización, en el filtro `pse_authorization_statement`, si se quiere cambiar el que trae por defecto
- Borrar las solicitudes de prueba del sitio: las que usan los asuntos antiguos mostrarían el identificador del asunto en vez de su nombre

## [0.1.2] — 2026-11-16

### Cambiado

- **Quien está en la lista de «Zonas» atiende su zona aunque no tenga el perfil de psicología.** Una baja se resuelve desde «Zonas», cambiando el correo de quien está de baja por el de quien la sustituye, sin pedir a la administración que le dé el perfil

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
