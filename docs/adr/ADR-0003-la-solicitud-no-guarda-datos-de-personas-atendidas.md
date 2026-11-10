---
id: ADR-0003
title: "La solicitud no guarda datos de personas atendidas"
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

# ADR-0003: La solicitud no guarda datos de personas atendidas

## Estado

Aceptada

## Contexto

Los centros piden la intervención del personal de psicología educativa de su zona. Hasta ahora lo hacían por correo, y alguien reenviaba cada mensaje a mano. Quien coordina necesita saber cuántas solicitudes hay, de dónde y de qué, y cuáles siguen pendientes. Una solicitud de intervención psicológica dice algo de la salud de alguien.

## Problema

¿Qué se guarda de cada solicitud para poder repartirla y contarla sin tratar datos de salud de personas identificables?

## Factores de decisión

- La información de salud de una persona identificada es de categoría especial.
- La estadística que se pide es por centro, municipio, zona y asunto: no por persona.
- El profesional ya contacta con el centro para conocer el caso.

## Alternativas consideradas

### Opción 1: Guardar la solicitud completa, con la descripción del caso

Resuelve todo en un sitio, pero convierte el aplicativo en un registro de datos de salud.

### Opción 2: Guardar un identificador de la persona sin el motivo

Sigue asociando a una persona identificable con una atención psicológica.

### Opción 3: Guardar solo el centro, la fecha, un asunto de lista cerrada y el estado

No hay a quién identificar. El resto de la información viaja por el contacto directo entre el profesional y el centro, como hasta ahora.

## Decisión

Haremos la opción 3. La solicitud tiene número, centro, municipio, isla, zona, asunto y estado. El asunto se elige de una lista cerrada (`Vocabulary::subjects()`, que quien despliega cambia con el filtro `pse_subjects`) y no hay ningún campo de texto libre. Los estados son tres: pendiente, en atención y atendida.

## Consecuencias

### Positivas

- El aplicativo no trata datos personales de alumnado.
- La estadística por asunto sale sola, porque el asunto no es texto libre.

### Negativas

- La motivación de la solicitud, sus antecedentes y la autorización de la familia no caben aquí: siguen por su cauce.
- No se puede saber si dos solicitudes son del mismo caso.

### Neutras

- El centro, el municipio y la zona se copian en la solicitud al crearla, para que la estadística no cambie si luego cambia el catálogo o el reparto de zonas.
- El número es correlativo por año: «2026-0001».
