# INFORMES DEL PROYECTO AULEC

<!-- Concatenación literal de los informes 01 a 06. -->

<!-- INICIO: INFORMES DEL PROYECTO/01_ARQUITECTURA_DEL_SISTEMA.md -->

# 01_ARQUITECTURA_DEL_SISTEMA

**Proyecto:** AULEC — Aula Electrica  
**Ultima revision:** Agosto 2026  
**Estado:** Documento de arquitectura fija (pocos cambios esperados)

---

## 1. Resumen del proyecto

AULA ELECTRICA es una escuela de clases particulares de guitarra. Cada alumno recibe una clase de 1 hora a la semana. Cada clase se registra con un correo electronico al alumno describiendo lo estudiado y las tareas a realizar durante la semana.

Todos los correos se han centralizado en una base de datos SQLite (`CORREOS.db`) que contiene toda la informacion: correos, adjuntos, destinatarios, fechas y etiquetas.

La base de datos y el servidor que la gestiona se alojan en el hosting de la web de la escuela.

**Objetivo general del sistema AULEC:**

- Permitir que cada alumno acceda online a la informacion de sus correos de clase, archivos adjuntos, tareas y explicaciones.
- Sintetizar respuestas a dudas del alumno.
- Automatizar la creacion del correo de tareas semanales.
- Todo ello usando una IA (OpenAI GPT-4) que traduce lenguaje natural a SQL y puede acceder a toda la informacion de la base de datos.

---

## 2. Estructura de directorios en el hosting

La arquitectura fisica del sistema en el hosting es la siguiente:

```
public_html/
├── AULEC/
│   ├── SERVIDOR.php              # Backend centralizado
│   ├── usuarios.php              # Control de autenticacion y sesiones
│   ├── CORREOS.db                # Base de datos principal (correos y adjuntos)
│   ├── usuarios.db               # Base de datos de usuarios (alumnos y profesor)
│   ├── previsualizar_archivo.php # Endpoint GET: vista previa de adjuntos
│   ├── descargar_archivo.php     # Endpoint GET: descarga de adjuntos
│   ├── miniatura_pdf.php         # Endpoint GET: generacion de miniaturas PDF
│   └── abrir_archivo.php         # Endpoint GET: apertura local de archivos (pendiente)
│
├── interfaz/
│   ├── interfaz.html             # Interfaz principal de usuario (chat, login, resultados)
│   ├── index.php                 # Redireccion con ?nocache= para evitar cache del navegador
│   └── imagenes/                 # Logos, encabezados, iconos y recursos graficos
│
└── .private/
    └── clave.env                 # Archivo protegido con claves API (OpenAI, YouTube)
```

**Notas de despliegue:**

- Las bases de datos (`CORREOS.db`, `usuarios.db`) estan en la carpeta `AULEC/` dentro del hosting.
- La carpeta `.private/` esta protegida mediante `.htaccess` para evitar acceso web directo.
- Las claves API se cargan desde `clave.env` solo en el lado del servidor (PHP).

---

## 3. Componentes principales del sistema

### 3.1. Backend (PHP)

#### SERVIDOR.php

**Funcion:** Backend centralizado que gestiona todas las operaciones del sistema mediante un esquema de "modos".

**Modos de operacion:**

| Modo               | Descripcion                                                        | Requiere sesion                       |
| ------------------ | ------------------------------------------------------------------ | ------------------------------------- |
| `login`            | Autenticacion de usuario contra `usuarios.db`                      | No                                    |
| `logout`           | Cierre de sesion de usuario                                        | No                                    |
| `verificar_sesion` | Comprueba si existe una sesion activa                              | No                                    |
| `metadatos`        | Obtiene metadatos del sistema (ej. lista de emails de alumnos)     | Si (recomendado)                      |
| `execute_query`    | Ejecuta una consulta SQL generada y devuelve resultados            | Si                                    |
| `natural`          | Traduce una consulta en lenguaje natural a SQL usando OpenAI GPT-4 | Si (pendiente validar explicitamente) |

**Funciones clave:**

- `FILTRAR_POR_USUARIO($sql_query)`: Modifica la consulta SQL para filtrar los resultados segun los correos permitidos para el usuario autenticado (rol "alumno" o "profesor").
- `CREAR_TABLA_PERMITIDOS_CONEXION($db)`: Crea una tabla temporal `correos_permitidos` con los `correo_id` accesibles para el usuario en la conexion actual a la base de datos.

**Seguridad:**

- Filtrado automatico de consultas SQL por email del usuario.
- Validacion de sesiones PHP.
- Proteccion contra inyeccion SQL mediante consultas preparadas de SQLite.

#### usuarios.php

**Funcion:** Controla la autenticacion y el acceso de usuarios vinculado a la base de datos `usuarios.db`.

**Responsabilidades:**

- Validar credenciales de usuario (nombre de usuario y contraseña).
- Gestionar el inicio y cierre de sesiones PHP.
- Proporcionar informacion del usuario autenticado (rol, emails asociados, etc.).

#### Endpoints GET de archivos

Estos archivos permiten el acceso seguro a los archivos adjuntos almacenados en la base de datos.

| Archivo                     | Funcion                                                                                             |
| --------------------------- | --------------------------------------------------------------------------------------------------- |
| `previsualizar_archivo.php` | Muestra una vista previa del archivo adjunto en el navegador (ej. imagenes, PDFs, textos).          |
| `descargar_archivo.php`     | Permite la descarga directa de un archivo adjunto.                                                  |
| `miniatura_pdf.php`         | Genera una miniatura (imagen pequeña) de la primera pagina de un archivo PDF.                       |
| `abrir_archivo.php`         | Abre archivos localmente mediante un protocolo instalable (pendiente de implementar completamente). |

**Seguridad:** Todos estos endpoints deben validar la sesion del usuario antes de permitir el acceso a los archivos.

