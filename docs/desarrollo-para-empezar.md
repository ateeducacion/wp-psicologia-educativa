# Empezar a trabajar en este proyecto (sin haber programado nunca)

Esta guía es para una persona del equipo **que no ha programado**, que va a
trabajar con un **agente de IA** (Claude Code, Copilot, Codex…) y que necesita
saber qué instalar, qué teclear y —sobre todo— **qué tiene que ver en pantalla
para saber que ha ido bien**.

No hace falta entender el código. Sí hace falta seguir el orden y no saltarse
el paso 4, que es el que evita romper nada.

Si ya te manejas con git y Docker, esto no es para ti: ve al
[README](../README.md) y a [AGENTS.md](../AGENTS.md).

---

## Cuatro palabras que van a salir todo el rato

| Palabra | Qué es, en una frase |
|---|---|
| **Terminal** | La ventana negra donde se escriben órdenes. En macOS se llama «Terminal»; en Windows, «Ubuntu» (ver más abajo). |
| **Repositorio** | La carpeta del proyecto, con todo su historial de cambios. También la copia que vive en GitHub. |
| **Rama** (*branch*) | Una copia de trabajo con nombre propio donde haces tus cambios sin tocar la versión buena. |
| **Pull Request** (**PR**) | La propuesta de cambio que mandas a GitHub para que alguien la revise y la apruebe. Nada entra sin pasar por aquí. |
| **`make ...`** | El atajo del proyecto. `make up`, `make check`… No hay que saber qué hacen por dentro: `make help` los lista todos. |

Y una regla de oro: **cuando algo no salga como dice esta guía, para y
pregunta.** No hay ninguna prisa que compense romper el entorno.

---

## 1. Qué hay que instalar

Son siete cosas. Se instalan una vez y ya está.

| Programa | Para qué sirve | Versión mínima |
|---|---|---|
| **Docker Desktop** | Levanta un WordPress de mentira en tu ordenador | 4.30 |
| **Node.js** | Herramientas del entorno | **22** (lo dice `.nvmrc`) |
| **PHP** | El lenguaje del proyecto; lo usan las comprobaciones | **8.1** |
| **Composer** | Instala las librerías de PHP | 2.5 |
| **git** | Guarda el historial de cambios | 2.39 |
| **gh** | El cliente de GitHub: sirve para crear los PR desde la terminal | 2.40 |

Y nada más: **todo lo que corre aquí es PHP o JavaScript**. No hace falta
ningún otro lenguaje.

### 1.1 En macOS

Primero **Homebrew**, que es el instalador de programas de macOS. Abre la
aplicación **Terminal** (Cmd+Espacio, escribe «Terminal») y pega:

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

Te pedirá la contraseña de tu Mac. **Al teclearla no se ve nada, ni puntitos:
es normal.** Cuando termine, el propio Homebrew te dirá si tienes que ejecutar
dos líneas más (`echo 'eval "$(/opt/homebrew/bin/brew shellenv)"' ...`).
Hazlo si lo pide, cierra la Terminal y ábrela otra vez.

Y ahora lo demás:

```bash
brew install --cask docker
brew install node php composer git gh
```

Abre **Docker Desktop** desde Aplicaciones una vez y déjalo arrancado: en la
barra superior tiene que aparecer la ballena y, al pinchar, poner
**«Engine running»**. Docker tiene que estar en marcha siempre que trabajes.

### 1.2 En Windows

En Windows **se trabaja dentro de WSL**, que es un Linux que corre dentro de tu
Windows. No es un capricho: las órdenes de este proyecto (`make`) no existen en
Windows a secas, y montarlo de otra manera da problemas toda la semana.

Abre **PowerShell como administrador** (botón derecho en el menú Inicio →
«Terminal (Administrador)») y pega:

```powershell
wsl --install -d Ubuntu
```

Reinicia el ordenador cuando lo pida. Al volver se abrirá una ventana negra que
te pide un **usuario y una contraseña de Ubuntu**: invéntatelos y apúntalos, no
son los de Windows.

Después instala **Docker Desktop para Windows** desde
<https://www.docker.com/products/docker-desktop/>. Al terminar, ábrelo y ve a
**Settings → Resources → WSL integration** y **activa «Ubuntu»**. Sin ese paso
Docker no se ve desde dentro de Linux y nada funciona.

A partir de aquí, **todo lo demás se hace en la ventana «Ubuntu»** (búscala en
el menú Inicio). Pega esto ahí:

```bash
sudo apt update
sudo apt install -y git make php-cli php-xml php-mbstring php-curl composer curl unzip
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
sudo apt install -y gh
```

