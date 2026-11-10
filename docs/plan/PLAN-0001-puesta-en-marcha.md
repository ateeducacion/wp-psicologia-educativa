---
id: PLAN-0001
title: "Puesta en marcha"
status: Propuesta
date: 2026-11-10
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0002]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-fable-5-2"
---

# PLAN-0001: Puesta en marcha

| Fase | Qué | Estado |
|---|---|---|
| 1 | Esqueleto sobre la plataforma heredada y las seis pantallas con datos de demostración (REQ-0001) | Hecha |
| 2 | Revisión con quien coordina: asuntos, estados y textos | Pendiente |
| 3 | Despliegue: sitio propio, snippets, filtros del despliegue (`pse_chrome`, `pse_role_caps`, `pse_centre_code_meta_key`, `pse_subjects`) y catálogo de centros | Pendiente |
| 4 | Carga de las zonas y de los profesionales, y prueba con un centro de cada zona | Pendiente |
| 5 | Comunicación a los centros | Pendiente |

## Qué hay que configurar en el despliegue

Va en un snippet de quien despliega, no en este repositorio (ADR-0002):

- `pse_chrome`: logotipo, nombre de la organización y pie.
- `pse_role_caps`: dar `pse_request_intervention` al rol que ya tienen las
  direcciones de centro.
- `pse_centre_code_meta_key`: la meta de usuario donde el inicio de sesión
  guarda el código del centro.
- `pse_subjects`: la lista de asuntos acordada.
- `pse_role_labels`: cómo se llama cada perfil.
