---
id: GUIA-ADR
title: "Guía de los registros de decisiones de arquitectura"
status: Aceptada
date: 2026-11-10
related:
  issues: []
  prs: []
  adrs: []
  sdds: []
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-fable-5-2"
---

# Registros de decisiones de arquitectura (ADR)

## Propósito

Un **Architecture Decision Record (ADR)** captura una única decisión de
arquitectura duradera junto con su razonamiento: el contexto, el problema, las
alternativas que se consideraron, la decisión y las consecuencias que se
derivan de ella.

Las ADR existen para que quien trabaje en este repositorio — persona o IA —
pueda responder a *«¿por qué está construido así?»* mucho después, sin hacer
arqueología por hilos de pull requests o conversaciones. Una decisión que solo
vive en la descripción de un PR se pierde con facilidad; una ADR es un
documento de primera clase y de larga vida.

Aquí hay un motivo añadido. El sistema al que sustituye este repositorio se
construyó sin ninguna decisión escrita: un formulario de más de cien campos,
una plantilla de 42.165 caracteres metida dentro de un campo de texto y una
taxonomía usada para cuatro cosas distintas a la vez (la investigación que lo
documenta está en `.local/`, que no se versiona). Nada de eso está mal por
capricho; está así porque nadie tuvo dónde escribir por qué. Estas ADR son ese
sitio.

Principios:

- **Evidencia antes que preferencia.** Mejor una fuente verificable que una
  afirmación.
- **Separar hechos, interpretación y decisión.** Primero qué se observa,
  después qué significa y por último qué se decide.
- **IDs estables y monótonos.** Los IDs nunca se reutilizan.
- **Append-only.** Las decisiones aceptadas no se reescriben: se sustituyen.

## ADR frente a SDD

Las ADR y los [Software Design Documents (SDD)](../sdd/README.md) se
complementan, no compiten:

| Artefacto | Responde a | Vida |
|-----------|------------|------|
| **SDD** | *Qué* se va a construir y *cómo* | Puede quedar como histórico una vez implementado |
| **ADR** | *Qué* decisión duradera se tomó y *por qué* | Larga vida, append-only |

Un cambio grande suele empezar con un SDD que describe el diseño. Las
decisiones que sobreviven al cambio en sí (un modelo de datos, un mecanismo de
sincronización, un límite de seguridad) se extraen a ADR o se enlazan a las
existentes. No copies un SDD entero dentro de una ADR.

## Cuándo se requiere una ADR

Crea o actualiza una ADR cuando un cambio **introduce o modifica una decisión
de arquitectura duradera** — una que quien venga después no debería tener que
re-litigar. En este repositorio, típicamente decisiones que afecten a:

- el papel del repositorio (entorno de desarrollo, no plugin) y qué es fuente
  de verdad (`src/Pse/`, `snippets/`);
- el mecanismo de sincronización de snippets con Code Snippets;
- el modelo de datos: los tipos de contenido `pse_design`, `pse_action`,
  `pse_speaker` y `pse_incident` y las taxonomías `pse_programme`,
  `pse_topic`, `pse_competence` y `pse_scope`;
- el reparto de permisos: el ciclo del diseño, los roles del aplicativo, el
  permiso por campo y el acotado por ámbito;
- la migración desde el sistema actual y qué se queda en el archivo;
- el flujo de aprovisionamiento (wp-env, el despliegue sobre el subsitio de un
  multisitio) o la CI;
- cualquier política o flujo de generación asistida por IA.

**Política obligatoria:** toda decisión de arquitectura no trivial tomada con
ayuda de una IA debe registrarse aquí rellenando `ai_assistance` (ver
[AGENTS.md](../../AGENTS.md)).

En caso de duda, es mejor escribir una ADR corta que perder el razonamiento.

## Cuándo NO hace falta una ADR

- Corrección de errores que restauran el comportamiento previsto.
- Refactorizaciones rutinarias sin decisión observable desde fuera.
- Actualización de dependencias, cambios de formato/lint, correcciones de
  texto.
- Cambios que solo tocan tests o traducciones.

Si un cambio necesita un diseño pero todavía no fija una decisión duradera,
empieza por un [SDD](../sdd/README.md).