---

### 3.2. Bases de datos (SQLite)

#### CORREOS.db

**Funcion:** Almacena todos los correos electronicos de clases, sus adjuntos y metadatos asociados.

**Tablas principales:**

**Tabla: `correos`**

| Campo                      | Tipo    | Descripcion                                                                                |
| -------------------------- | ------- | ------------------------------------------------------------------------------------------ |
| `correo_id`                | INTEGER | Clave primaria autoincremental                                                             |
| `nombre`                   | TEXT    | Nombre del destinatario del correo                                                         |
| `email`                    | TEXT    | Email del destinatario                                                                     |
| `fecha`                    | TEXT    | Fecha de envio del correo (formato texto)                                                  |
| `Asunto`                   | TEXT    | Asunto del correo                                                                          |
| `cuerpo`                   | TEXT    | Cuerpo del correo en formato HTML                                                          |
| `cuerpo_txt_formateado`    | TEXT    | Cuerpo del correo en texto plano formateado (campo añadido en 2026)                        |
| `enlaces`                  | TEXT    | Lista de enlaces URL extraidos del cuerpo del correo                                       |
| `numero_archivos_adjuntos` | INTEGER | Cantidad de archivos adjuntos asociados a este correo                                      |
| `etiquetas`                | TEXT    | Etiquetas musicales y tecnicas indexadas (campo añadido en 2026)                           |
| `canciones`                | TEXT    | Lista JSON de canciones detectadas, con titulo y artista canonicos (campo añadido en 2026) |

**Estructura del campo `canciones`:**

El campo `canciones` contiene una lista JSON. Cada elemento representa una cancion detectada y contiene dos campos:

- `titulo`: titulo canonico de la cancion.
- `artista`: artista canonico asociado.



**Tabla: `archivos_adjuntos`**

| Campo             | Tipo    | Descripcion                         |
| ----------------- | ------- | ----------------------------------- |
| `archivo_id`      | INTEGER | Clave primaria autoincremental      |
| `correo_id`       | INTEGER | Clave foranea a `correos.correo_id` |
| `nombre_archivo`  | TEXT    | Nombre original del archivo adjunto |
| `archivo_binario` | BLOB    | Contenido binario del archivo       |
| `bytes_archivo`   | INTEGER | Tamaño del archivo en bytes         |

**Relaciones:**

- Un correo (`correos.correo_id`) puede tener cero o mas archivos adjuntos (`archivos_adjuntos.correo_id`).
- Los adjuntos se consultan siempre en relacion con su correo padre.

#### usuarios.db

**Funcion:** Almacena los usuarios del sistema (alumnos y profesor) con sus credenciales y permisos.

**Tabla: `usuarios`**

| Campo            | Tipo | Descripcion                                       |
| ---------------- | ---- | ------------------------------------------------- |
| `usuario`        | TEXT | Nombre de usuario (clave primaria, UNIQUE)        |
| `nombre`         | TEXT | Nombre completo del usuario (UNIQUE, NOT NULL)    |
| `email`          | TEXT | Email del usuario (NOT NULL)                      |
| `password_hash`  | BLOB | Contraseñ¡ª¡ä¡¡ hasheada (NOT NULL)               |
| `acceso_seccion` | TEXT | Rol del usuario: "alumno" o "profesor" (NOT NULL) |

**Notas:**

- El campo `acceso_seccion` determina los permisos del usuario en el sistema.
- Los alumnos solo pueden acceder a sus propios correos (filtrado por email).
- El profesor tiene acceso total a todos los correos y usuarios.

---

### 3.3. Frontend (HTML/JS/PHP)

#### interfaz.html

**Funcion:** Interfaz principal de usuario del sistema AULEC.

**Caracteristicas principales:**

- **Login:** Formulario de autenticacion que consulta al backend con modo `login`.
- **Chat:** Interfaz de chat para consultas en lenguaje natural (traducidas a SQL por la IA).
- **Visualizacion de resultados:** Muestra los correos y adjuntos resultantes de las consultas.
- **Gestion de sesion:**
  - Al cargar la pagina (`window.onload`), verifica si hay una sesion activa mediante `verificar_sesion`.
  - Si hay sesion, restaura el nombre del usuario y el historial del chat desde `localStorage`.
- **Historial:**
  - Se guarda en `localStorage.historial_chat` como un array estructurado.
  - Funcion `borrarHistorial()` para limpiar el historial visual y almacenado.
- **Control por rol:**
  - La variable global `window.rolUsuario` condiciona la visibilidad de ciertos elementos (ej. ocultar la consulta SQL traducida para alumnos).
  - Boton de papelera flotante para borrar el chat y reiniciar la sesion visual.

#### index.php

**Funcion:** Redirecciona a `interfaz.html` con un parametro `?nocache=` para evitar que el navegador cachee la interfaz.

**Uso:**

- El usuario accede a `https://aulaelectrica.com/interfaz/` y `index.php` redirecciona a `interfaz.html?nocache=<timestamp>`.
- Esto asegura que siempre se cargue la ultima version de la interfaz tras actualizaciones.

#### imagenes/ (carpeta)

**Contenido:** Logos, encabezados, iconos y otros recursos graficos usados en la interfaz.

**Ejemplos:**

- Logos de Aula Electrica
- Iconos de acciones (enviar, borrar, descargar, etc.)
- Imagenes de encabezado y fondo

---

### 3.4. Archivos de configuracion y seguridad

#### clave.env

**Ubicacion:** `.private/clave.env`

**Funcion:** Almacena las claves API sensibles del sistema de forma protegida.

**Variables de entorno:**

- `AULEC_OPENAI_API_KEY`: Clave de API de OpenAI para GPT-4.
- `AULEC_YOUTUBE_API_KEY`: Clave de API de YouTube (si se usa para integraciones de video).

