---
id: SDD-0000
title: "Título corto del diseño"
status: Propuesta
date: AAAA-MM-DD
related:
  issues: []
  prs: []
  adrs: []
  sdds: []
supersedes: []
superseded_by: []
ai_assistance:
  tool: ""
  model: ""
---

<!--
Cómo usar esta plantilla:
1. Copia este fichero a `SDD-NNNN-titulo-corto-en-kebab-case.md` con el
   siguiente ID libre.
2. Actualiza el frontmatter (id, title, date, enlaces relacionados).
3. Rellena las secciones que apliquen; marca las que de verdad no apliquen
   como «No aplica» (con una línea de motivo) en vez de borrarlas. Borra
   estos comentarios de guía antes de enviar.
4. Usa un SDD para propuestas significativas, no para arreglos pequeños.
5. Captura las decisiones duraderas en «ADR requeridas o referenciadas»:
   enlaza una ADR existente o márcala como «ADR pendiente».
6. Registra la asistencia de IA en `ai_assistance` (valores, o `none` si no
   se usó).
Se puede editar libremente mientras está en Propuesta. Una vez Aceptada e
implementada, solo erratas/enlaces. Ver README.md para la política completa.
-->

# SDD-0000: Título corto del diseño

## Estado

Propuesta

<!-- Uno de: Propuesta | Aceptada | Rechazada | Sustituida. Mantenlo
sincronizado con el `status` del frontmatter. -->

## Resumen

<!-- Uno o dos párrafos: qué cambia y por qué importa. -->

## Contexto

<!-- El trasfondo que necesita quien revisa: cómo funciona hoy la parte
afectada, a alto nivel, y qué motiva este diseño. Cita la fuente de cada
dato del sistema actual (campo, formulario, vista, snippet, página, o cifra
medida sobre la foto de `.local/`). -->

## Problema

<!-- El problema que se resuelve y quién lo tiene (quien solicita,
quien lo coordina, quien administra el sitio, quien desarrolla). -->

## Objetivos

<!-- Qué significa el éxito. Formúlalos de modo comprobable. -->

## No objetivos

<!-- Lo que este diseño explícitamente no intenta. -->

## Especificación / Diseño propuesto

<!-- El diseño a alto nivel y después el detalle: tipos de contenido,
taxonomías, claves de meta y sus valores, capacidades, hooks y snippets
afectados, scripts de aprovisionamiento. Nombra los ficheros que cambian o se
añaden. -->

## Comportamiento ante errores y casos límite

<!-- Qué pasa con entradas inválidas, datos ausentes o estados intermedios:
una acción sin fechas, un usuario sin ámbito, una incidencia huérfana, una
URL histórica que debe seguir resolviendo. El sitio nunca debe romperse por
un fallo del snippet. -->

## Estrategia de pruebas

<!-- Cobertura PHPUnit (tests/unit/), cómo ejecutarla (`make test`) y las
comprobaciones de estilo (`make lint`, `make phpmd`). -->

## Criterios de aceptación

<!-- Condiciones concretas y comprobables de «hecho». -->

- [ ] ...

## ADR requeridas o referenciadas

<!-- Lista las decisiones duraderas. Enlaza una ADR existente o marca
«ADR pendiente». -->

| Decisión | ADR | Estado |
|----------|-----|--------|
| Ejemplo de decisión duradera | ADR-XXXX | Propuesta |

## Pendientes y trabajo futuro

<!-- Lo que queda fuera y los siguientes pasos. Enlaza issues/PRs cuando
existan. -->

- [ ] ...

## Referencias

<!-- Fuentes citadas, más issues, PRs, ADR y SDD relacionados. -->
