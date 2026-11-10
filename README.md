# Solicitudes de intervención de psicología educativa

[![CI](https://github.com/ateeducacion/wp-psicologia-educativa/actions/workflows/ci.yml/badge.svg)](https://github.com/ateeducacion/wp-psicologia-educativa/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/ateeducacion/wp-psicologia-educativa/graph/badge.svg)](https://codecov.io/gh/ateeducacion/wp-psicologia-educativa)
[![Probar en WordPress Playground](https://img.shields.io/badge/Probar%20en%20WordPress%20Playground-3858E9?logo=wordpress&logoColor=white)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/ateeducacion/wp-psicologia-educativa/main/blueprint.json)

Un aplicativo para WordPress con el que **los centros educativos solicitan la
intervención del personal de psicología educativa de su zona**. La dirección
del centro solicita eligiendo un asunto; quien atiende la zona recibe un aviso
y marca el estado; la coordinación ve todas las solicitudes, las cuenta y las
exporta.

**No guarda datos de las personas atendidas**: ni nombre, ni identificador, ni
descripción del caso. Una solicitud es un centro, una fecha, un asunto de una
lista cerrada y un estado
([ADR-0003](docs/adr/ADR-0003-la-solicitud-no-guarda-datos-de-personas-atendidas.md)).

No es un plugin: en producción se pega en
[Code Snippets](https://wordpress.org/plugins/code-snippets/), y este
repositorio es su entorno de desarrollo
([ADR-0001](docs/adr/ADR-0001-el-repositorio-es-un-entorno-de-desarrollo-no-un-plugin.md)).

## Probarlo

Sin instalar nada, con el botón «Probar en WordPress Playground» de arriba: se
abre con datos inventados. En local, con Docker y Node 22:

```bash
make install
make up        # http://localhost:8958  (admin / password)
```

O sin Docker: `make playground`.

## Usuarios de prueba

Todos con la contraseña `password`. Los centros, los municipios y las zonas
son inventados.

| Usuario | Perfil | Qué hace |
|---|---|---|
| `direccion`, `direccion2` | Dirección de centro | Solicitar una intervención y ver las de su centro |
| `psicologia`, `psicologia2` | Psicología educativa | Ver las solicitudes de sus zonas y cambiar su estado |
| `coordinacion` | Coordinación | Ver todas, filtrarlas, exportarlas, ver las estadísticas y repartir las zonas |
| `admin` | Administración | Todo lo anterior, los ajustes y el escritorio |

## Desplegarlo

Se pegan en Code Snippets `snippets/roles-and-profiles.php` y
`snippets/pse-psicologia-app.bundle.php`, en un sitio para él solo: el
aplicativo ocupa la portada. Lo propio de cada despliegue va en un snippet
aparte, que no se versiona:

| Filtro | Para qué |
|---|---|
| `pse_chrome` | Logotipo, nombre de la organización, pie y analítica |
| `pse_role_caps` | Dar las capacidades a roles que el sitio ya tiene |
| `pse_centre_code_meta_key` | La meta de usuario con el código del centro |
| `pse_subjects` | La lista de asuntos |
| `pse_role_labels` | Cómo se llama cada perfil |

El catálogo de centros se configura en «Ajustes → Centros educativos», y las
zonas, en la pestaña «Zonas» del aplicativo.

## Desarrollo

```bash
make check     # lint, phpmd, check-public, check-provision, check-skills, test-js y tests
make help      # el resto de órdenes
```

- [AGENTS.md](AGENTS.md): cómo está hecho y las reglas para tocarlo.
- [Para empezar](docs/desarrollo-para-empezar.md), si es tu primera vez.
- [Requisitos](docs/requisitos/REQ-0001-solicitudes-de-intervencion.md) y
  [plan de puesta en marcha](docs/plan/PLAN-0001-puesta-en-marcha.md).
- [Decisiones](docs/adr/registro.md).

## Licencia

[GPL-3.0](LICENSE).