**Seguridad:**

- El archivo esta en la carpeta `.private/`, protegida por `.htaccess` para evitar acceso web directo.
- Solo los scripts PHP del servidor pueden leer este archivo.
- Las claves nunca se exponen en el frontend (HTML/JS).

#### .htaccess

**Funcion:** Configuracion de seguridad y reescritura de URLs para el servidor Apache.

**Usos principales:**

- Proteger la carpeta `.private/` de acceso web directo.
- Redireccionar peticiones a los endpoints adecuados.
- Configurar cabeceras de seguridad (ej. `X-Frame-Options`, `Content-Security-Policy`).

---

*Documento de arquitectura fija del proyecto AULEC — Agosto 2026*

<!-- FIN: INFORMES DEL PROYECTO/01_ARQUITECTURA_DEL_SISTEMA.md -->

<!-- INICIO: INFORMES DEL PROYECTO/02_FUNCIONALIDAD_Y_RECURSOS_DEL_SISTEMA.md -->

# 02_FUNCIONALIDAD_Y_RECURSOS_DEL_SISTEMA

**Proyecto:** AULEC — Aula Electrica  
**Ultima revision:** Agosto 2026  
**Estado:** Funcionalidades implementadas y en produccion

---

## 1. Sistema de login y gestion de sesiones

### 1.1. Flujo de autenticacion

1. **Usuario introduce credenciales:**

   - En `interfaz.html`, el usuario escribe su nombre de usuario y contraseña.
   - El frontend envia una peticion POST a `SERVIDOR.php` con modo `login`.

2. **Backend valida credenciales:**

   - `usuarios.php` consulta `usuarios.db` para verificar el usuario y contraseña.
   - Si las credenciales son correctas, se crea una sesion PHP.
   - El backend responde con los datos del usuario: `usuario`, `nombre`, `emails`, `acceso_seccion` (rol).

3. **Frontend almacena datos:**

   - Los datos se guardan en la variable global `usuarioActivo`.
   - Se establece `window.rolUsuario` con el rol del usuario ("alumno" o "profesor").
   - Se muestra el nombre del usuario en la interfaz (ej. encabezado).

### 1.2. Restauracion de sesion tras recargar (F5)

- **Al cargar la pagina (`window.onload`):**

  - El frontend hace un fetch a `SERVIDOR.php` con modo `verificar_sesion`.
  - Si hay una sesion PHP activa, el backend confirma la sesion y devuelve los datos del usuario.
  - El frontend restaura:
    - Nombre del usuario (mostrado en la interfaz).
    - Historial del chat desde `localStorage.historial_chat`.
    - Panel lateral y estado de la interfaz.

- **Evitar duplicaciones:**

  - El mensaje de bienvenida de la IA solo se muestra tras un login nuevo o al borrar el chat.
  - Tras un F5, se actualiza el nombre del usuario sin repetir el mensaje de bienvenida.

### 1.3. Gestion del historial

- **Almacenamiento:**

  - Todo el historial del chat (mensajes de usuario, IA y resultados) se guarda en `localStorage.historial_chat`.
  - El formato es un array estructurado de objetos JSON.

- **Funciones clave:**

  - `borrarHistorial()`: Limpia `localStorage.historial_chat` y vacia la variable en memoria `historialEstructurado`.
  - Al pulsar el boton de papelera flotante, se llama a esta funcion y se muestra de nuevo el mensaje de bienvenida de la IA.

- **Recuperacion:**

  - Al recargar la pagina, se recupera el historial desde `localStorage` y se renderiza en la interfaz.

---

## 2. Uso de OpenAI GPT-4 para traduccion NL → SQL

### 2.1. Integracion con SERVIDOR.php

- **Modo `natural`:**

  - Cuando el usuario escribe una consulta en lenguaje natural (ej. "muestrame los correos de la semana pasada sobre acordes"), el frontend la envia a `SERVIDOR.php` con modo `natural`.
  - `SERVIDOR.php` construye un prompt para la API de OpenAI GPT-4.
  - El prompt incluye:
    - La consulta del usuario en lenguaje natural.
    - El esquema de la base de datos `CORREOS.db` (tablas y campos).
    - Instrucciones para generar SQL valida y segura.

- **Respuesta de la IA:**

  - OpenAI devuelve una consulta SQL generada.
  - `SERVIDOR.php` recibe la SQL y la pasa por la funcion `FILTRAR_POR_USUARIO()` para aplicar el filtrado por email del usuario.

### 2.2. Prompt y contexto enviado a la IA

- **Informacion incluida en el prompt:**
  - Estructura de las tablas `correos` y `archivos_adjuntos`.
  - Lista de campos disponibles (correo_id, nombre, email, fecha, Asunto, cuerpo, enlaces, etc.).
  - Instrucciones para evitar consultas peligrosas (ej. DROP, DELETE, UPDATE).
  - Ejemplos de consultas validas en lenguaje natural y su traduccion a SQL.

---

## 3. Flujos implementados

### 3.1. Flujo completo: login → consulta → ejecucion → respuesta

1. **Login:**

   - Usuario se autentica en `interfaz.html`.
   - Backend valida credenciales y crea sesion PHP.
   - Frontend recibe datos del usuario y los almacena.

2. **Consulta en lenguaje natural:**

   - Usuario escribe una pregunta en el chat.
   - Frontend envia la consulta a `SERVIDOR.php` con modo `natural`.
   - OpenAI GPT-4 traduce la consulta a SQL.

3. **Filtrado por usuario:**

   - La SQL generada pasa por `FILTRAR_POR_USUARIO()`.
   - Se modifica la consulta para incluir un JOIN a la tabla temporal `correos_permitidos` (si el usuario es alumno).
   - El profesor tiene acceso total (no se aplica filtrado).

