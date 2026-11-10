# AGENTS.md — Solicitudes de intervención: instrucciones para agentes

Guía para cualquier agente (o persona) que toque este repositorio. Léela entera
antes de escribir código.

---

## Qué es este proyecto (y qué NO es)

Un aplicativo pequeño para WordPress con el que **los centros solicitan la
intervención del personal de psicología educativa de su zona**: la dirección
solicita eligiendo un asunto, quien atiende la zona recibe un aviso y marca el
estado, y la coordinación ve todas las solicitudes y sus estadísticas.

Qué pide cada perfil está en
[REQ-0001](docs/requisitos/REQ-0001-solicitudes-de-intervencion.md), y por dónde
va la puesta en marcha, en [PLAN-0001](docs/plan/PLAN-0001-puesta-en-marcha.md).

- **No es un plugin.** En producción solo se puede pegar código en Code
  Snippets ([ADR-0001](docs/adr/ADR-0001-el-repositorio-es-un-entorno-de-desarrollo-no-un-plugin.md)).
- **Hereda la plataforma de los aplicativos hermanos**: empaquetador,
  provisión, comprobaciones y flujos
  ([ADR-0002](docs/adr/ADR-0002-se-hereda-la-plataforma-de-los-aplicativos-hermanos.md)).
- **No guarda datos de personas atendidas**: ni nombre, ni identificador, ni
  texto libre (ADR-0003). Si una petición empuja hacia ahí, **se para y se
  pregunta**.

---

## Mapa del repositorio

```
src/Pse/                 El aplicativo, modular. Lo único que se edita a mano.
snippets/                Lo que se pega en Code Snippets: el bundle generado y el snippet de roles.
build/                   El empaquetador y el envoltorio de Plugin Check.
scripts/                 Provisión, sincronización de snippets, datos de demostración y comprobaciones.
scripts/mu-plugins/      Herramientas solo de desarrollo: cuentas de prueba y cambio de usuario.
assets/                  La hoja de estilos y el guion, que el bundle lleva dentro.
tests/unit/, tests/js/   PHPUnit contra WordPress y Vitest.
docs/                    ADR, SDD, requisitos y plan.
.local/                  Material privado de quien despliega. No se versiona ni se cita.
```

### Anatomía de `src/Pse/`

```
src/Pse/
├── load-order.php            El orden de carga: lo usan `bootstrap.php` y el empaquetador
├── bootstrap.php             Carga para desarrollo y tests
├── App.php                   Engancha cada módulo, una sola vez
├── Domain/Vocabulary.php     Listas cerradas: asuntos (filtro `pse_subjects`) y estados
├── Domain/Stats.php          Los recuentos de la pantalla de estadísticas
├── Centre/CentreCatalogue*.php  La copia local del catálogo de centros y su ajuste (ADR-0007)
├── Centre/Zones.php          Qué centros tiene cada zona y quién la atiende (ADR-0004)
├── Access/Access.php         La única guarda: capacidades, centro de la cuenta, quién ve y quién cambia (ADR-0005)
├── PostType/Requests.php     La solicitud: tipo de contenido, campos, alta, estado y consultas (ADR-0003)
├── Notifications/Mailer.php  El aviso a quien atiende la zona, sin el asunto (ADR-0006)
└── PublicFront/
    ├── Assets.php            La hoja y el guion, desde el bundle o desde `assets/`
    ├── Actions.php           Los tres formularios: solicitar, cambiar el estado y guardar las zonas
    ├── Views.php             Las seis pantallas, el CSV y las barras
    └── Screen.php            La portada es el aplicativo: rutas, pestañas, cabecera y documento
```

La portada del sitio **es** el aplicativo. Sin sesión, al acceso; con sesión,
la primera pestaña del perfil. Cada pantalla es `?pse_tab=`: `new`, `centre`,
`zone`, `all`, `stats` y `zones`. El documento no usa el tema ni llama a
`wp_head()`; la barra de WordPress sale solo para la administración y
`Screen::admin_bar()` la pinta a mano.

Ni una capa más. Si crees que hace falta otra, escribe la ADR primero.

---

## Qué comando ejecutar

