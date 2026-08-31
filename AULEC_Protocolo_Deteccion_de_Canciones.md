# AULEC — PROTOCOLO COMPACTO DE DETECCIÓN DE CANCIONES

## 1. OBJETIVO

Detectar **todas las canciones mencionadas en el 100 % del corpus**, minimizar falsos negativos y evitar falsos positivos. La lista canónica vigente es la única autoridad para el nombre final de cada canción.

**Regla central:** máxima sensibilidad al detectar; máxima exigencia al validar.

---

## 2. FUENTES OBLIGATORIAS

Trabajar exclusivamente con:

1. **Lista canónica vigente de canciones** → autoridad de títulos y artistas.
2. **JSON actual de canciones** → estado de partida.
3. **Corpus completo de correos**.

No utilizar versiones antiguas de la lista canónica.

---

## 3. ESTRUCTURA DEL JSON DE CANCIONES

La estructura que se debe conservar es:

```json
{
    "correos": [
        {
            "correo_id": 12345,
            "fecha": "YYYY-MM-DD HH:MM:SS",
            "canciones": [
                {
                    "titulo": "Título canónico",
                    "artista": "Artista canónico"
                }
            ]
        }
    ]
}
```

### Reglas

- `correo_id` identifica el correo.
- `fecha` se conserva.
- `canciones` contiene una lista de objetos canción.
- `titulo` y `artista` deben corresponder exactamente a la forma de la lista canónica.
- Un correo puede tener **0, 1 o varias canciones**.
- No duplicar una canción dentro del mismo correo.
- No eliminar una canción existente porque aparezca otra distinta.
- Durante la detección **NO modificar el JSON**. Primero detectar, resolver y presentar los cambios; modificar solo después de la aprobación.

---

## 4. CAMPOS DEL CORREO QUE SE DEBEN BUSCAR

Revisar en **cada registro**:

- `correo_id`
- `fecha`
- **cuerpo completo**
- **nombres de todos los archivos adjuntos disponibles**

El cuerpo y los adjuntos deben considerarse fuentes independientes: pueden contener canciones diferentes.

---

## 5. RECORRIDO OBLIGATORIO

Procesar **el 100 % de los registros** antes de concluir.

No es válido concluir a partir de:

- una muestra;
- resultados de búsqueda semántica;
- algunos correos relevantes;
- las primeras coincidencias encontradas.

La búsqueda semántica solo puede ayudar a localizar candidatos; **no sustituye el recorrido exhaustivo**.

---

## 6. PASADA 1 — DETECCIÓN DE ALTA SENSIBILIDAD

Buscar candidatos aunque la referencia sea imperfecta:

- título exacto;
- título parcial o abreviado;
- fragmentos del título;
- errores ortográficos evidentes;
- acentos o apóstrofes diferentes;
- mayúsculas/minúsculas;
- puntuación o guiones diferentes;
- contracciones;
- título sin artista;
- artista sin título;
- título en el nombre de un adjunto;
- referencias diferentes en cuerpo y adjunto.

**No decidir automáticamente que una coincidencia es una canción.**

---

## 7. NORMALIZACIÓN Y COMPARACIÓN

Normalizar temporalmente para comparar:

- mayúsculas/minúsculas;
- acentos;
- apóstrofes;
- espacios;
- guiones;
- puntuación;
- errores tipográficos pequeños;
- contracciones.

Después comparar cada candidato contra **todas las canciones completas**.

La normalización sirve para encontrar correspondencias; el resultado final siempre debe utilizar el nombre exacto del catálogo.

---

## 8. TÍTULOS CORTOS Y FRAGMENTOS — CONTROL OBLIGATORIO

Buscar específicamente títulos o palabras que puedan estar contenidos dentro de otros títulos canónicos.

Ejemplos:

- `Stop` → `I Just Can't Stop Loving You`
- `Pride` → `Pride (In the Name of Love)`
- `Good Times` → `Good Times Bad Times`
- `Fire` → `The Unforgettable Fire`

Una palabra parcial **NO es automáticamente una canción**.

Procedimiento:

```text
fragmento
→ buscar TODAS las candidatas canónicas
→ analizar cuerpo completo
→ analizar artista si aparece
→ analizar adjuntos
→ resolver o marcar DUDA REAL
```

Si hay una única candidata compatible con todo el contexto → **RESUELTO**.

Si quedan varias → **DUDA REAL**, salvo que otra evidencia permita eliminar inequívocamente las demás.

---

## 9. JERARQUÍA DE EVIDENCIA

### Muy fuerte

1. título + artista explícitos;
2. nombre de adjunto inequívoco con título/artista;
3. cuerpo y adjunto coinciden;
4. contexto identifica inequívocamente la canción.

### Débil

- palabra aislada;
- palabra genérica;
- fragmento sin contexto;
- coincidencia puramente textual.

Las evidencias débiles no bastan por sí solas.

---

## 10. VARIAS CANCIONES EN UN MISMO CORREO

Un correo puede estudiar varias canciones.

Puede ocurrir:

```text
cuerpo → canción A
adjunto → canción B
```