4. **Ejecucion de la consulta:**

   - `SERVIDOR.php` ejecuta la SQL filtrada contra `CORREOS.db` (modo `execute_query`).
   - Se obtienen los resultados (correos y metadatos).
   - Si hay `correo_id` en los resultados, se realiza una segunda consulta para obtener los adjuntos asociados desde `archivos_adjuntos`.

5. **Respuesta al usuario:**

   - El backend envia un JSON con los resultados y adjuntos al frontend.
   - `interfaz.html` renderiza los correos y adjuntos en el chat.
   - El historial se actualiza en `localStorage.historial_chat`.

### 3.2. Filtrado por usuario (tabla `correos_permitidos`)

- **Creacion de la tabla temporal:**

  - Funcion `CREAR_TABLA_PERMITIDOS_CONEXION($db)` en el backend.
  - Se ejecuta en cada conexion a la base de datos para usuarios con rol "alumno".
  - La tabla contiene solo los `correo_id` cuyo email coincide con los asociados a la sesion del usuario.

- **Modificacion de la SQL:**

  - Funcion `FILTRAR_POR_USUARIO($sql_query)`.

  - Si el usuario es alumno, la SQL se modifica para incluir:

    ```sql
    FROM correos_permitidos JOIN correos USING (correo_id)
    ```

  - Si el usuario es profesor, la SQL no se modifica (acceso total).

- **Seguridad:**

  - El filtrado se aplica una sola vez al obtener la SQL desde la IA (modo `natural`).
  - No se vuelve a aplicar en modo `execute_query` para evitar dobles encapsulados.
  - Este sistema garantiza que ningun alumno acceda a correos que no le correspondan, incluso si manipula la consulta original.

### 3.3. Gestion de adjuntos

- **Consulta separada por `correo_id`:**

  - Tras obtener los resultados de la consulta principal, se verifica si hay `correo_id` en los resultados.
  - Si los hay, se realiza una segunda consulta a `archivos_adjuntos` filtrando por `correo_id`.
  - Los adjuntos se agrupan por correo y se incluyen en el JSON de respuesta al frontend.

- **Endpoints GET para adjuntos:**

  - `previsualizar_archivo.php`: Muestra vista previa del archivo en el navegador.
  - `descargar_archivo.php`: Permite descarga directa del archivo.
  - `miniatura_pdf.php`: Genera miniatura de la primera pagina de un PDF.
  - `abrir_archivo.php`: Abre archivos localmente (pendiente de implementar completamente).

---

## 4. Seguridad actual

### 4.1. Filtrado SQL por email/rol

- **Funcion `FILTRAR_POR_USUARIO($sql_query)`:**

  - Encapsula cualquier consulta SQL en una subconsulta filtrada por emails del alumno.
  - Metodo independiente del contenido de la consulta original.
  - El filtrado se aplica una sola vez al obtener la SQL desde la IA (modo `natural`).

- **Tabla temporal `correos_permitidos`:**

  - Se crea en cada conexion para usuarios con rol "alumno".
  - Contiene solo los `correo_id` cuyo email coincide con los asociados a su sesion.
  - Usuarios con rol "profesor" tienen acceso total (no se crea la tabla).

### 4.2. Validacion de sesiones

- **Modos que requieren sesion:**

  - `execute_query`: Comprueba que existan emails en la sesion y aplica el filtrado por usuario.
  - `metadatos`: Deberia exigir sesion (actualmente recomendado, pendiente validar explicitamente).
  - `natural`: Deberia exigir sesion (actualmente pendiente validar explicitamente).

- **Modos que no requieren sesion:**

  - `login`: Necesariamente debe poder recibirse sin sesion para iniciar sesion.
  - `logout`: Puede recibirse sin sesion; simplemente intenta cerrarla.
  - `verificar_sesion`: Puede recibirse sin sesion; responde indicando que no existe una sesion activa.

### 4.3. Proteccion contra inyeccion SQL

- **Consultas preparadas de SQLite:**

  - Todas las consultas a la base de datos usan consultas preparadas con parametros vinculados.
  - Esto previene inyeccion SQL incluso si un usuario malintencionado intenta manipular las consultas.

- **Validacion de SQL generada:**

  - La SQL generada por la IA se valida antes de ejecutarse.
  - Se rechazan consultas que contengan palabras clave peligrosas (ej. DROP, DELETE, UPDATE, etc.).

### 4.4. Limites de acceso por rol

- **Alumno:**

  - Solo puede acceder a sus propios correos (filtrado por email).
  - No puede ver la consulta SQL traducida (oculta en la interfaz).
  - No tiene acceso a funciones administrativas (ej. gestion de usuarios, metadatos del sistema).

- **Profesor:**

  - Tiene acceso total a todos los correos y usuarios.
  - Puede ver la consulta SQL traducida.
  - Puede acceder a funciones administrativas (pendiente de implementar en la interfaz).

---

## 5. Recursos del sistema

### 5.1. Gestion de historial en localStorage

- **Almacenamiento:**

  - `localStorage.historial_chat`: Array estructurado de objetos JSON con el historial completo del chat.
  - Cada objeto incluye: rol (usuario/ia/resultado), contenido, timestamp, etc.

- **Recuperacion:**

  - Al cargar la pagina, se recupera el historial desde `localStorage` y se renderiza en la interfaz.
  - Se mantiene tambien en memoria en la variable `historialEstructurado`.

- **Limpieza:**

  - Funcion `borrarHistorial()` limpia tanto `localStorage` como la variable en memoria.
  - Al pulsar el boton de papelera flotante, se llama a esta funcion y se limpia el chat visual.

### 5.2. Control por rol (`window.rolUsuario`)

- **Variable global:**

  - `window.rolUsuario` se establece tras el login con el rol del usuario ("alumno" o "profesor").
  - Esta variable condiciona la visibilidad de ciertos elementos en la interfaz.