| Situación | Comando |
|-----------|---------|
| Primera vez | `make install && make up` |
| Cambio en `src/Pse/` | `make bundle && make sync-snippets` (+ `make lint` / `make test`) |
| Cambio en un snippet suelto | `make sync-snippets` |
| Tras `make bundle`, con wp-env arrancado | `make snippet-check` (los snippets sobreviven al guardado de Code Snippets) |
| Un test concreto | `make test FILE=tests/unit/test-requests.php` o `make test FILTER=nombre_del_metodo` |
| Cambio en un guion de `assets/js/` | `make test-js` (Vitest y jsdom; la cobertura queda en `artifacts/coverage-js/`) |
| Ver la cobertura | `make coverage` (reinicia wp-env con Xdebug) |
| Entorno raro | `make clean` |
| Empezar de cero | `make destroy && make up` |
| Probar sin Docker | `make playground` |
| Antes de commit/PR | `make check` |
| Comprobar el código como lo revisaría WordPress.org | `make check-plugin` (errores **y avisos**) |
| Publicar una versión | `make release` (tras cerrar el bloque del CHANGELOG) |
| Llevar un snippet al sitio de destino | `npm run snippets -- push <id> --file snippets/… --dry-run` (sin `--yes` no escribe) |

Sitio local: <http://localhost:8958> (`admin` / `password`). Tests en el 8959.
Los puertos son otros que los de los aplicativos hermanos para poder tenerlos arrancados a la vez.
Cuentas de prueba (`password`): `direccion`, `direccion2`, `psicologia`, `psicologia2` y `coordinacion`.

---

## Cuando quien te dirige no viene de desarrollo

Parte del equipo trabaja en este repositorio **a través de un agente**, sin
experiencia previa en desarrollo. Para esa persona está
[`docs/desarrollo-para-empezar.md`](docs/desarrollo-para-empezar.md): instalación
en macOS y en Windows, `git pull` antes de empezar, `make install` / `make up`,
el ciclo rama → `make check` → `gh pr create` → **revisión de otra persona**, y
qué mirar cuando algo falla.

Si es tu caso, dos consecuencias para ti:

- **Explica en castellano llano qué has tocado y por qué**, y qué tiene que ver
  en pantalla para comprobarlo. Un resumen que solo entiende quien ya sabe no
  sirve de nada.
- **No propongas ni ejecutes nada que se salte la revisión**: ni `push` a
  `main`, ni fusionar el PR, ni desactivar una comprobación para que `make
  check` pase. Si `make check` está en rojo, se arregla; no se rodea.

---

## Añadir código al aplicativo

1. El código va en `src/Pse/`. Un fichero nuevo se añade a
   `src/Pse/load-order.php`, o el empaquetador falla. Clases `final` con
   métodos estáticos; sin `declare(strict_types=1)` y sin interpolación
   `{$x}` en cadenas.
2. `make bundle && make sync-snippets` para verlo en <http://localhost:8958>.
3. **Permisos:** todo lo que se pinta o se cambia pasa por `Access\Access`.
   Esconder una fila o un botón no protege nada. El centro de quien solicita
   sale de su cuenta, nunca del formulario.
4. **Formularios:** por POST, con el nonce y el campo de `Actions::fields()`,
   y su permiso comprobado en `Actions`, no en la pantalla. POST → redirección
   → GET.
5. **Un dato nuevo en la solicitud** es una clave en `Requests::keys()`, y
   antes una pregunta: ¿identifica a alguien? Si puede, no entra (ADR-0003).
6. **Un asunto nuevo** no se añade aquí: es configuración de quien despliega,
   con el filtro `pse_subjects`.
7. Tests en `tests/unit/` para lo de PHP y en `tests/js/` para el guion.

---

## Convenciones