Te pedirá la contraseña de Ubuntu (la que te acabas de inventar). Igual que en
macOS: **al escribirla no se ve nada**.

### 1.3 Comprobar que está todo

Pega esto **entero** en la terminal (Terminal en macOS, Ubuntu en Windows):

```bash
docker --version && node --version && php --version && composer --version && git --version && gh --version
```

Tienes que ver **siete respuestas con números**, algo parecido a esto:

```
Docker version 27.3.1, build ce12230
v22.14.0
PHP 8.3.14 (cli) (built: ...)
Composer version 2.8.4 ...
git version 2.47.0
gh version 2.63.2 (2024-11-20)
```

Los números pueden ser mayores que los del ejemplo: eso está bien. Lo que **no**
puede salir es `command not found` («orden no encontrada»). Si sale eso, ese
programa no se ha instalado: repite su paso o pregunta.

### 1.4 Conectar tu cuenta de GitHub

Una sola vez:

```bash
gh auth login
```

Te hace cuatro preguntas. Responde:

1. `GitHub.com` → Enter
2. `HTTPS` → Enter
3. `Authenticate Git with your GitHub credentials?` → **Y** (sí)
4. `Login with a web browser` → Enter

Te enseña un **código de ocho caracteres** (tipo `A1B2-C3D4`). Cópialo, pulsa
Enter, se abre el navegador, pégalo y autoriza. Al volver a la terminal tienes
que ver:

```
✓ Logged in as tu-usuario
```

Para comprobarlo en cualquier momento: `gh auth status`.

---

## 2. Bajarse el proyecto (y traerse siempre lo último)

La primera vez, y solo la primera:

```bash
cd ~
git clone https://github.com/ateeducacion/wp-psicologia-educativa.git
cd wp-psicologia-educativa
```

Verás muchas líneas de descarga y al final `Resolving deltas: 100% ... done.`
Ya tienes la carpeta `wp-psicologia-educativa` en tu carpeta personal.

**A partir de ahí, cada vez que te sientes a trabajar, lo primero es esto:**

```bash
cd ~/wp-psicologia-educativa
git checkout main
git pull
```

Esto se traduce como: «ponme en la versión buena y bájame lo que hayan hecho
los demás desde la última vez».

**Por qué importa tanto.** El proyecto lo tocan varias personas y varios
agentes. Si empiezas a trabajar sobre una copia de hace tres días, estás
cambiando un texto que a lo mejor ya no existe, o arreglando algo que ya está
arreglado. Cuando luego mandes tu propuesta, GitHub te dirá que hay un
**conflicto** y tocará deshacer el lío a mano. Literalmente la mitad de los
problemas de quien empieza salen de aquí. Treinta segundos de `git pull` los
evitan.

Si al hacer `git pull` sale `Already up to date.`, perfecto: ya estabas al día.

---

## 3. Levantar el entorno

Con **Docker Desktop abierto** (la ballena en marcha), y dentro de
`~/wp-psicologia-educativa`:

```bash
make install
make up
```

- `make install` baja las librerías. Tarda un par de minutos la primera vez y
  suelta mucho texto. Bien es que termine sin la palabra `error`.
- `make up` arranca el WordPress de mentira. **La primera vez puede tardar
  cinco o diez minutos** porque se descarga WordPress entero. No lo cortes.

Cuando acabe, lo último que tiene que aparecer es esto:

```
Entorno listo:
  Sitio:            http://localhost:8958
  Administración:   http://localhost:8958/wp-admin/
  ...
  Credenciales:     admin / password
```

Ábrelo en el navegador: <http://localhost:8958/>, usuario **`admin`**,
contraseña **`password`**. Tienes que ver el aplicativo en castellano, con las
pestañas **Solicitudes de mi zona**, **Solicitudes**, **Estadísticas** y **Zonas**.