Resultado:

```text
A + B
```

También:

```text
cuerpo → A
adjunto 1 → A
adjunto 2 → B
```

Resultado:

```text
A + B
```

Nunca eliminar A porque se haya encontrado B.

---

## 11. COMPARACIÓN CON EL JSON ACTUAL

Para cada canción resuelta:

```text
¿ya está asignada correctamente?
    ↓ sí → no modificar
    ↓ no
¿está asignada con nombre canónico incorrecto?
    ↓ sí → proponer corrección
    ↓ no → proponer nueva asignación
```

Ejemplo:

```text
Actual:
Hangar — Megadeth

Canónico:
Megadeth — Hangar 18
```

La asignación final debe ser:

```json
{
    "titulo": "Hangar 18",
    "artista": "Megadeth"
}
```

---

## 12. CLASIFICACIÓN

Cada candidato debe terminar como:

### RESUELTO

La canción canónica es inequívoca.

### DUDA REAL

Quedan varias canciones plausibles y el contexto no permite decidir.

### DESCARTADO

Falso positivo, término técnico, ejercicio, palabra genérica o coincidencia sin respaldo.

---

## 13. PASADA 2 — BÚSQUEDA DE FALSOS NEGATIVOS

Después de la primera pasada, volver a recorrer **el 100 % del corpus** utilizando las 1555 canciones canónicas como patrones.

Buscar especialmente:

- títulos cortos;
- fragmentos;
- errores ortográficos;
- títulos incompletos;
- variantes de escritura;
- referencias solo en adjuntos;
- referencias solo en cuerpo;
- correos con varias canciones.

Todo nuevo candidato debe volver a pasar por la validación contextual.

---

## 14. AUDITORÍA FINAL DE NOMBRES CANÓNICOS

Comparar **todas las canciones del JSON** contra la lista canónica vigente.

Detectar:

- títulos inexistentes en el catálogo;
- artistas incorrectos;
- nombres antiguos;
- títulos abreviados;
- variantes ortográficas;
- duplicados provocados por diferencias de escritura.

Ejemplo:

```text
Pride — U2
↓
Pride (In the Name of Love) — U2
```

La auditoría se hace sobre **todo el JSON**, no solo sobre los registros modificados.

---

## 15. DECISIONES DEL USUARIO

Si el usuario resuelve una ambigüedad, su decisión es la **autoridad contextual para ese caso**.

Si procede, debe aplicarse retroactivamente a casos equivalentes.

Ejemplo:

```text
The Ocean
```

Si el usuario determina:

```text
The Ocean — U2
```

esa resolución prevalece para el caso correspondiente.

---

## 16. INFORME ANTES DE MODIFICAR

Presentar primero:

```text
correo_id |
asignación actual |
referencia encontrada |
candidatas canónicas |
evidencia (cuerpo/adjunto) |
propuesta |
clasificación
```

No modificar el JSON hasta que las propuestas estén aprobadas.

---

## 17. AUDITORÍA MECÁNICA DESPUÉS DE MODIFICAR

Una vez aprobado y modificado el JSON, verificar:

- número total de correos;
- `correo_id` duplicados;
- estructura del JSON;
- estructura de cada objeto de `canciones`;
- canciones duplicadas dentro del mismo correo;
- canciones fuera de la lista canónica;
- artistas/títulos no canónicos;
- variantes antiguas;
- registros modificados;
- que ninguna canción anterior haya desaparecido accidentalmente.

Volver a cargar el JSON desde disco para realizar esta comprobación.

---

## 18. FLUJO COMPLETO

```text
CARGAR FUENTES
      ↓
VERIFICAR CATÁLOGO VIGENTE
      ↓
RECORRER 100 % DEL CORPUS
      ↓
DETECTAR CANDIDATOS (ALTA SENSIBILIDAD)
      ↓
NORMALIZAR
      ↓
COMPARAR CONTRA 1555 CANÓNICOS
      ↓
ANALIZAR CUERPO + ADJUNTOS
      ↓
RESUELTO / DUDA REAL / DESCARTADO
      ↓
COMPARAR CON JSON ACTUAL
      ↓
SEGUNDA PASADA 100 %
      ↓
AUDITORÍA DE NOMBRES CANÓNICOS
      ↓
PRESENTAR CAMBIOS
      ↓
APROBACIÓN DEL USUARIO
      ↓
MODIFICAR JSON
      ↓
AUDITORÍA MECÁNICA FINAL
      ↓
JSON DEFINITIVO
```

## 19. REGLA DE ORO

> **Recorrer todo el corpus, detectar con máxima sensibilidad, comparar cada candidato contra cada una de las canciones de la lista canónica, validar con el contexto completo, considerar cuerpo y adjuntos por separado, resolver las ambigüedades antes de asignar, repetir el recorrido completo para buscar falsos negativos y comprobar finalmente que todo el JSON utiliza nombres canónicos.**

Nunca inventar canciones. Nunca considerar una palabra aislada como canción sin respaldo contextual. Nunca concluir una búsqueda exhaustiva sin haber recorrido el 100 % del corpus.
