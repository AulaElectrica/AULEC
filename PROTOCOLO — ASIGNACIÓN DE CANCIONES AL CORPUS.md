# PROTOCOLO — ASIGNACIÓN DE CANCIONES AL CORPUS

## 0. RECUPERACIÓN — OBLIGATORIA

- Procesar **el 100 % de los correos** del año.
- Ignorar completamente el campo `canciones` existente.
- En **cada correo**, revisar: **cuerpo completo + nombre de TODOS los adjuntos**.
- Comparar contra **las lista de canciones canónicas completas**.
- Admitir coincidencias normalizadas, abreviadas, deformadas, errores y extensiones (`.gp5`, `.gp3`, etc.).

## 1. NIVEL 1

**Título + Artista inequívocos en el cuerpo o en el nombre de algun archivo adjunto → ASIGNAR.**

## 2. NIVEL 2

**Titulo inequívoco, pero artista no determinado**:

- Si el titulo es **ambiguo** o sea:

  - En inglés, pudiendo mencionar un estilo o termino común en ingles →**DUDOSO**.

  - **IMPORTANTE**: En español, formando parte gramatical, sintáctica y semánticamente del párrafo del cuerpo porque se ha confundido con el texto normal del cuerpo→**NO ASIGNAR**.
    Casos tipicos que generan esa confusion:

    - 'Black' de Pearl Jam.

    - 'Aprendiz' de Malú.

    - 'Just' de Radiohead

    - 'Stop!' de Sam Brown.

    - 'Mayo' de The Black Moiras.

    - 'Fire' de U2.

    - 'Dom' de Santana.

    - 'One' de Metallica.

    - 'Brothers' de Yngwie Malmsteen.

    - 'Agradecido' de Rosendo

    - 'Triste' de Jobim

    - 'Ansiedad' de Nat King Cole

    - 'El Unico' de Catriel y Paco amoroso

    - 'Sucede' y 'Decidí' de Extromoduro.

    - 'Vicio' de Reincidentes.

    - 'Numb' de Linkin Park.

    **AÑADIDO — Títulos autosuficientes:**  
    Cuando un título previamente identificado como **autosuficiente** se detecte completo y de forma independiente, podrá asignarse aunque no se haya determinado el artista. Este añadido no modifica el tratamiento del resto de casos del Nivel 2.

- Si el título **no es ambiguo**:

  1. Revisar si ya hay una candidata previa mejor que tenga mas coincidencias→**NO ASIGNAR**.

  2. Si no hay una candidata previamente asignada, entonces buscar contexto en los 2 correos anteriores y posteriores del mismo destinatario para encontrar posible nombre de artista, y si no se encuentra, proponer las canciones candidatas de la lista y todas en→**DUDOSO.**

## 3. NIVEL 3

**Título claro en cuerpo + cifrado con `|` o `-` como separadores de compases.**

Si el artista se obtiene inequivocamente del contexto del cuerpo→ **ASIGNAR.**

Si no se puede determinar el artista, asignar las canciones candidatas de la lista y→ **DUDOSO**.

## 4. NIVEL 4

**Título abreviado, deformado o erróneo en el cuerpo sin adjunto y sin mencion al artista** → **DUDOSO**.

## 5. CONTROL FINAL

Antes de terminar:

- comprobar que se recorrieron **todos los correos**;
- comprobar que se revisaron **todos los adjuntos**;
- comprobar que se utilizó **toda la lista canónica**;
- comprobar que no existen duplicados `correo_id + canción`.

**REGLA PRINCIPAL: PRIMERO RECUPERAR TODO. DESPUÉS CLASIFICAR.**

## 6. ENTREGA DE RESULTADOS

Al finalizar todo el proceso, descargar un unico excel con las siguientes columnas:

- Correo_id

- Nombre destinatario

- Cancion detectada

- Asignada o DUDOSA

- Regla nivel del protocolo que decidió la asignacion o dudosidad, y breve descripcion.

- evidencia

- Canciones de la lista canonica candidatas a resolver la duda.