- **Ejemplos de uso:**

  - Ocultar la consulta SQL traducida para alumnos.
  - Mostrar/ocultar botones o funciones administrativas segun el rol.
  - Condicionar el acceso a ciertas secciones de la interfaz.

---

*Documento de funcionalidades y recursos del proyecto AULEC — Agosto 2026*

<!-- FIN: INFORMES DEL PROYECTO/02_FUNCIONALIDAD_Y_RECURSOS_DEL_SISTEMA.md -->

<!-- INICIO: INFORMES DEL PROYECTO/03_SCRIPTS_DE_APOYO_AL_DESARROLLO.md -->

# 03_SCRIPTS_DE_APOYO_AL_DESARROLLO

**Proyecto:** AULEC — Aula Electrica  
**Ultima revision:** Agosto 2026  
**Estado:** Scripts de gestion y mantenimiento de bases de datos

---

## 1. CREAR CORREOS.db (Etiquetas y cuerpoTXT).py

Script principal para crear o actualizar `CORREOS.db` a partir de archivos MSG exportados de Outlook. Lee los archivos `.msg`, extrae metadatos (remitente, destinatarios, asunto, fecha, cuerpo HTML, adjuntos), convierte el HTML a texto plano formateado (`cuerpo_txt_formateado`), extrae enlaces y busca etiquetas musicales en el contenido para rellenar el campo `etiquetas`. Inserta todo en las tablas `correos` y `archivos_adjuntos` de la base de datos.

---

## 2. CREAR CORREOS.db.py

Version anterior del script para crear `CORREOS.db`. No genera los campos nuevos `cuerpo_txt_formateado` y `etiquetas`. Estado: obsoleto. Se mantiene solo como referencia historica.

---

## 3. USUARIOS.py

Gestion de la base de datos `usuarios.db`. Crea y actualiza usuarios con campos: `usuario`, `nombre`, `email`, `password_hash`, `acceso_seccion` (rol: alumno o profesor). Hashea las contraseñ¡ª¡ä¡¡s antes de guardarlas y asigna el rol de acceso para controlar permisos en el sistema.

---

*Documento de scripts de apoyo al desarrollo del proyecto AULEC — Agosto 2026*

<!-- FIN: INFORMES DEL PROYECTO/03_SCRIPTS_DE_APOYO_AL_DESARROLLO.md -->

<!-- INICIO: INFORMES DEL PROYECTO/04_DOCUMENTACION_Y_APOYO_PARA_IA.md -->

# 04_DOCUMENTACION_Y_APOYO_PARA_IA

**Proyecto:** AULEC — Aula Electrica  
**Ultima revision:** Agosto 2026  
**Estado:** Documentacion y recursos para entrenamiento y contexto de IA

---

## 1. MANUAL-de-ESTILO-de-REDACCION-de-CORREOS-de-DEBERES.md

Guia para la redaccion de correos de tareas semanales. Describe el estilo, tono y estructura que debe seguir el profesor al escribir los correos de deberes. Incluye ejemplos de redaccion, formato recomendado para listas de tareas, referencias a archivos adjuntos, y convenciones para mencionar temas musicales y tecnicos.

---

## 2. 120-PREGUNTAS-y-RESPUESTAS.md

Dataset de entrenamiento para IA con 120 preguntas y respuestas tipicas del Aula Electrica. Cubre dudas frecuentes de alumnos sobre teoria musical, tecnica de guitarra, tareas de clase, y otros temas relacionados con las clases. Se usa como contexto para entrenar o ajustar respuestas de la IA.

---

## 3. LISTA-de-TERMINOS-MUSICALES-y-RECURSOS-TECNICOS.md

Lista de terminos musicales (acordes, escalas, ritmos, tecnicas, etc.) y recursos tecnicos (equipamiento, software, herramientas) usados en las clases del Aula Electrica. Sirve como referencia para la IA al generar respuestas o etiquetar correos.

---

## 4. LISTA_de_ETIQUETAS_para_indexar_correos.json

Archivo JSON con la lista de etiquetas musicales y tecnicas para indexar correos. Se usa en el script CREAR CORREOS.db (Etiquetas y cuerpoTXT).py para buscar terminos en el contenido de los correos y rellenar el campo etiquetas de la tabla correos.

---

## 5. NORMAS-para-los-TEXTOS-en-JSON.md

Documenta el formato esperado para los textos en JSON, especialmente el campo cuerpo_txt_formateado y el campo etiquetas. Incluye convenciones de formateo, estructura de datos, y ejemplos de uso.

---

## 6. FILTRADO POR USUARIO.txt

Documenta el sistema de filtrado SQL por rol de usuario. Explica como se crea la tabla temporal correos_permitidos, como se modifica la SQL para filtrar por email del alumno, y las diferencias de acceso entre alumnos y profesor.

---

## 7. LOGIN Y GESTION DE USUARIOS.txt

Informe detallado del sistema de autenticacion, gestion de sesiones y control por roles. Describe el flujo de login, almacenamiento de datos en frontend, restauracion de sesion tras F5, y gestion del historial del chat.

---

## 8. INDICE-de-CONTENIDOS.md

Archivo con el indice de contenidos de la biblioteca del proyecto. Sirve como referencia rapida para navegar por los diferentes archivos y secciones de la documentacion.

---

*Documento de documentacion y apoyo para IA del proyecto AULEC — Agosto 2026*

<!-- FIN: INFORMES DEL PROYECTO/04_DOCUMENTACION_Y_APOYO_PARA_IA.md -->

<!-- INICIO: INFORMES DEL PROYECTO/05_EN_TRABAJO_ACTUAL.md -->

# 05_EN_TRABAJO_ACTUAL

**Proyecto:** AULEC — Aula Electrica  
**Ultima revision:** Agosto 2026  
**Estado:** Funcionalidades en desarrollo activo

---

