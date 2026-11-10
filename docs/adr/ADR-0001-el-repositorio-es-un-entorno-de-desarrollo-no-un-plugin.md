---
id: ADR-0001
title: "El repositorio es un entorno de desarrollo, no un plugin"
status: Aceptada
date: 2026-11-10
related:
  issues: []
  prs: []
  sdds: []
  adrs: []
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-fable-5-2"
---

# ADR-0001: El repositorio es un entorno de desarrollo, no un plugin

## Estado

Aceptada

## Contexto

El sitio donde se despliega solo admite código pegado en el plugin Code Snippets: no se pueden instalar plugins propios ni subir ficheros.

## Problema

¿Cómo se desarrolla, se prueba y se entrega un aplicativo que en producción es un fragmento de código pegado?

## Factores de decisión

- Lo que se entrega tiene que caber en un snippet.
- El código necesita tests, revisión y control de versiones como cualquier otro.
- Quien despliega no es quien desarrolla.

## Alternativas consideradas

### Opción 1: Escribir directamente en Code Snippets

Sin tests, sin historial y sin revisión.

### Opción 2: Un plugin

No se puede instalar en el sitio de destino.

### Opción 3: Código modular en `src/Pse` que se empaqueta en un snippet

`build/pack-snippet.php` concatena los ficheros en el orden de `src/Pse/load-order.php` y genera `snippets/pse-psicologia-app.bundle.php`.

## Decisión

Haremos la opción 3. El repositorio no lleva cabecera de plugin, ni `readme.txt`, ni ganchos de activación. El bundle se genera, no se edita a mano.

## Consecuencias

### Positivas

- Tests de PHPUnit contra un WordPress real, con `wp-env`.
- Un único fichero que pegar, con su versión.

### Negativas

- Las clases son `final` con métodos estáticos y no hay `declare(strict_types=1)`: es lo que permite concatenarlas.
- Un fichero nuevo en `src/Pse` hay que añadirlo a `load-order.php`.

### Neutras

- Los roles van en un snippet aparte, `snippets/roles-and-profiles.php`, con prioridad 5.
