# AULEC — Protocolo de búsqueda mediante Outlook

## 1. Fuente oficial

El corpus de AULEC se encuentra en Outlook, carpeta:

`Bandeja de entrada/JSONS MENSUALES`

Estructura recomendada:

**1 correo por año → JSON mensuales como adjuntos.**

La fuente primaria para las búsquedas es siempre Outlook y los JSON
mensuales originales adjuntos en los correos anuales. Los archivos
concatenados o copias auxiliares no sustituyen a esta fuente.

## 2. Flujo de búsqueda

Pregunta del usuario  
→ determinar ámbito temporal/conceptual  
→ localizar los correos anuales necesarios  
→ obtener sus JSON mensuales  
→ materializar los JSON completos  
→ procesarlos sin truncamiento  
→ localizar candidatos  
→ analizar contexto completo  
→ comparar resultados  
→ eliminar duplicados conceptuales  
→ responder.

## 3. Búsqueda selectiva

Si la pregunta permite limitar años, procesar solamente esos años.

Ejemplo:

`2024–2026 → 3 correos anuales → JSON mensuales disponibles → búsqueda y análisis.`

No procesar todo el corpus innecesariamente.

## 4. Búsqueda exhaustiva

Si el usuario pregunta:

- «todos los casos»
- «cuántas maneras diferentes»
- «¿hay alguna otra?»
- «comprueba que no exista otra»

realizar un barrido de todos los JSON disponibles.

La exhaustividad debe significar que se han procesado realmente todos los archivos incluidos en el ámbito solicitado.

## 5. Procesamiento

No confundir búsqueda por fragmentos con procesamiento completo.

Para búsquedas exhaustivas:

- obtener el JSON completo;
- recorrer todos sus registros;
- considerar cuerpo, fecha, nombre/email y archivos adjuntos;
- analizar contexto, no solamente coincidencias de palabras.

Los nombres de archivos adjuntos también pueden aportar información relevante.

## 6. Interpretación

Una coincidencia textual no equivale automáticamente a un resultado válido.

Distinguir entre:

- explicación;
- ejemplo;
- repetición de un concepto;
- uso diferente;
- sustitución;
- aplicación práctica;
- referencia incidental.

Cuando varias explicaciones describen el mismo mecanismo, contarlas como un único concepto.

## 7. Estrategia

Prioridad:

1. búsqueda dirigida;
2. ampliar el periodo si es necesario;
3. barrido completo solamente cuando la pregunta lo requiera.

## 8. Principio fundamental

El objetivo no es:

> «buscar una palabra y devolver coincidencias».

El objetivo es:

> «localizar la información relevante en los JSON originales, recuperar los registros completos necesarios, analizar su contexto y sintetizar una respuesta fundamentada».

## 9. Estructura de Outlook

Mantener:

```text
correo anual
└── JSON mensuales
```

No consolidar todos los JSON en un único correo salvo que exista una razón específica para hacerlo.