## 1. Adaptacion a los dos campos nuevos de CORREOS.db

**Campos añadidos en 2026:**

- `cuerpo_txt_formateado`: Cuerpo del correo en texto plano formateado, generado a partir del HTML original.
- `etiquetas`: Lista de etiquetas musicales y tecnicas indexadas, extraidas del contenido del correo.

**Trabajo realizado:**

- Script `CREAR CORREOS.db (Etiquetas y cuerpoTXT).py` adaptado para generar ambos campos.
- Tabla `correos` actualizada con la nueva estructura.
- Documentacion de la base de datos actualizada (`01_ARQUITECTURA_DEL_SISTEMA.md`).

**Pendiente:**

- Migracion completa de correos antiguos con los nuevos campos.
- Validacion de consistencia en consultas existentes.

---

## 2. Flujo de consultas a la API para informacion y sintesis de respuestas

**Objetivo:** Usar la API de OpenAI GPT-4 no solo para traducir lenguaje natural a SQL, sino tambien para:

- Dar informacion contextual al alumno sobre sus correos y tareas.
- Sintetizar respuestas a dudas del alumno basadas en el contenido de los correos.

**Flujo previsto:**

1. Usuario escribe una duda o consulta en lenguaje natural.
2. Frontend envia la consulta a `SERVIDOR.php` con modo `natural`.
3. OpenAI traduce la consulta a SQL (si es una consulta de base de datos) o genera una respuesta directa (si es una duda conceptual).
4. Si es SQL, se ejecuta y se devuelven los resultados.
5. Si es una respuesta directa, se devuelve el texto generado por la IA.

**Estado:** En desarrollo. Se esta probando la integracion del flujo de 2 llamadas a la API (ver siguiente seccion).

---

## 3. Optimizacion de llamadas a la API - Sistema de 2 pasos

**Objetivo:** Reducir el coste de tokens en las consultas a OpenAI mediante un sistema de prompt condicional con maximo 2 llamadas.

**Flujo:**

1. **1a llamada (~400 tokens):**

   - Prompt basico sin lista de etiquetas.
   - La IA genera SQL o responde "NECESITO_ETIQUETAS" si la consulta requiere filtrado por etiquetas musicales.

2. **2a llamada (~1.600 tokens, solo si hace falta):**

   - Se envia: lista de etiquetas + SQL parcial (si la hay) + consulta original.
   - La IA completa la SQL usando las etiquetas apropiadas.

**Ventajas:**

- Ahorro de tokens en consultas que no requieren etiquetas.
- Solo se usa la llamada larga cuando es estrictamente necesario.

**Estado:** En desarrollo. Se esta probando la implementacion en `SERVIDOR.php`.

---

## 4. Endpoint MCP (Model Context Protocol)

**Ubicacion:** `https://aulaelectrica.com/mcp/` (implementado en `mcp/index.php`)

**Funcion:** Endpoint de la API MCP para integraciones externas (ej. Perplexity, otros clientes MCP). Servira como unica API con capacidad para comunicarse con el servidor y establecer una capa de seguridad mediante la validacion de API Key.

**Caracteristicas:**

- Protocolo: JSON-RPC sobre HTTP
- Transporte: `streamable_http`
- Autenticacion: Por ahora `none` (acceso publico), con validacion de API key leida desde `clave.env`
- Variable de entorno: `AULEC_MCP_API_KEY`

**Flujo probado:**

1. Cliente MCP hace GET `/mcp/` (comprobacion de conectividad).
2. Cliente envia POST con `method: initialize` (inicializacion del protocolo).
3. Cliente envia `notifications/initialized` (notificacion de inicializacion completada).
4. El servidor registra la actividad en `mcp_debug.log` para depuracion.

**Estado:** Endpoint creado y probado basicamente. Falta:

- Validacion completa de API key.
- Integracion con `SERVIDOR.php` para reenviar consultas.
- Pruebas con clientes MCP externos (Perplexity, etc.).

---

## 5. Etiquetado semantico de correos desde chat de IA

**Objetivo:** Usar una IA (ej. Perplexity, GPT-4) para analizar el contenido de los correos y asignar etiquetas semanticas de forma automatica, generando un JSON de etiquetas asignadas que luego se usa para actualizar `CORREOS.db`.

**Flujo previsto:**

1. **Preparacion de datos:**

   - Los correos de `CORREOS.db` se exportan en archivos JSON mensuales (ya implementado).
   - Cada JSON mensual incluye: `correo_id`, `fecha`, `nombre`, `asunto`, `cuerpo_txt_formateado`, `enlaces`, `numero_archivos_adjuntos`, `archivos_adjuntos`.

2. **Procesamiento desde chat de IA:**

   - Se envian los JSONs mensuales a la IA junto con la lista de etiquetas disponibles (`ETIQUETAS_para_indexar_correos.json`).
   - La IA analiza el contenido de cada correo y asigna etiquetas semanticas.
   - La IA genera un **JSON de etiquetas asignadas** con la estructura definida abajo.

3. **Formato del JSON de etiquetas asignadas:**

```json
{
    "correos": [
        {
            "correo_id": 29,
            "fecha": "2024-06-20 22:22",
            "etiquetas": [
                "teoria",
                "escala_armonica",
                "acordes_m7b5",
                "repaso"
            ]
        }
    ]
}
```

- Raiz: objeto con clave `"correos"` que contiene una lista de registros.
- Cada registro incluye obligatoriamente:
  - `correo_id`: numero entero identificador del correo.
  - `fecha`: fecha exacta con formato `YYYY-MM-DD HH:MM`.
  - `etiquetas`: lista de textos con las etiquetas asignadas.
