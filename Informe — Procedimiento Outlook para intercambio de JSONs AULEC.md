# Informe — Procedimiento Outlook para intercambio de JSONs AULEC

**Objetivo:** utilizar Outlook como canal de entrada y salida de los JSON mensuales que deben ser procesados y etiquetados. El etiquetado y validación del contenido se realiza posteriormente siguiendo el **protocolo de etiquetado registrado en la Biblioteca**.

## 1. Entrada del JSON

2. El JSON mensual se recibe en **Outlook como archivo adjunto** de un correo.

3. Se localiza el correo correspondiente mediante Outlook.

4. Se identifica el archivo adjunto JSON.

5. Se **materializa el archivo completo**, evitando trabajar con una vista o contenido truncado.

6. El JSON se carga y se comprueba que puede ser leído íntegramente.

## 2. Procesamiento

Una vez obtenido el archivo completo:

2. Se procesa el JSON siguiendo **exclusivamente el protocolo de etiquetado de la Biblioteca**.

3. El protocolo determina cómo analizar los correos, sus textos, adjuntos, enlaces y cómo asignar las etiquetas.

4. El resultado será el **JSON mínimo de etiquetas** establecido por el protocolo.

Por tanto, **Outlook no forma parte del proceso de etiquetado**: únicamente proporciona el archivo de entrada y recibe el resultado.

## 3. JSON de salida

El archivo generado tendrá la estructura mínima establecida:

```
{
  "correos": [
    {
          "correo_id": 123,
          "fecha": "....",
          "etiquetas": ["...", "..."]
        }
  ]
}
```

Es decir, se conserva la correspondencia con cada correo mediante:

- `correo_id`

- `fecha`

- `etiquetas`

## 4. Devolución por Outlook

2. Se crea un nuevo correo de salida mediante Outlook.

3. Se adjunta el JSON etiquetado.

4. Se envía al destinatario establecido.

5. El archivo queda disponible en el correo recibido para incorporarlo al sistema de AULEC.

## 5. Prueba realizada

Este circuito ya ha sido **probado de extremo a extremo**:

**Outlook → JSON original → materialización completa → procesamiento/etiquetado → JSON mínimo → Outlook → recepción**

La prueba se realizó con `2011-02-febrero.json`, que contenía **72 correos (IDs 423–494)**.

El JSON etiquetado fue generado y enviado de vuelta mediante Outlook, y has confirmado que **el archivo llegó correctamente**.

### Conclusión

El procedimiento de transporte queda, por tanto, establecido como:

> **Outlook es el buzón de intercambio de los JSON mensuales: recibe el JSON original como adjunto, nosotros lo procesamos siguiendo el protocolo de la Biblioteca y devolvemos por Outlook el JSON mínimo etiquetado como adjunto.**
