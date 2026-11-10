---
id: ADR-0002
title: "Se hereda la plataforma de los aplicativos hermanos"
status: Aceptada
date: 2026-11-10
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0001]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-fable-5-2"
---

# ADR-0002: Se hereda la plataforma de los aplicativos hermanos

## Estado

Aceptada

## Contexto

Este aplicativo es el quinto de una familia que comparte utillaje: empaquetador, sincronización de snippets, provisión, comprobaciones y flujos de integración continua. El más reciente es el de formación. Como ellos, no es un plugin ([ADR-0001](ADR-0001-el-repositorio-es-un-entorno-de-desarrollo-no-un-plugin.md)).

## Problema

¿Se decide de nuevo cada cuestión transversal o se copia la plataforma que ya funciona?

## Factores de decisión

- El aplicativo es pequeño y tiene que estar en uso en semanas.
- Quien mantiene los otros cuatro mantiene este.
- El repositorio es público.

## Alternativas consideradas

### Opción 1: Diseñar la plataforma desde cero

Tiempo que no hay, y una quinta forma de hacer lo mismo.

### Opción 2: Copiar la del aplicativo de formación y adaptar

Se copian `build/`, `scripts/`, el `Makefile`, los flujos y los tests de infraestructura, y se cambia el prefijo a `pse`.

## Decisión

Haremos la opción 2. Con ella se heredan estas decisiones, que no se repiten en ADR propias:

- **La portada del sitio es el aplicativo.** Sin sesión, al acceso; con sesión, un documento propio que no usa el tema. Cada pantalla es un parámetro de la portada (`?pse_tab=`): no hay páginas que crear.
- **Nada que identifique a una organización se versiona.** El logotipo, el nombre de quien despliega, la analítica y los enlaces legales llegan por el filtro `pse_chrome`; los nombres de los perfiles, por `pse_role_labels`. El material privado vive en `.local/`, y `make check-public` vigila que no salga.
- **Las librerías de terceros se sirven desde jsDelivr con SRI**, con la misma versión en `package.json`.
- **Los datos de demostración son inventados**: centros, municipios y zonas que no existen.

## Consecuencias

### Positivas

- Un solo conjunto de órdenes (`make up`, `make test`, `make check`) en los cinco aplicativos.
- La vista previa en WordPress Playground de cada PR funciona desde el primer día.

### Negativas

- El aplicativo necesita un sitio para él solo, porque ocupa la portada.
- Las mejoras de la plataforma hay que llevarlas a mano de un repositorio a otro.

### Neutras

- Lo que este aplicativo no usa de la plataforma —taxonomías, editor, documentos— no se copia.
