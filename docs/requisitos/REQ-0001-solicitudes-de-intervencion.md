---
id: REQ-0001
title: "Solicitudes de intervención de psicología educativa"
status: Propuesta
date: 2026-11-10
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0003, ADR-0004, ADR-0005, ADR-0006]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-fable-5-2"
---

# REQ-0001: Solicitudes de intervención de psicología educativa

## Para qué

Los centros piden la intervención del personal de psicología educativa de su
zona. Por correo, alguien tiene que leer cada mensaje, averiguar la zona del
centro y reenviarlo, y nadie puede decir cuántas solicitudes hay ni en qué
estado están. El aplicativo quita el reenvío y deja el registro.

## Quién lo usa

| Perfil | Qué necesita |
|---|---|
| Dirección de centro | Solicitar una intervención sin escribir datos del alumnado, y ver en qué estado están las de su centro |
| Psicología educativa | Enterarse de que hay una solicitud en su zona, ver las de su zona y marcar su estado |
| Coordinación | Ver todas las solicitudes, filtrarlas, contarlas por municipio, zona y asunto, exportarlas y decir quién atiende cada zona |

## Requisitos

1. **Solicitar.** La dirección entra con su cuenta. El centro y la fecha los
   pone el aplicativo. Solo elige un asunto de una lista cerrada. Puede hacer
   varias solicitudes.
2. **Sin datos del alumnado.** Ni nombre, ni identificador, ni descripción
   del caso: no hay ningún campo de texto libre (ADR-0003).
3. **Número.** Cada solicitud recibe un número correlativo por año.
4. **Reparto por zona.** La solicitud va a la zona del centro, y se avisa por
   correo a quien la atiende, sin el asunto (ADR-0004, ADR-0006).
5. **Atender.** El profesional ve solo las solicitudes de sus zonas y solo
   cambia su estado: pendiente, en atención o atendida.
6. **Seguir.** La coordinación ve todas, las filtra por estado, asunto, zona
   y municipio, y las exporta en CSV.
7. **Contar.** Una pantalla de estadísticas: por mes, asunto, zona, municipio
   e isla.
8. **Zonas.** La coordinación mantiene qué centros tiene cada zona y quién la
   atiende.

## Fuera del alcance

- La información del caso, sus antecedentes y las autorizaciones.
- La comunicación entre el centro y el profesional.
- Agenda, citas y registro de las intervenciones.

## Pendiente de quien despliega

- La lista definitiva de asuntos.
- La relación de centros por zona y el correo de cada profesional.
- Si los tres estados son los que se quieren.
