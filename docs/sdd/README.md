---
id: GUIA-SDD
title: "Guía de los documentos de diseño de software"
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

# Documentos de diseño de software (SDD)

## Propósito

Un **Software Design Document (SDD)** describe *qué* va a construir un cambio
significativo, *por qué*, *cómo* se validará y a qué partes del proyecto
afecta. Es la puerta de diseño para el trabajo grande: el sitio donde acordar
objetivos, no-objetivos, el diseño propuesto, los casos límite y los criterios
de aceptación **antes** de empezar a implementar.

Un SDD hace revisable un cambio grande en su conjunto, en lugar de que llegue
como un pull request enorme que los revisores tengan que descifrar.

El análisis de cómo funciona hoy el sistema anterior se
escribe como un SDD, pero **vive en `.local/`** y no aquí: describe por dentro
una instalación que no es nuestra (ADR-0002).

## SDD frente a ADR

| Artefacto | Responde a | Vida |
|-----------|------------|------|
| **SDD** | *Qué* se va a construir y *cómo* se implementará | Puede quedar como histórico una vez implementado |
| **ADR** | *Qué* decisión duradera se tomó y *por qué* | Larga vida, append-only |

Un SDD es un **diseño**; una [ADR](../adr/README.md) es una **decisión**. Un
mismo SDD suele contener varias decisiones duraderas (un modelo de datos, un
mecanismo de sincronización): esas pertenecen a ADR, y el SDD las enlaza en su
tabla *ADR requeridas o referenciadas* en lugar de enterrarlas en prosa.

**Política obligatoria:** toda especificación no trivial elaborada con ayuda
de una IA debe registrarse aquí rellenando `ai_assistance` (ver
[AGENTS.md](../../AGENTS.md)).

## Cuándo se requiere un SDD

Escribe un SDD para trabajo que necesite una puerta de diseño antes de
implementar. En este repositorio, típicamente:

- funcionalidades nuevas significativas del aplicativo (tipos de contenido,
  claves de meta, pantallas del escritorio, nuevos snippets con lógica de
  negocio);
- cambios de comportamiento observables por quien solicita, atiende o coordina, por
  quien lo coordina o por quien lo lee en el frontal;
- cambios transversales al contrato entre `src/Pse/`, `snippets/` y los
  scripts de aprovisionamiento;
- movimientos de frontera con el sistema anterior: llevarse al aplicativo algo
  que hoy vive en uno de sus formularios, o al revés;
- propuestas con varias fases de implementación.

## Cuándo NO hace falta un SDD

- Corrección de errores y mejoras pequeñas.
- Cambios locales con implementación obvia.
- Cambios que solo tocan tests o documentación.
- Trabajo ya cubierto por un SDD vigente.

Una decisión duradera que no necesita diseño completo puede ir directa a una
[ADR](../adr/README.md).

## Ubicación y nomenclatura

- Los SDD viven en `docs/sdd/`.
- Los ficheros se llaman `SDD-NNNN-titulo-corto-en-kebab-case.md` — por
  ejemplo `SDD-0001-pantallas-propias.md`.
- Los IDs van con ceros a la izquierda, son monótonos y nunca se reutilizan.
  El siguiente ID es `max(existentes) + 1`.
- [`plantilla.md`](plantilla.md) es la plantilla canónica.
- [`registro.md`](registro.md) lista todos los SDD. El índice se mantiene a
  mano.

## Estados

| Estado | Significado |
|--------|-------------|
| `Propuesta` | En redacción o en revisión; todavía no acordada. |
| `Aceptada` | Diseño acordado y en vigor; puede implementarse o estar ya implementado. |
| `Rechazada` | Considerada y descartada. Se conserva como registro. |
| `Sustituida` | Reemplazada por un SDD posterior (ver `superseded_by`). |

Un SDD puede editarse libremente mientras está en `Propuesta`. Una vez
`Aceptada` e implementada, evita reescribirla salvo erratas o enlaces; si el
diseño cambia sustancialmente, crea un SDD nuevo y sustituye el anterior. Los
SDD implementados se **conservan como registro histórico de diseño** — no se
borran.

## Asistencia de IA

Si una herramienta de IA ayudó a redactar o investigar un SDD, decláralo en
el frontmatter:

```yaml
ai_assistance:
  tool: "Claude Code"       # herramienta / interfaz usada
  model: "claude-fable-5-2"    # modelo, cuando sea relevante
```

Si no intervino ninguna IA, pon `none` en ambos campos. El frontmatter
registra herramientas y enlaces, **no** nombres de personas.

## Flujo de trabajo

1. Copia [`plantilla.md`](plantilla.md) a `SDD-NNNN-titulo-corto.md` con el
   siguiente ID. Empieza en `status: Propuesta`.
2. Rellena el diseño: problema, objetivos, no-objetivos, especificación,
   casos límite, estrategia de pruebas y criterios de aceptación.
3. Lista las decisiones duraderas en la tabla *ADR requeridas o
   referenciadas*; crea o enlaza las ADR correspondientes.
4. Añade el SDD a [`registro.md`](registro.md) y abre (o referencia) un PR.
5. Con la aprobación, pasa a `Aceptada` e implementa.

## Lista de comprobación para revisión

- [ ] El SDD tiene un ID único y monótono y un título en kebab-case.
- [ ] Objetivos y no-objetivos son explícitos.
- [ ] Casos límite, pruebas y criterios de aceptación están cubiertos (o
      marcados como «No aplica» con su motivo).
- [ ] Las decisiones duraderas están en la tabla *ADR requeridas o
      referenciadas*.
- [ ] Lo que se afirma del sistema de hoy cita su fuente verificable.
- [ ] `status` refleja la realidad.
- [ ] `ai_assistance` está relleno (valores o `none`).
- [ ] [`registro.md`](registro.md) está actualizado.
