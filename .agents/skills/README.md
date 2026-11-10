# Skills de agentes

Las copias canónicas de las skills viven aquí, y `.claude/skills/` lleva una
**copia** de cada una, no un enlace.

Copia y no enlace por una razón concreta: **en Windows los enlaces simbólicos no
funcionan** salvo que se habiliten a mano —modo desarrollador o privilegio de
creación—, y parte del equipo trabaja ahí. `gh skill` tampoco enlaza: copia. Así
el árbol es el mismo en macOS, Linux y Windows, y quien clone el repositorio ve
las skills sin configurar nada.

El precio es que cada skill está dos veces y las dos hay que mantenerlas a la
vez. De eso se encarga `make skills-sync`, que copia `.agents/skills/` sobre
`.claude/skills/` y falla si alguna difiere —lo comprueba `make check`, así que
una copia desincronizada no llega a `main`—.

La regla al elegirlas: **una skill copiada y no usada envejece en silencio y
acaba dando instrucciones falsas**, así que aquí solo están las que este
repositorio va a abrir de verdad. Y todas son **de terceros y verbatim**: las
hechas a medida de otro aplicativo no valen —traen su dominio dentro— y
publicarlas sería publicar el suyo
([ADR-0030](../../docs/adr/ADR-0030-el-repositorio-se-publica-sin-nada-de-nadie.md)).

## Añadir una skill propia

```bash
mkdir -p .agents/skills/<nombre>
$EDITOR .agents/skills/<nombre>/SKILL.md
make skills-sync
```

## Añadir una de terceros

Se instalan **solo** para Copilot y se copian después. Así `gh skill update`
sigue funcionando y la procedencia queda en el frontmatter del `SKILL.md`
(`metadata.github-repo`, `github-path`, `github-tree-sha`).

```bash
gh skill add WordPress/agent-skills wp-plugin-development --agent github-copilot
gh skill update --all
make skills-sync
```

No instales `--agent claude-code` ni `--agent grok`: escribiría en
`.claude/skills/` por su cuenta y la canónica dejaría de ser la de
`.agents/skills/`.

Las de terceros van **verbatim**. No las reformatees: divergir de upstream
complica `gh skill update`. El workflow `.github/workflows/update-agent-skills.yml`
las actualiza y abre un PR cada lunes.

## Las que hay

### Seguridad

| Skill | Léela antes de | Origen |
|---|---|---|
| `security-audit` | Buscar vulnerabilidades explotables en el código: límites de confianza, entrada no fiable, escalada | [`cloudflare/security-audit-skill`](https://github.com/cloudflare/security-audit-skill) |
| `wp-plugin-security` | Entrada, salida, nonces, capacidades, formularios, ficheros | [`fernandotellado/ai-skills`](https://github.com/fernandotellado/ai-skills) |
| `github-actions-hardening` | Tocar `.github/workflows/*.yml`: permisos del `GITHUB_TOKEN`, acciones sin fijar, inyección por `pull_request_target` | [`github/awesome-copilot`](https://github.com/github/awesome-copilot) |

Las tres se leen juntas con la política de permisos de la casa: toda decisión de
«puede o no puede» pasa por `Access/EventAccess`, y esconder algo con
`pre_get_posts` **no es protegerlo** —lo que protege es `map_meta_cap`—.

### WordPress

| Skill | Léela antes de | Origen |
|---|---|---|
| `wp-plugin-development` | Hooks, CPT, admin, shortcodes, capacidades, enqueue — **no** empaquetado | [`WordPress/agent-skills`](https://github.com/WordPress/agent-skills) |
| `wp-performance` | Perfilar consultas, listados y frontal | ídem |
| `wp-wpcli-and-ops` | Escribir o tocar algo de `scripts/`, que corre con `wp eval-file` | ídem |
| `wp-project-triage` | Inspeccionar el repositorio de arriba abajo antes de meterse en algo grande | ídem |
| `blueprint` / `wp-playground` | Editar `blueprint*.json` o usar `make playground` | ídem |

### Pruebas

| Skill | Léela antes de | Origen |
|---|---|---|
| `playwright-cli` | Tocar `tests/browser/` o `make test-browser` | [`microsoft/playwright-cli`](https://github.com/microsoft/playwright-cli) |

### Propias

| Skill | Léela antes de |
|---|---|
| `changelog` | Abrir un bloque de versión en `CHANGELOG.md` y publicar con `make release` |

Esta sí es de la casa, así que se mantiene aquí y no se actualiza desde ningún
upstream. Está adaptada de otro aplicativo del equipo: se conserva el método
—dónde está el corte, cómo se clasifica un PR, qué no entra— y se cambia todo
lo que era suyo.

Recuerda [ADR-0001](../../docs/adr/ADR-0001-repo-entorno-desarrollo-no-plugin.md):
la arquitectura de este repositorio prevalece sobre lo que recomiende una skill
genérica de WordPress. Aquí no se crea un fichero bootstrap de plugin, ni
cabeceras de plugin, ni `readme.txt`, ni hooks de activación, ni se asume que en
producción existan rutas relativas a un plugin.
