---
name: changelog
description: Prepara el bloque de versión del CHANGELOG a partir de los PR fusionados desde el último corte.
metadata:
  author: ateeducacion
  version: "1.0.0"
---

# Redactar un bloque de CHANGELOG

> **Esto produce un borrador, no un changelog terminado.** Sirve para no partir
> de cero; quien mantiene el repositorio revisa y corrige cada línea antes de
> hacer commit.

El corte es la última release de GitHub. Hasta que exista la primera, la
referencia es el bloque superior de `CHANGELOG.md`.

La cabecera `## [X.Y.Z]` superior es la **única** fuente de verdad de la
versión: `make bundle` la lee y la escribe en el `@version` del bundle, y falla
si no la encuentra. No toques el `@version` a mano.

## 1. Localiza el corte

```bash
grep -n -m2 '^## \[' CHANGELOG.md      # bloque superior y su fecha
git tag --list 'v*' --sort=-v:refname | head -3
```

Si el bloque superior ya está etiquetado, el corte es su fecha y toca abrir uno
nuevo. Si aún no tiene tag, **amplíalo** en vez de crear otro: mientras no se
publique, sigue siendo la misma versión.

## 2. Reúne los PR fusionados desde el corte

```bash
gh pr list --state merged --search "merged:>YYYY-MM-DD" \
  --json number,title,body,labels,mergedAt --limit 100
```

De cada PR lee el **`body` completo**, no solo el título: aquí un PR puede
agrupar varios cambios sin relación bajo un título único. Si el cuerpo cita
`Closes #NNN`, lee también la issue con `gh issue view NNN`.

## 3. Clasifica

| Sección | Qué va aquí |
|---|---|
| **Añadido** | Funcionalidad nueva: pantallas, campos, tipos de contenido, roles, exportaciones |
| **Cambiado** | Comportamiento que ya existía y ahora es distinto |
| **Corregido** | Errores, permisos mal aplicados, datos que no cuadraban |
| **Eliminado** | Lo que deja de existir |
| **Pendiente antes de desplegar** | Lo que hay que resolver fuera del código para que la versión funcione: snippets que deben existir en el destino, filtros que alguien tiene que contestar (`evt_participants`, `evt_centres`, `evt_chrome`), ajustes del `.env` |

Un PR puede dar varias entradas en secciones distintas. Divídelo.

## 4. Escribe las entradas

- Una frase por línea, en castellano, mayúscula inicial y sin punto final.
- Describe el **efecto para quien usa el aplicativo**, no la implementación:
  - ✅ `Un taller lleno deja de poder elegirse en la inscripción`
  - ❌ `Añadido candado por evento en Registrations::seat()`
- Prefija el área cuando aclare: `Taller del evento: …`, `Página pública: …`.
- Agrupa lo relacionado dentro de cada sección.

**Qué NO incluir:** cambios solo de tests, lint, CI o herramientas internas;
duplicados del mismo arreglo; commits de merge; subidas de versión de
dependencias que no cambian nada visible. Ojo: un PR titulado `test:` o
`chore:` puede esconder un arreglo real —o un cambio de versión de una librería
que sí se ve, como subir Bootstrap—; lee el cuerpo y, si lo hay, sácalo como
entrada y descarta el resto.

## 5. Elige la versión

SemVer sobre lo que ya está desplegado: incompatible para quien usa el
aplicativo o migración de datos → **mayor**; funcionalidad nueva → **menor**;
solo arreglos → **parche**. Pregunta si dudas; no la infieras a la brava.

## 6. Inserta el bloque

Va justo después de la cabecera introductoria y **antes** del bloque anterior.
No toques nada por debajo.

```markdown
## [0.5.0] — 2026-09-15

### Añadido

- …
```

## 7. Regenera y comprueba

```bash
make bundle                                  # propaga la versión al bundle
grep -m1 '@version' snippets/evt-eventos-app.bundle.php
make check
```

## 8. Publica

```bash
make release
```

Etiqueta `vX.Y.Z` y publica la release en GitHub con este bloque como notas.
Aborta si el árbol está sucio, si el bundle no está regenerado, si no lleva esa
versión o si el tag ya existe. **No crees el tag a mano.**

Etiquetar **no despliega**: los snippets llegan al sitio de destino con
`npm run snippets`, que lee el `.env` y exige `--yes` para cualquier escritura
remota. Son dos pasos distintos y en ese orden.

## 9. Avisa de que es un borrador

Di qué PR entraron y en qué sección, cuáles descartaste y por qué, y recuerda
que hay que revisar cada línea antes del commit. Si el bloque se quedara solo
con cambios internos, dilo: probablemente no toca versión nueva todavía.
