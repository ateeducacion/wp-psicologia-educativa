---
id: ADR-0007
title: "El catálogo de centros es una copia local que se pone al día sola"
status: Aceptada
date: 2026-11-10
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0002, ADR-0003]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-fable-5-2"
---

# ADR-0007: El catálogo de centros es una copia local que se pone al día sola

## Estado

Aceptada

## Contexto

La solicitud necesita el nombre, el municipio y la isla del centro, y la cuenta solo trae su código. Los aplicativos hermanos ya usan un catálogo de centros publicado como JSON, que copian a una opción del sitio una vez al día.

## Problema

¿De dónde salen el nombre y el municipio de un centro?

## Factores de decisión

- El catálogo cambia poco, pero cambia.
- No se puede depender de una descarga en cada visita.
- Un catálogo real no se versiona en un repositorio público.

## Alternativas consideradas

### Opción 1: Versionar el catálogo

Publica datos de un despliegue y envejece.

### Opción 2: Copiar las clases `Centre\CentreCatalogue*` de los aplicativos hermanos

Copia local en una opción, sincronizada a diario desde la dirección que configura la administración en «Ajustes → Centros educativos», con comprobación de integridad.

## Decisión

Haremos la opción 2, sin cambios respecto a los aplicativos hermanos.

## Consecuencias

### Positivas

- El mismo catálogo y el mismo ajuste en los cinco aplicativos.

### Negativas

- Si el centro de una cuenta no está en el catálogo, la solicitud se guarda sin nombre ni municipio.

### Neutras

- En el entorno de desarrollo el catálogo lo pone `scripts/seed-demo.php`, con centros inventados.
