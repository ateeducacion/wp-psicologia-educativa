---
id: ADR-0006
title: "El aviso por correo no lleva el asunto"
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

# ADR-0006: El aviso por correo no lleva el asunto

## Estado

Aceptada

## Contexto

Cuando un centro solicita una intervención, quien atiende su zona tiene que enterarse sin estar mirando el aplicativo.

## Problema

¿Qué dice el correo de aviso?

## Factores de decisión

- El correo sale del sitio y se guarda en buzones que el aplicativo no controla.
- El asunto, junto al centro, es la parte más sensible de la solicitud.

## Alternativas consideradas

### Opción 1: El correo con todos los datos de la solicitud

Cómodo, pero deja el asunto en cada buzón.

### Opción 2: El correo con el número, el centro y la fecha, y un enlace

El asunto se consulta dentro, con sesión.

## Decisión

Haremos la opción 2 (`Notifications\Mailer`). El aviso va a los correos de la zona; si la zona no tiene a nadie, no se envía y la pantalla del centro lo dice de otra forma.

## Consecuencias

### Positivas

- El asunto no sale del aplicativo.

### Negativas

- Un clic más para quien atiende.

### Neutras

- No hay más avisos: ni al centro cuando cambia el estado, ni recordatorios.