- No es necesario incluir: nombre, email, Asunto, cuerpo, cuerpo_txt_formateado, enlaces, ni archivos_adjuntos.
4. **Actualizacion de la base de datos:**
   - El JSON de etiquetas asignadas generado por la IA se procesa para actualizar el campo `etiquetas` de `CORREOS.db`.
   - Se usa una transaccion SQLite (BEGIN/COMMIT/ROLLBACK) para garantizar consistencia.

**Estado:** La exportacion de JSONs mensuales ya esta implementada. Falta:

- Integracion completa con el chat de IA para etiquetado semantico.
- Validacion del formato de etiquetas generado.
- Actualizacion masiva de `CORREOS.db` con las etiquetas asignadas.

---

## 6. Añadir campo `canciones` a AULEC

### 1. Campo en la base de datos

Añadir a la tabla `correos`:

| Campo       | Tipo   | Contenido                                      |
| ----------- | ------ | ---------------------------------------------- |
| `canciones` | `TEXT` | JSON con las canciones detectadas en el correo |

Ejemplo:

```
[
  {
    "titulo": "Little Wing",
    "artista": "Jimi Hendrix"
  },
  {
    "titulo": "Sultans of Swing",
    "artista": "Dire Straits"
  }
]
```

Si no hay canciones:

```
[]
```

### 2. Estructura del JSON de detección

```
{
  "correos": [
    {
      "correo_id": 12345,
      "fecha": "2026-08-18 12:30",
      "canciones": [
        {
          "titulo": "Little Wing",
          "artista": "Jimi Hendrix"
        }
      ]
    }
  ]
}
```

**Campos obligatorios:** `correo_id`, `fecha`, `canciones`, `titulo`, `artista`.

### 3. Modificaciones necesarias en el proyecto

1. **Modificar** `**CORREOS.db**`: añadir `canciones TEXT` a `correos`.

2. **Modificar el exportador JSON**: incluir `canciones` al exportar los correos.

3. **Crear el proceso de detección**: la IA analiza cada correo y genera el JSON de canciones basándose en el contexto, no solo en coincidencias de palabras.

4. **Procesar los correos históricos**: generar los JSON, validar `correo_id` y `fecha` e incorporar `canciones` a la BD.

5. **Modificar las consultas/API**: permitir consultas por canción, artista, fechas y combinación con `etiquetas`.

### Resumen

`canciones` será un **índice semántico de canciones**, almacenado como JSON dentro de un campo `TEXT`, siguiendo el mismo enfoque que `etiquetas`.

---

*Documento de trabajo en curso del proyecto AULEC — Agosto 2026*

<!-- FIN: INFORMES DEL PROYECTO/05_EN_TRABAJO_ACTUAL.md -->

<!-- INICIO: INFORMES DEL PROYECTO/06_IDEAS_Y_PLANES_FUTUROS.md -->

# 06_IDEAS_Y_PLANES_FUTUROS

**Proyecto:** AULEC — Aula Electrica  
**Ultima revision:** Agosto 2026  
**Estado:** Ideas y planes no implementados (prioridad por definir)

---

## 1. Mejora de la seguridad general

**Objetivo:** Reforzar la seguridad del sistema en multiples capas.

**Mejoras previstas:**

- **Exigir sesion valida en todos los modos de SERVIDOR.php:**

  - Actualmente `metadatos` y `natural` no validan sesion explicitamente.
  - Se requiere validar sesion en todos los modos excepto `login`, `logout` y `verificar_sesion`.

- **Validar sesion en todos los endpoints GET:**

  - `previsualizar_archivo.php`, `descargar_archivo.php`, `miniatura_pdf.php`, `abrir_archivo.php`.
  - Actualmente no todos exigen sesion activa.

- **Rotacion de claves API:**

  - Implementar mecanismo para rotar `AULEC_OPENAI_API_KEY` y otras claves periodicamente.
  - Registrar intentos fallidos de acceso.

- **Limites de tasa (rate limiting):**

  - Limitar numero de peticiones por minuto/hora por usuario o IP.
  - Prevenir abuso o ataques de fuerza bruta.

- **Logs de auditoria:**

  - Registrar intentos de acceso fallidos.
  - Registrar consultas SQL ejecutadas por usuario.
  - Alertas por comportamiento sospechoso.

**Estado:** No comenzado.

---

## 2. MCP como embudo API mas robusto

**Objetivo:** Convertir el endpoint MCP en la unica puerta de entrada segura para integraciones externas.

**Mejoras previstas:**

- **X-API-Key privada y solo por HTTPS:**

  - Todas las peticiones deben incluir `X-API-Key` en las cabeceras.
  - Forzar uso exclusivo de HTTPS para todas las comunicaciones.

- **Control de permisos y rol:**

  - Validar no solo la API key, sino tambien el contexto del usuario (rol, permisos).
  - Limitar modos disponibles segun el rol.

- **Limites de consultas:**

  - Limitar tamaño maximo de consultas SQL.
  - Limitar numero de resultados por consulta.
  - Limitar frecuencia de peticiones por cliente.

- **Registro de actividad:**

  - Log detallado de todas las peticiones MCP.
  - Alertas por intentos fallidos o patrones sospechosos.

**Estado:** Endpoint MCP creado, pero estas mejoras no estan implementadas.

---

## 3. Automatizacion IMAP + cron para insercion automatica de correos

**Objetivo:** Automatizar el registro de correos enviados con asunto "DEBERES" en la base de datos, sin macros ni intervencion manual.

**Flujo previsto:**

1. **Envio del correo:**

   - Usuario envia correo desde Outlook → se guarda en carpeta "Enviados".

2. **Cliente IMAP en el servidor:**

   - Script PHP (llamado mediante cron job cada X minutos) se conecta al buzon por IMAP.
   - Filtra en "Enviados" los correos con asunto que contiene "DEBERES".
   - Filtra por fecha posterior a la ultima registrada en la base.

3. **Copia a carpeta especifica:**

   - Script copia correos filtrados a carpeta "ParaBase".
   - Asi se separan los correos pendientes de procesar.