## Ubicación y nomenclatura

- Las ADR viven en `docs/adr/`.
- Los ficheros se llaman `ADR-NNNN-titulo-corto-en-kebab-case.md` — por
  ejemplo `ADR-0003-la-solicitud-no-guarda-datos-de-personas-atendidas.md`.
- Los IDs van con ceros a la izquierda, son monótonos y nunca se reutilizan.
  El siguiente ID es `max(existentes) + 1`.
- [`plantilla.md`](plantilla.md) es la plantilla canónica: cópiala a un
  fichero nuevo y asigna el siguiente ID.
- [`registro.md`](registro.md) lista todas las ADR. El índice se mantiene a
  mano.

## Estados

| Estado | Significado |
|--------|-------------|
| `Propuesta` | En discusión; todavía no acordada. |
| `Aceptada` | Acordada y en vigor. |
| `Rechazada` | Considerada y descartada. Se conserva como registro. |
| `Sustituida` | Reemplazada por una ADR posterior (ver `superseded_by`). |

## Asistencia de IA

Si una herramienta de IA ayudó a redactar o investigar una ADR, decláralo en
el frontmatter:

```yaml
ai_assistance:
  tool: "Claude Code"       # herramienta / interfaz usada
  model: "claude-fable-5-2"    # modelo, cuando sea relevante
```

Si no intervino ninguna IA, pon `none` en ambos campos. La declaración es de
trazabilidad, no de juicio. El frontmatter registra herramientas y enlaces,
**no** nombres de personas: para la atribución están los issues y PRs.

## Sustituir una ADR

Las ADR aceptadas son **append-only**: no se reescriben salvo para corregir
erratas o enlaces rotos. Para cambiar una decisión aceptada:

1. Crea una ADR nueva con el siguiente ID.
2. Pon `supersedes: [ADR-XXXX]` en la nueva.
3. Pon `status: Sustituida` y `superseded_by: [ADR-YYYY]` en la antigua.
4. Actualiza [`registro.md`](registro.md).

Para un matiz que no cambia la decisión, no hace falta una ADR nueva: se añade
al final una `## Adenda — AAAA-MM-DD: <qué>` con su propia línea de asistencia
de IA.

**Todo esto empieza a regir cuando la ADR se publica**, y publicar aquí es el
commit. Mientras el repositorio no tenga historia, una ADR no es todavía la
dirección de nadie: se corrige en su propio texto, se consolida y, si hace
falta, se renumera. Eso se hizo una vez, el 2026-09-14, y está contado en
[`registro.md`](registro.md). Después del primer commit, el identificador y el
texto quedan congelados.

## Flujo de trabajo

1. Identifica una decisión duradera.
2. Copia [`plantilla.md`](plantilla.md) a `ADR-NNNN-titulo-corto.md` con el
   siguiente ID. Empieza en `status: Propuesta`.
3. Rellena contexto, problema, factores, alternativas, decisión y
   consecuencias, citando fuentes verificables.
4. Añade la ADR a [`registro.md`](registro.md).
5. Abre (o referencia) un PR. Si se acuerda, el estado pasa a `Aceptada`.
6. Si más adelante se revierte la decisión, sustitúyela — nunca edites el
   registro aceptado.

## Lista de comprobación para revisión

- [ ] La ADR tiene un ID único y monótono y un título en kebab-case.
- [ ] Contexto, problema, alternativas, decisión y consecuencias están
      presentes.
- [ ] Las consecuencias positivas, negativas y neutras se declaran con
      honestidad.
- [ ] Cada afirmación sobre el sistema de hoy cita su fuente: ruta:línea del
      repositorio, documentación oficial, experimento reproducible o medición
      hecha sobre el material de `.local/`, que no se publica
      ([ADR-0002](ADR-0002-se-hereda-la-plataforma-de-los-aplicativos-hermanos.md)).
- [ ] `status` refleja la realidad (`Propuesta` mientras se discute).
- [ ] `ai_assistance` está relleno (valores o `none`).
- [ ] Las ADR sustituidas tienen `superseded_by` y la nueva `supersedes`.
- [ ] [`registro.md`](registro.md) está actualizado.