- **Idiomas:** ver [más abajo](#idiomas); en corto, identificadores en
  **inglés** y todo lo que lee una persona en **castellano**.
- **Prefijo:** `pse_` / `PSE_`; namespace `Pse`; snippets `PSE — …`.
- **Estilo:** WPCS (`wp-coding-standards/wpcs`, `<rule ref="WordPress">`),
  tabuladores, Yoda conditions, escape de salida. `make lint` / `make fix`.
  Cubre `src/`, `snippets/`, `scripts/`, `tests/` **y `build/`**; en `build/` se
  apagan solo las reglas que exigen `WP_Filesystem`, porque esos guiones corren
  desde la línea de órdenes y ahí no hay WordPress.
- **`declare(strict_types=1)` está prohibido** (ver arriba).
- **Aplicativo:** editar solo `src/Pse/`; no editar a mano
  `snippets/*.bundle.php` (regenerar con `make bundle`).
- **Librerías de terceros:** en producción desde **jsDelivr con SRI**
  (`cdn.jsdelivr.net/npm/<paquete>@<versión>/…`, con `integrity` y
  `crossorigin`); en desarrollo y en los tests desde **`node_modules`**, que el
  mu-plugin reescribe. Van en `package.json` con la versión **exacta**, y esa
  versión es la misma en los tres sitios: `package.json`, la URL y el `$ver` del
  encolado. **Nunca se inlinean en el bundle.** El CDN **no** es un problema en
  este proyecto: es el camino
  (ADR-0002).
- **Workflows:** `make check` compila el JavaScript de `github-script`, que va
  dentro de una cadena YAML y que si no nadie mira hasta que el job corre. **No**
  valida las expresiones `${{ … }}`: un `if` que lea un contexto inexistente
  —`secrets`, por ejemplo— es YAML válido, pasa aquí, y GitHub rechaza el fichero
  entero al recibirlo: rojo a los cero segundos y sin un job que abrir. Eso solo
  lo dice GitHub.
- **`main` está protegida** por una regla de rama de GitHub
  (ADR-0002):
  solo se mezcla por PR con una aprobación y con los checks `lint` y `test` de
  `ci.yml` en verde. Si esos jobs cambian de nombre, hay que cambiar también la
  regla, o ningún PR se podrá mezclar. Por eso mismo `ci.yml` corre en todos
  los PR, también en los de solo documentación.
- **Scripts de `scripts/`:** idempotentes, **sin `WP_CLI`** (corren también
  bajo Playground), raíz con `dirname( __DIR__ )`, y lanzan `RuntimeException`
  cuando algo falla, para que `make provision` se caiga en vez de seguir a
  medias.
- **Listas cerradas en código, no en taxonomía:** los estados y los asuntos
  por defecto viven en `Domain/Vocabulary.php`.

### Idiomas

Qué va en cada idioma. Si algo no está en esta tabla, va en castellano: es lo
que lee una persona.

| Qué | Idioma |
|--|--|
| Identificadores que se llaman: clases, métodos, funciones, nombres de fichero | **inglés** |
| Variables y parámetros locales | **inglés** |
| Slugs, claves de meta y sus valores, roles, opciones y hooks | **inglés** |
| Nombres de test | **inglés** (son funciones) |
| Docblocks (`/** … */`) | **inglés** |
| Comentarios sueltos (`// …`) | **castellano** si explican una regla del dominio o una decisión; inglés si son puramente técnicos |
| Cadenas de la interfaz, etiquetas, mensajes de error y avisos | **castellano** |
| ADR, SDD, requisitos, planes, `CHANGELOG`, `README`, este fichero | **castellano** |
| Mensajes de commit, títulos y descripciones de PR | **castellano** |
| `Makefile`: `help` y comentarios | **castellano** |
| URL de las páginas | **castellano** |

**La raya está entre lo que lee una máquina y lo que lee una persona**: en
inglés el slug del tipo de contenido, las claves de
meta y sus valores (`pse_status`, `in_progress`), los roles
(`pse_psychologist`), las opciones, los hooks y las clases que los reflejan. En
castellano las cadenas, las etiquetas, los rótulos de rol y las URL de las
páginas, que se comparten y se teclean.

Los nombres de las zonas son **datos**, no identificadores: van como los
escriba quien coordina.

**Por qué los comentarios del dominio van en castellano:** quien los lee
—persona o agente— tiene que enlazarlos con el requisito y con la cadena que
sale en pantalla, y ambos están en castellano. Los docblocks no: describen la
API, sus etiquetas ya son inglesas y las revisa WPCS.

---

## Política ADR/SDD

Toda decisión no trivial de IA se registra en `docs/adr/` o `docs/sdd/` con
`ai_assistance` (tool, model). Plantillas e índices en esos directorios; el ID
es `max(existentes) + 1`, con ceros a la izquierda, y nunca se reutiliza.

Una ADR aceptada **no se reescribe**: se le añade una `## Adenda — AAAA-MM-DD`,
o se crea una ADR nueva con `supersedes` / `superseded_by` y se actualiza
`registro.md`.

La raya está en **publicado**. Mientras una ADR no haya salido del repositorio
—sin commit, sin enlace compartido— no es todavía la dirección de nadie y se
corrige en su propio texto; ahí es donde se consolida y se renumera. En cuanto
hay commit, el identificador y el texto se congelan y toda corrección es adenda
o ADR nueva.

Evidencia antes que preferencia: cada afirmación técnica lleva su fuente
verificable (ruta del repo con línea, documentación oficial, experimento
reproducible, PR o ADR previa). Los contras se escriben con la misma dureza
que los pros.

---

## Este repositorio se publica en abierto

Va a ser **software libre**, así que **nada de lo que se versiona puede decir
de quién es el despliegue, dónde está, qué infraestructura usa ni cómo era por
dentro el sistema que se sustituye**
(ADR-0002).

Lo que **no** se escribe en `src/`, `docs/`, `scripts/`, `tests/`, `README.md`
ni en este fichero:

| Qué | Dónde va |
|---|---|
| URL de analítica, de avisos de cookies o de servicios de una organización, y sus identificadores | Configuración: el filtro `pse_chrome`, vacío por defecto |
| El nombre, el escudo, el rótulo o los enlaces legales de una organización | Lo mismo |
| Cómo llama una instalación a sus perfiles, qué asuntos ofrece, qué rol tienen sus direcciones y dónde guarda el código del centro | Configuración: los filtros `pse_role_labels`, `pse_subjects`, `pse_role_caps` y `pse_centre_code_meta_key`, en un snippet de despliegue que no se versiona |
| El nombre de la red, del sitio o del subsitio de destino | El `.env`, que no se sube. `.env.dist` los trae **vacíos** |
| Cómo era la instalación anterior por dentro: sus formularios, vistas, campos, fragmentos de código y sus números | `.local/`, que está en el `.gitignore` |
| Cuánta gente hay, cuántas solicitudes llegan, qué zonas existen y quién las atiende | `.local/`, o se escribe la decisión sin la cifra |
| Centros, municipios o zonas reales en datos de demostración o en tests | En ningún sitio: se inventan |
| El nombre de otro repositorio del equipo, sus rutas, sus opciones o sus prefijos | `.local/`. **Son privados**: citarlos publica código de otro |
| Rutas absolutas de un portátil | En ningún sitio |

**La razón de una decisión se queda; el dato que la sostenía puede irse.** Se
puede escribir por qué se eligió algo sin publicar el inventario de una
instalación ajena. Vale igual para el argumento de autoridad: «se hace así en
tal repositorio» no es una razón, es una cita —**y además privada**—; lo que
convence es el motivo, escrito entero aquí. Cuando una ADR se apoye en una medición, se dice que está
medida y se deja el material en `.local/`.

**Se comprueba**, no se confía: `make check-public` recorre lo que git
versionaría y falla con el motivo escrito. Las reglas genéricas están en
`scripts/check-public.mjs`; las que nombran a una organización —su marca, sus
sitios, sus programas, sus repositorios— van en `.local/check-public.rules.json`
(o en la variable `PSE_PUBLIC_RULES`), porque versionarlas publicaría justo lo
que buscan. Sin ese fichero solo se aplican las genéricas, y el script lo avisa.

---

## Reglas duras

- **No convertir el repo en plugin de producción.** Ni cabecera, ni
  `readme.txt`, ni hooks de activación, ni rutas de plugin.
- **Código del aplicativo en `src/Pse/`** → bundle → Code Snippets. No dejar
  cambios solo en el admin de Snippets.
- **Nada de `declare(strict_types=1)`.**
- **No tocar nada del WordPress de producción.** Este repositorio es de solo
  escritura hacia dentro: lo que se escribe, se escribe aquí.
- **No versionar ni citar `.local/`**: es el material privado de quien despliega.
- **Nada de ninguna organización concreta en lo que se versiona** (ADR-0002):
  ni marca, ni infraestructura, ni destino de despliegue. `make check-public`.
- **Aquí no hay datos de personas atendidas** (ADR-0003): ni campos de texto
  libre, ni identificadores, ni adjuntos. Si una petición empuja hacia ahí, se
  para y se pregunta.
- **Los datos de demostración y de los tests son inventados**: centros,
  municipios, zonas y correos de `example.org`.
- El mu-plugin de `scripts/mu-plugins/` es solo desarrollo.
- Diffs pequeños y enfocados. No inventar ficheros que nadie ha pedido.

---

## Definición de hecho

1. `make lint` sin errores.
2. `make test` sin fallos, y `make check-plugin` sin errores de Plugin Check.
3. `make bundle` genera un bundle que pasa `php -l`, y `make snippet-check`
   pasa con el entorno arrancado.
4. `make up` / provisión OK (<http://localhost:8958>, `admin` / `password`).
5. Docs actualizadas si aplica; ADR/SDD con `ai_assistance` si hubo decisión.

---

## Referencia de herramientas

- `make help` y el `Makefile` son la referencia de targets.
- wp-env: **Code Snippets + WPFront User Role Editor + SQL Buddy**
  (ningún gestor de formularios). Puertos `8958` / `8959`.
- El destino en producción **puede ser un subsitio de un multisitio**, a
  diferencia del entorno local, que es un sitio único. Cualquier cosa que
  construya el nombre de una tabla o invoque `wp eval-file` sin `--url=` merece
  una mirada antes de darla por buena.

---

## Skills (`.agents/skills/`)

Viven en:

- `.agents/skills/` — GitHub Copilot, Codex, Cursor y el resto de agentes que
  comparten esa ruta
- `.claude/skills/` — Claude Code

Las copias canónicas viven en `.agents/skills/`, y `.claude/skills/` lleva una
**copia** de cada una. Copia y no enlace porque **en Windows los enlaces
simbólicos no funcionan** sin habilitarlos a mano, y `gh skill` tampoco enlaza.
Las dos carpetas se igualan con `make skills-sync`, y `make check` falla si
difieren.

**Las que hay hoy** —todas de terceros y verbatim, con su índice y su origen en
[`.agents/skills/README.md`](.agents/skills/README.md)—:

| Para | Skills |
|---|---|
| Seguridad | `security-audit`, `wp-plugin-security`, `github-actions-hardening` |
| WordPress | `wp-plugin-development`, `wp-performance`, `wp-wpcli-and-ops`, `wp-project-triage`, `wp-playground`, `blueprint` |
| Pruebas | `playwright-cli` |
| Propia | `changelog` — el bloque de versión y `make release` |

Skills propias: créalas en `.agents/skills/<nombre>/` y ejecuta
`make skills-sync`. Las de terceros, instálalas solo para Copilot y sincroniza
igual:

```bash
gh skill add WordPress/agent-skills wp-performance --agent github-copilot
gh skill update --all
make skills-sync
```

`gh skill` mete la procedencia en el frontmatter del `SKILL.md`. No instales
`--agent claude-code` ni `--agent grok` en este repo: escribirían en
`.claude/skills/` por su cuenta y la canónica dejaría de ser la de `.agents/`. Las de terceros van
**verbatim**: no las reformatees, divergir de upstream complica
`gh skill update`.

### Compatibilidad de skills

Las skills genéricas de WordPress orientan la implementación, pero este
repositorio **no es un plugin distribuible**. La arquitectura del repositorio
siempre prevalece sobre las recomendaciones de una skill:

- No crear un fichero bootstrap de plugin ni cabeceras de plugin ni
  `readme.txt`.
- El código del aplicativo pertenece a `src/Pse/`.
- Los artefactos de producción son Code Snippets generados con `make bundle`.
- No introducir hooks de activación/desactivación de plugin.
- No asumir rutas o URL relativas a un plugin (`plugin_dir_path()`,
  `plugin_dir_url()`, `plugins_url()`, `register_activation_hook()`).

`wp-plugin-development` se usa para **patrones de desarrollo WordPress**
(hooks, CPT, admin, shortcodes, capabilities, enqueue), no para packaging ni
estructura de plugin.
