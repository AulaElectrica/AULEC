# # Protocolo de procesado de JSONs mensuales — Etiquetado AULEC

**Objetivo:** garantizar 100% de cobertura, 100% de fidelidad al catálogo y minimizar omisiones sistemáticas al etiquetar los archivos mensuales.

---

## Fase 1 — Ingesta y verificación de integridad

1. El archivo debe subirse siempre como adjunto (nunca pegado en el chat), para poder leerlo con Python vía `bash_tool` en vez de con `view` (que trunca a partir de ~16.000 caracteres).
2. Verificar automáticamente:
   - Número total de correos del mes.
   - Que todos los `correo_id` sean correlativos o al menos sin duplicados.
   - Que todos los correos tengan los 8 campos esperados.
3. Volcar el texto completo de todos los correos a un archivo de trabajo intermedio y leerlo íntegro por bloques con `view` antes de etiquetar nada.

---

## Fase 2 — Lectura completa de cada correo

Para cada correo, revisar conjuntamente estas tres fuentes:

1. **`cuerpo_txt_formateado` completo**, incluyendo tablas y listas.
2. **`archivos_adjuntos`**, revisando el nombre completo de todos los archivos. Sus nombres pueden aportar información semántica válida para etiquetar aunque el término no aparezca en el cuerpo, siempre que sea coherente con el contexto del correo.
3. **`enlaces`**, cuando aporten información relevante.

No limitar el etiquetado a coincidencias literales del cuerpo: utilizar toda la información relevante proporcionada por las tres fuentes.

---

## Fase 3 — Checklist de etiquetado

Asignar etiquetas utilizando exclusivamente el catálogo oficial.

Revisar **cada correo contra todos los grupos**:

- [ ] `tipos_de_contenido`
- [ ] `tipos_de_ejercicios`
- [ ] `teoria_y_armonia`
- [ ] `tipos_de_acorde`
- [ ] `tipos_de_escala`
- [ ] `tecnica_y_ejecucion`
- [ ] `improvisacion`
- [ ] `estilos`
- [ ] `ritmo_y_metrica`
- [ ] `conceptos_transversales`

Antes de cerrar cada correo:

- [ ] Se ha leído todo el cuerpo, incluidas tablas y listas.
- [ ] Se han revisado **todos los nombres de los adjuntos**.
- [ ] Se ha comprobado si algún adjunto aporta etiquetas que no aparecen en el cuerpo.
- [ ] Se han revisado los enlaces relevantes.
- [ ] Se han considerado los conceptos específicos dentro de listas y tablas aunque ya exista una etiqueta general.
- [ ] Las menciones a `progresión/progresiones` se han valorado como `progresion_de_acordes` cuando se refieren a una secuencia armónica.
- [ ] Se han incluido todas las etiquetas justificadas, sin añadir etiquetas por asociación débil.
- [ ] Cualquier duda queda registrada para revisión.

---

## Fase 4 — Inferencia de estilo por canción

Si no se menciona explícitamente el género pero se cita una canción conocida, se permite inferir el estilo cuando la identificación sea inequívoca.

Si existe duda razonable sobre el género, no forzar la etiqueta.

---

## Fase 5 — Validación técnica automática

Antes de entregar, comprobar siempre:

1. Todos los `correo_id` originales están presentes, sin faltar ni sobrar.
2. Todas las etiquetas utilizadas existen literalmente en el catálogo vigente.
3. Ningún correo tiene una lista de etiquetas vacía sin revisión manual explícita.

---

## Fase 6 — Revisión final de coherencia

Antes de entregar, revisar especialmente los correos con mayor longitud o mayor número de adjuntos.

Buscar:

- Etiquetas de ejercicios que puedan haberse omitido.
- Términos concretos del texto o de los adjuntos que no hayan generado su etiqueta correspondiente.
- Etiquetas añadidas por asociación intuitiva sin respaldo suficiente.

---

## Fase 7 — Entrega

Entregar el JSON en el siguiente formato:

```
{
    "correos": [
        {
            "correo_id": 29,
            "fecha": "2024-06-20 22:22",
            "etiquetas": [
                "teoria",
                "escala_menor_armonica",
                "acorde_dominante",
                "ejercicio"
            ]
        }
    ]
}
```

Informar brevemente de cualquier correo extraño, duda o etiqueta obtenida mediante inferencia para que el profesor pueda decidir el criterio.