Hay más cuentas para probar qué ve cada perfil, todas con la contraseña
`password`: `direccion`, `direccion2`, `psicologia`, `psicologia2` y `coordinacion`.
Están explicadas en el [README](../README.md#usuarios-de-prueba).

Este WordPress **es tuyo y es de mentira**. Puedes romperlo, borrarlo y
rehacerlo cuantas veces quieras: `make clean` lo deja como nuevo. No tiene nada
que ver con el de verdad.

Para pararlo al terminar el día: `make down`.

---

## 4. El ciclo de un cambio

**Este es el apartado importante.** Son cinco pasos, siempre los mismos, y
siempre en este orden.

### Paso 1 — Una rama con nombre descriptivo

Nunca se trabaja directamente sobre `main`. `main` es la versión buena.

```bash
git checkout main
git pull
git checkout -b mostrar-sin-zona
```

El nombre va **en minúsculas, con guiones y diciendo qué haces**:
`mostrar-sin-zona`, `anadir-filtro-por-area`,
`corregir-texto-inscripcion`. Nada de `prueba`, `cambios` ni `rama2`.

Tienes que ver:

```
Switched to a new branch 'mostrar-sin-zona'
```

Para saber en qué rama estás en cualquier momento: `git status`, primera línea.

### Paso 2 — Pedirle el cambio al agente de IA

Abre el agente en esa carpeta y **descríbele qué tiene que pasar, no cómo
hacerlo**. Cuanto más concreto, mejor:

> «En el listado de solicitudes, la columna "Zona" sale vacía cuando el centro
> no tiene zona asignada. Quiero que ponga "Sin zona" en cursiva. Añade el test
> correspondiente.»

Tres cosas que le pides **siempre**:

1. Que **lea `AGENTS.md`** antes de tocar nada: ahí están las reglas de la casa.
2. Que **añada o actualice el test** de lo que cambie.
3. Que **te resuma qué ficheros ha tocado y por qué**.

Y una que **no** haces nunca: aceptar un cambio que no entiendes ni sabes
explicar. Si no puedes contar en una frase qué hace, pregúntale al agente hasta
que puedas, o pregunta al equipo.

### Paso 3 — `make check` ANTES de proponer nada

```bash
make check
```

Esto revisa el estilo del código y pasa toda la batería de pruebas. Tarda unos
minutos. **En verde** termina así:

```
OK (216 tests, 1470 assertions)
```

(El número de tests irá creciendo; lo que importa es la palabra **`OK`** y que
no haya ni `FAILURES` ni `ERRORS`.)

**Si sale en rojo**, verás `FAILURES!`, `ERRORS!` o una lista de ficheros con
avisos de estilo. Entonces:

1. **No mandes nada todavía.** Un PR en rojo hace perder el tiempo a quien
   revisa, y CI lo va a rechazar igual.
2. Copia **todo el texto del error** y pásaselo al agente tal cual: «`make check`
   falla con esto, arréglalo». Suele resolverlo a la primera.
3. Si el error es solo de estilo (líneas que empiezan por `FOUND ... ERROR`),
   prueba `make fix`, que corrige los tabuladores y comillas solo. Después
   vuelve a pasar `make check`.
4. Repite hasta ver `OK`. **Tres intentos y sigue en rojo → para y pregunta.**
   Insistir con el agente sobre un error que no cede suele empeorarlo.

### Paso 4 — Guardar y proponer el cambio

```bash
git add -A
git commit -m "Muestra «Sin zona» en el listado cuando el centro no tiene zona"
git push -u origin mostrar-sin-zona
gh pr create --base main --fill
```

El mensaje del commit va **en castellano** y dice qué cambia, no «cambios» ni
«fix». `gh pr create` te devuelve una dirección tipo
`https://github.com/ateeducacion/wp-psicologia-educativa/pull/42`. Ábrela: ahí está tu
propuesta.

En esa página, al cabo de unos minutos, aparecen las comprobaciones
automáticas. **Un tick verde** significa que el proyecto sigue sano. Una cruz
roja significa que algo falla y hay que arreglarlo antes de nada (vuelve al
paso 3).

### Paso 5 — Ahora le toca al equipo. No a ti.

Esto no es una formalidad, es la regla más importante de todas:

> **Cuando creas el PR, tu parte ha terminado.** A partir de ahí alguien del
> equipo tiene que **revisarlo y aprobarlo**. **No se sube nada a `main` por tu
> cuenta**, ni aunque los tests estén en verde, ni aunque sea «solo un texto»,
> ni aunque tengas prisa.

En concreto, **nunca** ejecutes `git push origin main`, ni pulses el botón
verde de *Merge* de GitHub en tu propio PR, ni `gh pr merge`. Avisa por el
canal del equipo con el enlace del PR y espera.

GitHub lo impone además por su cuenta: `main` tiene una regla que no deja
mezclar un PR sin una aprobación de otra persona y sin `lint` y `test` en
verde, así que el botón de *Merge* sale gris hasta entonces, también para quien
administra el repositorio.

Si te piden cambios, es lo normal y no es un suspenso. Los haces en la misma
rama, repites `make check`, y:

```bash
git add -A
git commit -m "Corrige lo comentado en la revisión"
git push
```

El PR se actualiza solo. No hay que crear otro.

---

## 5. Cuando algo falla

Antes de nada, lo que resuelve un tercio de los casos: **¿está Docker Desktop
arrancado?** Y lo que resuelve otro tercio: `make clean`, que rehace el entorno
desde cero sin tocar tu código.

### Dónde mirar, según lo que pase

| Qué pasa | Dónde mirar |
|---|---|
| La terminal se queja al ejecutar un `make` | El propio texto de la terminal: el error suele estar en las **últimas 20 líneas**, no en las primeras |
| La página de WordPress sale en blanco o con un error | `make logs` y el registro de errores (abajo) |
| Un botón no hace nada, algo se ve descolocado | La **consola del navegador** (abajo) |
| `make check` falla | El propio texto: busca las líneas que empiezan por `FAILURES!`, `ERRORS!` o `FOUND` |

**Los logs del entorno** (lo que dice el servidor):

```bash
make logs
```

Sale mucho texto y no para: se sale con **Ctrl+C**.

**El registro de errores de WordPress**, que es donde aparecen los fallos de
PHP:

```bash
npx wp-env run cli -- tail -50 /var/www/html/wp-content/debug.log
```

Si no devuelve nada, es que no hay errores registrados. Buena señal.

**La consola del navegador**, que es donde aparecen los fallos de JavaScript:
en la página que falla, pulsa **F12** (en Mac, Cmd+Option+I), pestaña
**Console**. Lo que salga **en rojo** es lo que interesa.

### Qué copiar cuando preguntes

Pega estas cuatro cosas. Con menos, quien te ayude tendrá que adivinar:

1. **Qué querías hacer**, en una frase.
2. **La orden exacta** que ejecutaste.
3. **El error entero**, copiado y pegado como texto (no una foto de la
   pantalla, no un recorte: el texto completo).
4. **La rama** en la que estás: la primera línea de `git status`.

Y este bloque, que dice en qué estado está tu máquina:

```bash
git status && git log --oneline -3 && docker ps --format '{{.Names}}'
```

### A quién preguntar

Al canal del equipo del proyecto, con lo de arriba pegado. Si es algo del
WordPress de producción o de datos reales de personas, **no lo publiques en el
canal**: escribe directamente a la persona responsable del proyecto.

Y una vez más: **preguntar pronto no molesta**. Lo que cuesta caro es tirar
media mañana con un error que se resuelve en un minuto.

---

## 6. Lo que nunca hay que hacer

Cinco cosas. Ninguna tiene excepciones.

1. **No tocar el WordPress de producción.** Ni entrar a «probar una cosita», ni
   editar un snippet «solo un momento» en el admin de verdad. Todo se cambia
   aquí, se revisa y se despliega. Un cambio hecho a mano en producción no está
   en ningún sitio y se pierde en el siguiente despliegue.
2. **No subir nada a `main`.** Ni `git push origin main`, ni fusionar tu propio
   PR, ni `gh pr merge`. Siempre rama + PR + revisión de otra persona. GitHub
   lo impide además por su cuenta: `main` está protegida y rechaza el `push`
   directo y el PR sin aprobación.
3. **No subir ficheros con datos personales.** Ni exportaciones de
   inscripciones, ni listados con nombres, correos, DNI o teléfonos, ni
   capturas donde se lean. Ni en el repositorio, ni en un PR, ni en un
   comentario de GitHub. Una vez subido, queda en el historial **para siempre**,
   aunque luego borres el fichero.
4. **No meter en el repositorio nada de `.local/`.** Esa carpeta es material
   descargado de producción, con datos reales, y está deliberadamente fuera del
   control de versiones. Si un agente te propone añadirla, dile que no.
5. **No aceptar del agente un cambio que no entiendes.** Si no sabes explicar en
   una frase qué hace y por qué, no lo mandes. Pregunta.

Si tienes la duda de si algo entra en esta lista: **entra**. Pregunta antes.

---

## Chuleta

```bash
cd ~/wp-psicologia-educativa                     # ir al proyecto
git checkout main && git pull       # ponerse al día  ← SIEMPRE lo primero
git checkout -b nombre-descriptivo  # crear la rama del cambio
make up                             # levantar el entorno (Docker arrancado)
                                    # ... pedirle el cambio al agente ...
make check                          # comprobar    ← SIEMPRE antes del PR
git add -A && git commit -m "..."   # guardar
git push -u origin nombre-descriptivo
gh pr create --base main --fill     # proponer, y esperar a que lo revisen
make down                           # parar el entorno al terminar
```

Y para lo demás, `make help` lista todas las órdenes del proyecto con lo que
hace cada una.