4. **Procesamiento desde ParaBase:**

   - Script procesa correos en ParaBase:
     - Extrae asunto, destinatarios, fecha, cuerpo HTML, adjuntos.
     - Aplica limpieza y formateo (como el script Python actual, migrado a PHP).
     - Inserta contenido en `CORREOS.db` + tabla `archivos_adjuntos`.

5. **Gestion del estado:**

   - Si el correo se inserta correctamente → se borra de ParaBase.
   - Si falla → permanece en ParaBase como señal visual del fallo.

**Ventajas:**

- Sin macros ni reglas complejas de Outlook.
- Todo controlado desde el servidor.
- Facil de supervisar: lo que queda en ParaBase son fallos.
- Compatible con hosting actual (solo PHP + cron).

**Estado:** No comenzado.

---

## 4. Activar / desactivar usuarios (alta y baja en la escuela)

**Objetivo:** Poder activar o desactivar usuarios cuando se dan de alta o baja en la escuela, sin eliminar sus datos historicos.

**Funcionalidad prevista:**

- **Campo nuevo en `usuarios.db`:**

  - Añadir campo `activo` (BOOLEAN: 1 = activo, 0 = desactivado).
  - O campo `fecha_baja` (NULL = activo, fecha = desactivado).

- **Interfaz de gestion:**

  - Panel para el profesor con lista de usuarios.
  - Boton o interruptor para activar/desactivar cada usuario.
  - Filtro para mostrar solo usuarios activos o todos.

- **Comportamiento:**

  - Usuarios desactivados no pueden iniciar sesion.
  - Sus correos y datos historicos se mantienen en la base de datos.
  - Se pueden reactivar en cualquier momento.

**Estado:** No comenzado.

---

## 5. Adaptacion a movil de la interfaz

**Objetivo:** Adaptar `interfaz.html` para visualizacion optima en pantallas de moviles y tablets.

**Mejoras previstas:**

- **Disenno responsivo:**

  - Media queries CSS para diferentes tamanos de pantalla.
  - Menu lateral colapsable en pantallas pequenas.
  - Chat y resultados adaptables al ancho de pantalla.

- **Toque y gestos:**

  - Botones mas grandes para facilitar el toque.
  - Gestos basicos (deslizar para borrar, etc.).

- **Rendimiento:**

  - Optimizacion de carga en redes moviles.
  - Lazy loading de adjuntos y resultados.

**Estado:** No comenzado.

---

## 6. Notificaciones, filtros, favoritos y etiquetas en la interfaz

**Objetivo:** Mejorar la experiencia de usuario con funcionalidades adicionales en la interfaz.

**Funcionalidades previstas:**

- **Notificaciones:**

  - Aviso cuando hay nuevos correos o tareas.
  - Notificaciones push en el navegador (si es posible).

- **Filtros:**

  - Filtrar correos por fecha, etiquetas, remitente, etc.
  - Busqueda avanzada con multiples criterios.

- **Favoritos:**

  - Marcar correos como favoritos para acceso rapido.
  - Lista de favoritos accesible desde el panel lateral.

- **Etiquetas visuales:**

  - Mostrar etiquetas de cada correo como badges o tags.
  - Filtrar por etiqueta haciendo clic en ellas.

**Estado:** No comenzado.

---

## 7. Protocolo para abrir archivos localmente desde el navegador

**Objetivo:** Implementar un protocolo instalable que permita abrir archivos adjuntos directamente con su aplicacion asociada en el ordenador del usuario, sin tener que descargarlos manualmente.

**Funcionamiento previsto:**

- **Protocolo personalizado:**

  - Registrar un protocolo personalizado en el sistema operativo (ej. `aulaelectrica://`).
  - Similar a como funcionan `mailto:`, `tel:`, o protocolos de aplicaciones como Zoom o Spotify.

- **Flujo:**

  1. Usuario hace clic en un enlace tipo `aulaelectrica://abrir?archivo_id=123`.
  2. El navegador pregunta si se permite abrir la aplicacion asociada.
  3. La aplicacion local recibe el `archivo_id` y lo solicita al servidor.
  4. El servidor valida la sesion y devuelve el archivo.
  5. La aplicacion local guarda el archivo en un directorio temporal y lo abre con la aplicacion asociada (PDF reader, editor de texto, reproductor de audio, etc.).

- **Ventajas:**

  - Experiencia de usuario mas fluida (un clic en vez de descargar y buscar el archivo).
  - Los archivos se abren directamente en su aplicacion nativa.
  - Mejor integracion con el flujo de trabajo local del alumno.

- **Implementacion:**

  - Script `abrir_archivo.php` actualmente pendiente de implementar completamente.
  - Requiere una aplicacion ligera local que registre el protocolo y gestione la apertura.

**Estado:** No comenzado (endpoint `abrir_archivo.php` existe pero no implementado completamente).

---

*Documento de ideas y planes futuros del proyecto AULEC — Agosto 2026*

---

## 8. Conversión de los cuerpos HTML a Markdown

- Sustituir el texto plano del campo `cuerpo_txt_formateado` por Markdown, convirtiendo directamente `msg.htmlBody` después de limpiarlo con `BeautifulSoup`. Así se conservarán mejor las tablas, listas, negritas, enlaces y saltos de párrafo para su análisis mediante la API de IA.
- Para los correos antiguos, reprocesar los archivos `.msg` originales y reconstruir la base de datos tras realizar una copia de seguridad. No es necesario modificar la estructura SQLite, ya que `cuerpo TEXT` puede almacenar Markdown. Adaptar también la exportación de los JSON mensuales y mantener marcadores específicos para imágenes y adjuntos.

<!-- FIN: INFORMES DEL PROYECTO/06_IDEAS_Y_PLANES_FUTUROS.md -->
