---
id: ADR-0009
title: "La solicitud dice el tipo de intervención y si hay autorización"
status: Propuesta
date: 2026-11-25
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0003]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0009: La solicitud dice el tipo de intervención y si hay autorización

## Estado

Propuesta

## Contexto

En la presentación del aplicativo al equipo de coordinación y a parte del
personal de psicología, el 2026-11-25, se pidieron tres cambios en lo que
guarda una solicitud:

1. **Saber si hay autorización.** Para intervenir con alumnado menor de edad
   hace falta la autorización de la familia. Hoy el profesional se entera al
   llegar al centro, y a veces no la hay. Se pidió que el centro diga, al
   solicitar, si cuenta con ella o si no hace falta porque el alumno es mayor
   de edad. La autorización la guarda el centro: al área no llega ningún
   documento.
2. **Separar el tipo del motivo.** «Intervención grupal o preventiva» era un
   asunto más (`src/Pse/Domain/Vocabulary.php`, en la lista por defecto), y al
   elegirlo se perdía el motivo. El registro que lleva hoy la coordinación
   separa el motivo y si la intervención es individual o grupal.
3. **Un estado para lo que no se llega a hacer**, porque la familia retira la
   autorización o por otra causa.

ADR-0003 dejó fuera «la autorización de la familia» y fijó tres estados. Esta
ADR no cambia el principio de ADR-0003: ninguno de los datos nuevos identifica a
nadie.

## Problema

¿Cómo recoge la solicitud el tipo de intervención y la autorización sin abrir
ningún campo que pueda llevar datos de una persona atendida?

## Factores de decisión

- ADR-0003: nada de texto libre ni identificadores.
- La dirección del centro tiene que rellenar lo menos posible.
- Un único motivo por solicitud: el orden de la lista es el orden de prioridad
  con el que se atiende, y dos motivos lo desdibujan.
- La coordinación quiere contar por tipo.

## Alternativas consideradas

### Opción 1: Que el centro suba el documento de autorización

Da la prueba, pero mete en el aplicativo un documento con el nombre del alumno
y de su familia. Choca de frente con ADR-0003.

### Opción 2: Una declaración responsable, de lista cerrada

El centro elige entre «Tiene autorización (menor de edad)» y «No precisa
autorización (mayor de edad)», junto a un texto en el que declara que es cierto
y que la custodia. No hay opción «no tiene»: sin autorización no se solicita.
No guarda nada de nadie y no pide un trámite más. En contra: el aplicativo no
comprueba la autorización, se fía de quien la declara.

### Opción 3: Dejar la autorización fuera, como hasta ahora

No toca nada, pero deja sin resolver lo que se pidió.

Para el tipo, la opción que no rompe nada es otra lista cerrada, «individual» o
«grupal», que elige también el centro. Permitir varios motivos se descartó por
el criterio de prioridad.

## Decisión

Haremos la opción 2, y el tipo como lista cerrada:

- `pse_scope`: `individual` o `group`. Obligatorio.
- `pse_authorization`: `authorized` o `not_required`. Obligatorio. El texto de
  la declaración se cambia con el filtro `pse_authorization_statement`, porque
  su redacción es de quien despliega.
- Un cuarto estado, `withdrawn` («No realizada»), para lo que no se llega a
  hacer.
- «Intervención grupal o preventiva» sale de los asuntos por defecto, y
  «Violencia de género» e «Identidad de género» pasan a ser dos asuntos.

Las solicitudes anteriores no tienen tipo ni autorización y se muestran con «—».

## Consecuencias

### Positivas

- El profesional sabe antes de ir si puede intervenir.
- La estadística por tipo sale sola, como la de asunto.
- Ningún dato nuevo identifica a una persona: ADR-0003 sigue en pie.

### Negativas

- La autorización es una declaración: si el centro declara en falso, el
  aplicativo no lo detecta. La responsabilidad queda en quien la firma.
- Dos campos más en el formulario.

### Neutras

- El aviso por correo sigue sin llevar el asunto (ADR-0006), y tampoco lleva
  el tipo ni la autorización.
- Los nombres definitivos del estado nuevo, los asuntos y el texto legal los
  da la coordinación.

## Adenda — 2026-11-25

La coordinación mandó por correo la redacción definitiva. El estado `withdrawn`
se llama «Baja» (retirada de la solicitud o revocación de la autorización por
parte de la familia), y las dos opciones de la autorización pasan a ser «Se
cuenta con la autorización familiar firmada (alumnado menor de edad)» y «No
requiere autorización familiar (alumnado mayor de edad)». Los valores que se
guardan no cambian. Su lista de asuntos va en el filtro `pse_subjects` del
despliegue.
