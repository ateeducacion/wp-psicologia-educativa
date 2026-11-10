---
id: ADR-0005
title: "Tres perfiles, y el centro sale de la cuenta"
status: Aceptada
date: 2026-11-10
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0003, ADR-0004]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-fable-5-2"
---

# ADR-0005: Tres perfiles, y el centro sale de la cuenta

## Estado

Aceptada

## Contexto

Usan el aplicativo las direcciones de los centros, el personal de psicología educativa y quien coordina. En el sitio de destino, el inicio de sesión ya crea las cuentas de las direcciones con su rol y guarda el código de su centro.

## Problema

¿Quién puede hacer qué, y cómo se sabe de qué centro es quien solicita?

## Factores de decisión

- El código del centro no puede venir del formulario.
- Los roles del sitio de destino no son los de este repositorio.
- Esconder una fila no es protegerla.

## Alternativas consideradas

### Opción 1: Comprobar roles

Ata el código a los nombres de rol de un despliegue.

### Opción 2: Comprobar capacidades, repartidas por un mapa que se puede filtrar

Cada cosa que se hace tiene su capacidad; el mapa rol → capacidades tiene un valor por defecto y el filtro `pse_role_caps`.

## Decisión

Haremos la opción 2. Las capacidades son `pse_request_intervention` (solicitar y ver las de su centro), `pse_attend_requests` (ver las de sus zonas y cambiar el estado), `pse_oversee_requests` (verlas todas, la estadística y la exportación) y `pse_manage_zones`. Por defecto las tienen los roles `pse_school_head`, `pse_psychologist` y `pse_coordinator`.

El centro de quien solicita se lee de la meta de usuario que diga el filtro `pse_centre_code_meta_key` (por defecto `pse_centre_code`). Sin un código de ocho cifras no se solicita ni se ve nada. Todo pasa por `Access\Access`: lo que se pinta y lo que se cambia.

## Consecuencias

### Positivas

- Quien despliega da la capacidad de solicitar al rol que ya tienen sus direcciones, sin tocar el código.
- Un solo sitio donde mirar quién ve qué.

### Negativas

- La delegación de la dirección en otra persona del centro se hace dándole a esa cuenta el rol y el código del centro; el aplicativo no la gestiona.

### Neutras

- La coordinación no cambia estados: eso es de quien atiende. La administración sí puede.
- En el escritorio, las solicitudes solo las ve la administración (`pse_manage_app`).
