---
id: ADR-0008
title: "Lo del sitio va en el sitio, y lo de la persona en su cuenta"
status: Aceptada
date: 2026-11-17
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0004]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0008: Lo del sitio va en el sitio, y lo de la persona en su cuenta

## Estado

Aceptada

## Contexto

El destino puede ser un subsitio de un multisitio. Ahí **las cuentas y su meta
de usuario son de toda la red**, y las opciones, los contenidos y los términos
son **de cada sitio**.

En otros aplicativos del equipo el ámbito de cada persona —qué contenidos
puede gestionar— se guardaba en la meta de usuario. En un multisitio eso lo
comparten todos los subsitios: cambiarlo en uno lo cambia en todos, y si el
ámbito es el número de un término, en otro sitio ese número es otra cosa. Allí
se ha corregido guardándolo con `update_user_option()`, que lo escribe por
sitio.

Aquí se revisó con el mismo criterio y **no hay ese fallo**:

- Las **zonas** y **quién atiende cada una** son dos opciones del sitio
  (`pse_centre_zones`, `pse_zone_staff`; `src/Pse/Centre/Zones.php`,
  [ADR-0004](ADR-0004-las-zonas-son-dos-listas-que-pega-la-coordinacion.md)).
  Quien atiende se reconoce por el correo de la cuenta, que es de la red, pero
  la lista contra la que se compara es la de este sitio
  (`Access::attend_by_zones_list()`, `Access::zones()`).
- La única meta de usuario es el **centro de la persona** (`pse_centre_code`,
  `Access::centre_code()`): es un dato de la persona, el mismo en cualquier
  sitio, y la red es su sitio.

## Problema

Que siga así cuando el aplicativo crezca: el primer dato nuevo que dependa del
sitio y se guarde en la meta de usuario reintroduce el fallo, y en local no se
ve, porque el entorno es un sitio único.

## Alternativas consideradas

- **No escribir nada.** Descartada: la regla no se deduce del código, y el
  entorno local no la pone a prueba.
- **Escribir la regla y comprobarla con un test** (elegida).

## Decisión

- Lo que **depende del sitio** —qué gestiona o atiende una persona, sus
  preferencias en este aplicativo, cualquier número de un contenido o de un
  término— se guarda en **opciones del sitio** o, si es de una persona, en una
  **opción de usuario por sitio** (`update_user_option()` /
  `get_user_option()`). Nunca en `update_user_meta()`.
- Lo que **es de la persona** en cualquier sitio —su centro— va en la meta de
  usuario.
- `tests/unit/test-site-data.php` recorre `src/` y `snippets/` y falla si
  aparece una escritura de meta de usuario que no esté en la lista de datos de
  la persona. Ampliar esa lista es decidir que el dato es de la persona.

## Consecuencias

### Positivas

- La regla está escrita y comprobada, aunque el entorno local no sea un
  multisitio.

### Negativas

- El test mira el código, no el comportamiento: una escritura hecha de otra
  forma —SQL directo, por ejemplo— no la ve.
- Quien añada un dato de la persona tiene que tocar la lista del test.

## Referencias

- `src/Pse/Centre/Zones.php`, `src/Pse/Access/Access.php`,
  `tests/unit/test-site-data.php`.
- WordPress: [`update_user_option()`](https://developer.wordpress.org/reference/functions/update_user_option/).
