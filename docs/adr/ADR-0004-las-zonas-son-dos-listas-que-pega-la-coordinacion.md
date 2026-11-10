---
id: ADR-0004
title: "Las zonas son dos listas que pega la coordinación"
status: Aceptada
date: 2026-11-10
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0003]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-fable-5-2"
---

# ADR-0004: Las zonas son dos listas que pega la coordinación

## Estado

Aceptada

## Contexto

Cada centro pertenece a una zona y cada zona la atienden uno o varios profesionales. El catálogo de centros que se copia a diario no dice la zona, y el reparto de profesionales cambia durante el curso.

## Problema

¿De dónde sale a qué zona va una solicitud y quién la ve?

## Factores de decisión

- Quien conoce el reparto es la coordinación, no la administración del sitio.
- El profesional puede no haber entrado nunca cuando llega su primera solicitud.
- Tiene que poder cambiarse sin tocar código.

## Alternativas consideradas

### Opción 1: Un campo de zona en el perfil de cada cuenta

Obliga a que la cuenta exista y a editarla en el escritorio, una a una.

### Opción 2: Ampliar el catálogo de centros con la zona

Depende de otro proyecto y no resuelve quién atiende cada zona.

### Opción 3: Dos listas de texto en una pantalla del aplicativo

«Código de centro; zona» y «zona; correo». La cuenta que entra con ese correo ve esa zona, y a ese correo llega el aviso.

## Decisión

Haremos la opción 3, en la pestaña «Zonas», que ve quien tiene `pse_manage_zones`. Se guardan en dos opciones del sitio (`Centre\Zones`). Las líneas sin un código de ocho cifras o sin un correo válido se descartan.

## Consecuencias

### Positivas

- La coordinación pega la hoja que ya tiene.
- El aviso llega aunque la cuenta todavía no exista.

### Negativas

- Un correo mal escrito deja una zona sin nadie que la vea: la pantalla del profesional lo dice.
- Una solicitud de un centro sin zona no la atiende nadie; la ve la coordinación.

### Neutras

- Cambiar el reparto no mueve las solicitudes ya registradas.
