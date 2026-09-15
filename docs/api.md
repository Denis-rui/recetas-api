# Contrato HTTP de ¿Qué preparamos?

Contrato del código revisado el 13 de septiembre de 2026. Backend Laravel 13.31.0, Sanctum 4.3.3 y PHP CLI 8.5. La aplicación React Native/Expo todavía no existe. Los ejemplos describen solicitudes y respuestas del backend; los identificadores son ilustrativos y deben sustituirse por registros existentes.

## 1. Dirección base, autenticación y formato

Las rutas siguientes parten de `/api/v1`, salvo cuando se indica una ruta web. El host depende del despliegue y de `APP_URL`; este documento no confirma un dominio público operativo.

Para JSON, enviar `Accept: application/json` y `Content-Type: application/json`. Para archivos, usar `multipart/form-data` y dejar que el cliente genere su boundary. Las rutas protegidas requieren `Authorization: Bearer <token>` y una cuenta activa. El panel administrativo utiliza sesión web y protección CSRF; un token móvil no sustituye ese inicio de sesión.

Los recursos de consulta suelen estar en `data`. Las operaciones pueden devolver `mensaje`, `usuario`, `receta` o `solicitud`; las tablas de este documento identifican cada envoltorio. Los tiempos presentes en Resources se expresan como cadenas ISO 8601 o `null`.

### Identificadores

Enviar enteros positivos o su representación decimal canónica: `1` o `"1"`. Se rechazan decimales, notación científica, signos, ceros iniciales, espacios y valores superiores al entero admitido por PHP. Ejemplos inválidos: `1.9`, `"1e2"`, `"+1"`, `"01"`, `" 1 "`. En rutas suelen producir 404; en campos o filtros, 422. No se truncan a otro identificador.

### Errores

| Estado | Significado                                                                                                                |
| ------ | -------------------------------------------------------------------------------------------------------------------------- |
| 400    | Solicitud HTTP que el servidor no puede interpretar.                                                                       |
| 401    | Falta autenticación, token inválido/vencido/revocado o login rechazado.                                                    |
| 403    | Cuenta deshabilitada detectada con acceso residual o falta de permiso.                                                     |
| 404    | Ruta/recurso no encontrado o no disponible en ese contexto de consulta.                                                    |
| 409    | Conflicto de versión al actualizar una receta privada.                                                                     |
| 422    | Validación o regla funcional incumplida; incluye versión de corrección desactualizada y clave de idempotencia reutilizada. |
| 429    | Límite de solicitudes; respetar la cabecera `Retry-After`.                                                                 |
| 500    | Error del servidor; no confirma eliminación ni éxito de una escritura.                                                     |

Las excepciones de rutas `api/*` se presentan como JSON incluso sin cabecera `Accept`. Se mantiene la compatibilidad existente: los mensajes de las acciones HTTP usan normalmente `mensaje`, mientras que las excepciones de Laravel utilizan `message`. Un cliente debe leer `mensaje ?? message` y no depender del texto exacto ni de su idioma para decidir el resultado.

Ejemplo de validación:

```json
{
    "message": "La cantidad por página debe estar entre 1 y 50.",
    "errors": {
        "per_page": ["La cantidad por página debe estar entre 1 y 50."]
    }
}
```

Los errores 429 incluyen `mensaje`, `retry_after` en segundos y `Retry-After`. El estado HTTP es la referencia principal. Ni un 404 genérico ni un fallo de conexión demuestran que una receta fue eliminada.

## 2. Cuentas, acceso y recuperación

| Método y ruta                         | Entrada                                                   | Éxito                                                       |
| ------------------------------------- | --------------------------------------------------------- | ----------------------------------------------------------- |
| `POST /auth/registro`                 | `name`, `email`, `password`, `password_confirmation`      | 201: `mensaje`, `usuario`.                                  |
| `POST /auth/login`                    | `email`, `password`; `dispositivo` opcional               | 200: `mensaje`, `token`, `token_type: "Bearer"`, `usuario`. |
| `POST /auth/logout`                   | Sin cuerpo; autenticado                                   | 200: `mensaje`; revoca el token utilizado.                  |
| `POST /auth/recuperacion/solicitar`   | `email`                                                   | 200: `mensaje` genérico.                                    |
| `POST /auth/recuperacion/verificar`   | `email`, `codigo` como cadena de seis dígitos             | 200: `mensaje`, `token_recuperacion`.                       |
| `POST /auth/recuperacion/restablecer` | `token_recuperacion`, `password`, `password_confirmation` | 200: `mensaje`; exige nuevo login.                          |

El registro crea una cuenta activa con rol `usuario` desde el servidor y no inicia sesión. Nombre y correo admiten hasta 255 caracteres; las contraseñas nuevas requieren al menos 12 caracteres y confirmación. `dispositivo` admite hasta 255 caracteres.

`usuario` contiene `id`, `name`, `email`, `foto_perfil_url` nullable, `rol` y `activo`. No contiene hash, tokens persistidos ni atributos internos de seguridad. Un login rechazado responde 401 con el mismo mensaje para credenciales incorrectas y cuentas inactivas.

La expiración de los tokens se configura mediante `SANCTUM_EXPIRATION`, en minutos; el valor predeterminado es 43200, equivalente a 30 días. No hay endpoint de renovación: corresponde iniciar sesión otra vez. Deshabilitar una cuenta o cambiar/restablecer su contraseña revoca los accesos anteriores. Reactivar no recupera esos tokens. La emisión del token comprueba el usuario vigente bajo bloqueo, coordinada con las operaciones de revocación.

La recuperación emplea un código con vigencia de diez minutos y hasta cinco intentos fallidos por recuperación. Al verificarlo se consume el código y se entrega una autorización de un solo uso con vigencia de quince minutos. `token_recuperacion` se envía en el cuerpo del restablecimiento; no es un Bearer token. Cuentas inexistentes o deshabilitadas reciben el mismo mensaje de solicitud sin generar un código. Solicitar un código nuevo invalida recuperaciones anteriores.

La verificación de propiedad del correo en registro o cambio de correo no está implementada. No confundirla con la recuperación por código.

## 3. Perfil propio

Todas estas rutas requieren autenticación y cuenta activa.

| Método y ruta          | Entrada                                                                | Respuesta 200                                               |
| ---------------------- | ---------------------------------------------------------------------- | ----------------------------------------------------------- |
| `GET /perfil`          | —                                                                      | `data` con el recurso de usuario.                           |
| `PATCH /perfil`        | `name` y/o `email`; `current_password` obligatorio si cambia el correo | `mensaje`, `usuario`.                                       |
| `GET /perfil/foto`     | —                                                                      | 200: archivo de imagen; 404 si la cuenta no tiene foto.     |
| `POST /perfil/foto`    | Archivo `foto_perfil`                                                  | `mensaje`, `foto_perfil_url`.                               |
| `PUT /perfil/password` | `current_password`, `password`, `password_confirmation`                | `mensaje`; revoca también el token que realizó la petición. |

La contraseña nueva debe ser diferente de la actual. La foto admite JPEG/JPG, PNG o WEBP y hasta 2048 KiB. Existe además `GET /api/user`, fuera del prefijo v1, como consulta protegida anterior que devuelve el mismo perfil en `data`.

## 4. Catálogo público y paginación

| Método y ruta                  | Parámetros                                                     | Respuesta                                                                 |
| ------------------------------ | -------------------------------------------------------------- | ------------------------------------------------------------------------- |
| `GET /categorias`              | —                                                              | 200: `data` con `{id, nombre}`; sin paginación, ordenado por nombre e ID. |
| `GET /ingredientes`            | `buscar`, `page`, `per_page`                                   | 200: colección paginada de `{id, nombre}`.                                |
| `GET /recetas`                 | `buscar`, `categoria_id`, `ingredientes[]`, `page`, `per_page` | 200: catálogo paginado.                                                   |
| `GET /recetas/{receta}`        | ID canónico                                                    | 200: detalle en `data`.                                                   |
| `GET /recetas/{receta}/imagen` | ID canónico                                                    | 200: archivo; 404 si la receta o su imagen no están disponibles.          |

El catálogo contiene únicamente recetas publicadas y no eliminadas. Mantiene visibles las publicaciones de autores deshabilitados. Las recetas privadas ajenas, eliminadas e inexistentes no se distinguen mediante la consulta pública de detalle.

Los listados de ingredientes, recetas, favoritos, recetas propias y solicitudes propias utilizan `page` desde 1 y `per_page` entre 1 y 50, con 15 por defecto. La página tiene además un límite técnico para evitar desbordamientos: `intdiv(PHP_INT_MAX, 50)`. Valores inválidos producen 422. Categorías es la excepción: no está paginada. Los listados paginados devuelven `data`, `links` y `meta`; `meta` incluye `current_page`, `last_page`, `per_page`, `from`, `to` y `total`. Una página sin resultados devuelve una lista vacía.

`buscar` admite hasta 100 caracteres. La búsqueda es por nombre. `categoria_id` debe existir. `ingredientes` es una lista de hasta 50 IDs existentes, sin duplicados; una lista vacía no activa el filtro.

Ejemplo de búsqueda:

```text
GET /api/v1/recetas?ingredientes[]=1&ingredientes[]=2&per_page=15&page=1
```

Con ingredientes seleccionados se exige al menos una coincidencia, se ordena primero por menor cantidad de ingredientes faltantes y después por publicación e ID descendentes. No se comparan cantidades. Cada resultado incorpora `ingredientes_disponibles`, `ingredientes_faltantes`, `cantidad_coincidencias` y `cantidad_faltantes`. Esta selección no se guarda como inventario.

### Recursos de receta

El catálogo devuelve `id`, `nombre`, `descripcion`, `imagen_url`, `porciones`, `tiempo_preparacion`, `categorias`, `valoracion_promedio` nullable, `cantidad_valoraciones`, `ingredientes_resumen` de hasta tres elementos y `cantidad_ingredientes`.

El detalle añade `tips` nullable, `ingredientes` y `pasos`. Un ingrediente contiene `ingrediente_id`, `nombre`, `cantidad` nullable, `unidad` nullable, `notas` nullable y `orden`. Un paso contiene `orden` e `instruccion`. El detalle público no expone rutas internas de archivos ni información privada del autor.

## 5. Recetas propias

Todas estas rutas requieren autenticación y propiedad. Un administrador no obtiene acceso a recetas privadas ajenas mediante `mis-recetas`. En estas rutas propias se conserva el comportamiento 403 para recursos existentes de otra cuenta; un ID inexistente devuelve 404.

| Método y ruta                                 | Entrada                                                                                 | Éxito                                                                |
| --------------------------------------------- | --------------------------------------------------------------------------------------- | -------------------------------------------------------------------- |
| `GET /mis-recetas`                            | `filtro`: `todas`, `privadas`, `publicadas`, `eliminadas`; `buscar`, `page`, `per_page` | 200: colección paginada propia.                                      |
| `POST /mis-recetas`                           | Receta completa y archivo `imagen`                                                      | 201: detalle propio en `data`, privada, versión 1.                   |
| `GET /mis-recetas/{receta}`                   | —                                                                                       | 200: detalle propio en `data`.                                       |
| `PUT`, `PATCH` o `POST /mis-recetas/{receta}` | Receta completa, `version`; imagen opcional                                             | 200: detalle actualizado en `data`.                                  |
| `DELETE /mis-recetas/{receta}`                | —                                                                                       | 200: `mensaje`, `receta` con metadatos de eliminación.               |
| `GET /mis-recetas/{receta}/imagen`            | —                                                                                       | 200: imagen vigente del autor.                                       |
| `POST /mis-recetas/{receta}/imagen`           | Archivo `imagen`                                                                        | 200: `mensaje`, `imagen`, `imagen_url`.                              |
| `POST /mis-recetas/{receta}/publicar`         | `clave_idempotencia` UUID                                                               | 200 publicación administrativa directa; 201 nuevo envío a revisión.  |
| `POST /mis-recetas/{receta}/corregir`         | `clave_idempotencia`, `version_base`, `contenido`                                       | 201: `mensaje`, `solicitud` pendiente, también para administradores. |

Las tres variantes de actualización de receta privada exigen el contenido completo aunque se use PATCH. Una versión desactualizada produce 409. No se puede actualizar por esta vía una receta publicada, eliminada o con envío pendiente. Para editar una privada enviada debe cancelarse primero la solicitud. Las publicadas no pueden volver a privadas.

La eliminación propia es lógica: registra autor, cancela solicitudes pendientes, conserva la referencia de favoritos y retira la receta del catálogo. Repetir esa eliminación devuelve 422. El detalle propio de una receta eliminada devuelve `id`, `nombre`, `visibilidad: "eliminada"`, `version`, `deleted_at`, `tipo_eliminacion`, `motivo_eliminacion`, `created_at` y `updated_at`.

El recurso propio vigente incluye los campos de contenido, `visibilidad` (`privada` o `publicada`), `version`, `solicitud_pendiente`, `created_at`, `updated_at` y `publicada_en`. Cuando se carga una solicitud pendiente, su resumen contiene `id`, `tipo`, `estado` y `created_at`. Consultar el detalle propio para conocer el estado vigente; no asumir que toda respuesta de escritura carga ese resumen.

### Contenido y validación

Ejemplo del objeto de contenido. Para crear una receta, enviar estos campos al nivel superior y adjuntar `imagen`; para corregir, enviarlos dentro de `contenido`:

```json
{
    "nombre": "Arroz con verduras",
    "descripcion": "Arroz casero con verduras frescas.",
    "porciones": 2,
    "tiempo_preparacion": 25,
    "tips": null,
    "categorias": [1],
    "ingredientes": [
        {
            "ingrediente_id": 1,
            "cantidad": 2,
            "unidad": "tazas",
            "notas": null,
            "orden": 1
        }
    ],
    "pasos": [{ "orden": 1, "instruccion": "Cocinar el arroz." }]
}
```

| Campo                             | Regla                                                                                             |
| --------------------------------- | ------------------------------------------------------------------------------------------------- |
| `nombre`                          | Obligatorio, texto, hasta 150 caracteres.                                                         |
| `descripcion`                     | Obligatoria, texto, hasta 16000 caracteres.                                                       |
| `porciones`, `tiempo_preparacion` | Enteros entre 1 y 65535.                                                                          |
| `tips`                            | Opcional/nullable, hasta 16000 caracteres.                                                        |
| `categorias`                      | Lista de 1 a 100 IDs existentes y distintos.                                                      |
| `ingredientes`                    | Lista de 1 a 500 objetos; IDs existentes sin repetir.                                             |
| `cantidad`                        | Campo presente, nullable; si contiene número, mayor que cero, hasta 9999999.999 y tres decimales. |
| `unidad`, `notas`                 | Campos presentes y nullables; hasta 50 y 16000 caracteres, respectivamente.                       |
| `orden`                           | Entero entre 1 y 65535, distinto dentro de cada lista.                                            |
| `pasos`                           | Lista de 1 a 500 objetos; instrucción obligatoria de hasta 16000 caracteres.                      |

Las listas deben tener índices consecutivos desde cero. La corrección admite solamente los campos documentados en `contenido`; los objetos de ingredientes y pasos también tienen campos cerrados. En formularios multipart, `categorias`, `ingredientes` y `pasos` pueden enviarse como cadenas JSON. `contenido` también puede ser una cadena JSON válida en la corrección.

### Imágenes

Las imágenes de receta admiten JPEG/JPG, PNG o WEBP y hasta 2048 KiB. Se guardan en almacenamiento privado y se sirven mediante las rutas autorizadas, con `Cache-Control: no-store, private`, `nosniff` y Content-Type comprobado. La URL de lectura no es una referencia aceptada para una escritura.

Para corregir texto desde otro dispositivo basta consultar el detalle y **omitir `contenido.imagen`**. El servidor conserva la imagen vigente de esa receta bajo bloqueo. Omitirla no equivale a enviar `null` o una cadena vacía: esos valores se rechazan. No es necesario conocer la ruta interna ni volver a subir la foto.

Si se cambia la imagen de una publicada: subir primero a `POST /mis-recetas/{id}/imagen` y usar el valor `imagen` devuelto en `contenido.imagen`. La subida no cambia la imagen pública de una receta publicada; la propuesta debe pasar por revisión. No se admiten referencias de otra receta, URLs externas ni rutas arbitrarias. Con una privada, esa subida establece su imagen vigente. La subida se rechaza si la receta está eliminada o tiene solicitud pendiente.

El detalle de solicitud entrega `contenido.imagen_url`, que permite leer la imagen propuesta. Al reconstruir un formulario desde un detalle, convertir `categorias` enriquecidas a su lista de IDs y quitar los campos de lectura, como `nombre` del ingrediente e `imagen_url`. Omitir imagen conserva la vigente de la receta, no necesariamente una imagen distinta de una propuesta rechazada. La API de detalle de solicitud no devuelve una referencia interna reutilizable para esa imagen rechazada.

## 6. Publicaciones, correcciones e idempotencia

Un usuario normal solicita publicación y espera revisión. La publicación inicial de una receta privada propia de un administrador activo es directa, con `mensaje` y `receta`; no crea revisiones ficticias. **Toda corrección posterior se envía a revisión**, incluida la de un administrador. Mientras espera, se mantienen el contenido aprobado y sus valoraciones.

Ejemplo de publicación:

```json
{ "clave_idempotencia": "ea868f67-c714-4896-9752-b038ff585971" }
```

Para corregir se añade `version_base` con la versión consultada y `contenido` con el objeto anterior. El primer envío devuelve 201 con `mensaje` y `solicitud`. Si la versión ya cambió y la clave no identifica una operación completada idéntica, devuelve 422 en `version_base`.

La clave UUID corresponde a una intención de operación y debe conservarse junto al cuerpo original hasta conocer su resultado:

- El mismo usuario, clave, receta, operación y petición recuperan el resultado sin volver a publicar, crear otra solicitud ni incrementar versiones.
- El reintento se comprueba después de autenticar y verificar propiedad, pero antes de exigir el estado o versión actuales de una operación nueva.
- Una solicitud se recupera por el mismo ID con su **estado actual**: puede estar pendiente, aprobada, rechazada o cancelada. No se reproduce una respuesta HTTP congelada.
- Un reintento de revisión devuelve 200; el primer envío devuelve 201. La publicación administrativa directa devuelve 200 en ambos casos.
- La misma clave usada para otra receta, tipo de operación, contenido o `version_base` devuelve 422 en `clave_idempotencia`.
- Publicar solo recibe la clave: volver a enviar esa misma clave a la misma ruta recupera la intención original, aunque la receta haya sido editada posteriormente. Para publicar nuevamente después de cancelar y editar se necesita otra clave.
- La huella de una corrección utiliza el cuerpo original antes de resolver la imagen omitida. Así el reintento idéntico funciona aunque cambien imagen o versión después. Mantener exactamente la misma representación de campos y listas; no alternar imagen omitida y explícita durante el reintento.
- Una receta eliminada no se restaura por recuperar una operación anterior. La respuesta refleja el recurso actual.

Las operaciones nuevas se registran en `operaciones_receta` dentro de la misma transacción que la escritura. Se conservan las claves históricas de `solicitudes_revision`. Las publicaciones/correcciones administrativas directas anteriores a este registro nunca guardaron una clave recuperable; no puede reconstruirse retrospectivamente un resultado por esa clave.

La creación de una receta privada y las subidas de archivo no ofrecen idempotencia mediante UUID. No aplicarles automáticamente la garantía de publicación/corrección. Agregar/quitar favoritos evita duplicar su relación sin requerir clave.

## 7. Solicitudes propias y revisión web

| Método y ruta API                            | Entrada                                                                                                                        | Respuesta 200                |
| -------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ | ---------------------------- |
| `GET /mis-solicitudes`                       | `estado`: `todos`, `pendiente`, `aprobada`, `rechazada`, `cancelada`; `tipo`: `todos`, `publicacion`, `correccion`; paginación | Colección propia.            |
| `GET /mis-solicitudes/{solicitud}`           | —                                                                                                                              | Detalle en `data`.           |
| `GET /mis-solicitudes/{solicitud}/imagen`    | —                                                                                                                              | Imagen propuesta autorizada. |
| `POST /mis-solicitudes/{solicitud}/cancelar` | Sin cuerpo                                                                                                                     | `mensaje`, `solicitud`.      |

El resumen contiene `id`, `receta_id`, `receta_nombre`, `tipo`, `estado`, `version_base`, `clave_idempotencia`, `created_at`, `revisada_en`, `cancelada_en` y `motivo_rechazo`. El detalle añade `contenido`, con categorías e ingredientes enriquecidos con nombres y la URL autenticada de imagen. No expone datos privados del revisor.

Se cancela una solicitud pendiente; repetir su cancelación tiene éxito sin repetir efectos. No se cancela una aprobada ni una rechazada. Cancelar una publicación deja la receta privada; cancelar una corrección conserva la receta publicada.

La aprobación y el rechazo se realizan en el panel web, con sesión de administrador activo y CSRF:

- `GET /revision-recetas` y `GET /revision-recetas/{solicitud}` muestran la cola y la comparación de la propuesta con la versión publicada.
- `POST /revision-recetas/{solicitud}/aprobar` requiere `confirmar_correccion_menor=1` cuando es una corrección.
- `POST /revision-recetas/{solicitud}/rechazar` requiere un `motivo_rechazo` no vacío, hasta 16000 caracteres.

La clasificación de corrección menor es manual: el administrador verifica que no cambie la preparación y confirma antes de aprobar. Si transforma ingredientes, cantidades o método de forma importante, debe rechazarla y explicar que corresponde crear otra receta, con identidad y valoraciones independientes. No existe clasificación semántica automática ni un porcentaje de cambio autorizado. La confirmación también se comprueba en la acción del servidor. La aprobación se bloquea si el autor está inactivo o la solicitud ya no es vigente.

## 8. Favoritos y disponibilidad para invitados

Los favoritos remotos requieren cuenta. Los invitados podrán guardar copias locales en la futura app, pero el backend no almacena ni sincroniza una lista de favoritos anónimos.

| Método y ruta                              | Acceso      | Resultado                                                                               |
| ------------------------------------------ | ----------- | --------------------------------------------------------------------------------------- |
| `GET /favoritos`                           | Autenticado | 200, colección paginada propia, incluidas entradas eliminadas.                          |
| `POST /favoritos/{receta}`                 | Autenticado | 201: `mensaje`, `receta_id`, `es_favorito: true`; no duplica la relación.               |
| `DELETE /favoritos/{receta}`               | Autenticado | 200: `mensaje`, `es_favorito: false`; también funciona si ya no estaba o fue eliminada. |
| `GET /favoritos/{receta}/estado`           | Autenticado | 200: `receta_id`, `es_favorito`; no confirma eliminación.                               |
| `POST /favoritos/verificar-disponibilidad` | Autenticado | 200: comprobación por lote del estado público.                                          |
| `POST /recetas/verificar-disponibilidad`   | Público     | 200: el mismo contrato de disponibilidad pública, para invitados.                       |

Solo se agregan recetas publicadas y vigentes. Consultar `/favoritos/{id}/estado` para una privada ajena o un ID inexistente devuelve el mismo 404, sin revelar su existencia. La consulta puede reconocer la receta privada propia, pero eso no autoriza agregarla como favorito público.

La colección de favoritos vigentes incluye el contenido resumido del catálogo, `visibilidad: "publicada"` y `agregado_en`. Una entrada eliminada contiene únicamente `id`, `nombre`, `visibilidad: "eliminada"`, `mensaje` y `agregado_en`; no entrega la preparación ni el motivo administrativo.

### Comprobación pública explícita

```text
POST /api/v1/recetas/verificar-disponibilidad
```

```json
{ "ids": [12, 18, 25, 99] }
```

Se admiten de 1 a 50 IDs canónicos distintos. Una respuesta ilustrativa es:

```json
{
    "data": [
        { "id": 12, "estado": "disponible" },
        {
            "id": 18,
            "estado": "eliminada_autor",
            "mensaje": "Esta receta fue eliminada por su autor"
        },
        {
            "id": 25,
            "estado": "eliminada_administracion",
            "mensaje": "Esta receta fue eliminada"
        },
        { "id": 99, "estado": "no_disponible" }
    ]
}
```

`no_disponible` agrupa IDs inexistentes y recetas privadas, incluso privadas eliminadas. **No confirma eliminación**. Los estados `eliminada_autor` y `eliminada_administracion` solo se devuelven para referencias de recetas que llegaron a publicarse. No incluyen título, contenido, autor ni motivo administrativo. El endpoint público no consulta ni modifica favoritos de una cuenta.

Contrato que deberá seguir el futuro móvil:

1. Sin conexión o sin confirmación explícita, conservar la copia descargada y el estado de eliminación desconocido.
2. Solo al recibir `eliminada_autor` o `eliminada_administracion` para ese ID, dejar de mostrar su contenido descargado.
3. Conservar la entrada con el aviso y permitir quitarla. No habilitar de nuevo el contenido al perder conexión después de esa confirmación.
4. Tratar timeouts, errores HTTP y `no_disponible` como ausencia de confirmación, sin deducir que el autor eliminó la receta.

La retirada administrativa y sus efectos todavía requieren completar el flujo funcional correspondiente; estos estados describen cómo se presentan referencias ya marcadas como eliminadas. No implican que exista una ruta administrativa de eliminación en la API v1.

## 9. Valoraciones

`GET /recetas/{receta}/valoracion`, autenticado, devuelve en `data`: `receta_id`, `puntuacion` propia nullable, `valoracion_promedio` nullable y `cantidad_valoraciones`.

`PUT` o `POST /recetas/{receta}/valoracion` recibe `{"puntuacion": 5}` y devuelve 200 con `mensaje` y el mismo recurso en `data`. Se admite un entero de 1 a 5. Cada cuenta tiene una valoración modificable por receta. Se permite autovalorar y valorar como administrador. Solo se consultan/valoran recetas publicadas y vigentes. Las correcciones aprobadas mantienen las valoraciones de la receta original; una receta nueva no las hereda.

## 10. Límites de solicitudes

Valores predeterminados observados en `config/api.php` y `AppServiceProvider`. Los límites configurables pueden ajustarse mediante las variables indicadas; no son una cuota contratada para todo despliegue.

| Operación               | Límite y ámbito                                                          | Configuración                                      |
| ----------------------- | ------------------------------------------------------------------------ | -------------------------------------------------- |
| Registro                | 5/minuto y 20/hora por IP                                                | `API_REGISTRO_POR_MINUTO`, `API_REGISTRO_POR_HORA` |
| Login                   | 5/minuto por correo y 10/minuto por IP; cuentan las peticiones           | Definido en el provider                            |
| Solicitar recuperación  | 1/minuto y 5/hora por correo; 15/hora por IP                             | Definido en el provider                            |
| Verificar código        | 10/minuto por IP; además límite de intentos del código                   | Definido en el provider                            |
| Restablecer contraseña  | 10/minuto por IP                                                         | `API_RESTABLECER_POR_MINUTO`                       |
| Disponibilidad pública  | 30/minuto por IP                                                         | `API_DISPONIBILIDAD_POR_MINUTO`                    |
| Escrituras autenticadas | 60/minuto por cuenta, compartidas entre esas rutas                       | `API_ESCRITURAS_POR_MINUTO`                        |
| Subidas de imágenes     | 10/minuto por cuenta cuando hay archivo; se suma al límite de escrituras | `API_IMAGENES_POR_MINUTO`                          |

El límite de imágenes también cubre crear y actualizar recetas cuando la petición contiene un archivo `imagen` o `foto_perfil`. Las consultas GET/HEAD/OPTIONS y el cierre de sesión no consumen la cuota de escrituras. Una actualización de texto sin archivo no consume la cuota de imágenes. La ruta autenticada de disponibilidad usa la cuota de escrituras. No interpretar estos límites como protección global de todos los endpoints públicos.

## 11. Compartir y conservar el identificador

El backend mantiene la identidad `id` de cada receta y resuelve una publicación mediante `GET /api/v1/recetas/{id}`. Un consumidor puede conservar el ID y construir esa URL con la dirección base configurada. La API de detalle aplica visibilidad y la comprobación pública por lote permite confirmar las eliminaciones sin confundirlas con otros 404.

Esa URL es un recurso JSON para obtener datos. No hay todavía una página pública de receta, campo `share_url`, App Link Android configurado ni enlace diferido tras instalar la aplicación. El dominio, la identidad y firma Android, el sitio de asociación, Google Play y el comportamiento de la futura app deben concretarse al implementar el cliente y el despliegue. Un App Link por sí solo no garantiza recuperar la receta después de instalar desde la tienda. No se ha elegido un proveedor externo.

## 12. Preparación del entorno y operaciones

### Administrador inicial

Desde la raíz Laravel que contiene `artisan`, el operador puede ejecutar en una terminal interactiva:

```powershell
php artisan app:crear-primer-administrador
```

Solicita nombre, correo y contraseña oculta con confirmación, mínimo 12 caracteres. Rechaza crear si ya existe un administrador, aunque esté deshabilitado, y rechaza reutilizar el correo de una cuenta existente. No promueve ni cambia la contraseña de cuentas existentes. No admite `--no-interaction` porque requiere introducir la contraseña sin exponerla como argumento. `DatabaseSeeder` no crea credenciales fijas para ese administrador. La presencia del comando no significa que se haya creado una cuenta real en esta revisión.

### Correo y colas

En el `.env` local inspeccionado se declaran `MAIL_MAILER=smtp` y `QUEUE_CONNECTION=database`. El archivo `.env.example` usa `log` y `database`. Estos valores de archivo no prueban credenciales válidas, configuración efectiva si está cacheada, existencia de un worker ni entrega de correo. No se enviaron correos reales para documentar este contrato.

La recuperación encola `CodigoRecuperacionMail`, con carga cifrada. Antes del envío vuelve a comprobar vigencia de la recuperación y del usuario. Para entrega real deben configurarse el transporte, host, puerto, autenticación y remitente del entorno, y operar un worker de cola. `log` escribe el correo en registros y no lo entrega a una bandeja. No copiar contraseñas o tokens a documentación ni publicar registros con códigos.

Una vez revisada la configuración y autorizada la entrega de correos reales, el operador puede iniciar el worker mediante `php artisan queue:work`. No se inició aquí: con SMTP podría procesar mensajes pendientes reales. Las pruebas usan correo simulado o `array` y recursos aislados.

### Migraciones pendientes

La migración incremental `2026_09_13_204720_create_operaciones_receta_table.php` crea el registro de idempotencia, con relaciones a usuario, receta y solicitud opcional. No reemplaza las solicitudes ni borra datos existentes. Fue preparada y comprobada en pruebas aisladas; no aplicada a la base real durante su implementación. Los endpoints que utilizan el nuevo registro requieren que esa migración esté instalada en el entorno donde se ejecutan.

La migración `2026_09_13_210516_fijar_expiracion_codigo_recuperacion.php` elimina la actualización automática accidental de `codigo_expira_en` detectada en MariaDB 10.4. Sin ella, guardar un intento fallido podía cambiar la expiración y vencer el código antes de los cinco intentos previstos. Conserva el tipo TIMESTAMP y los valores existentes, con un valor predeterminado explícito que impide el `ON UPDATE` implícito. No reconstruye expiraciones que ya se hayan modificado anteriormente. Se comprobó la conservación de registros previos en una base aislada; tampoco se aplicó a la base real.

Antes de instalar estas migraciones en la base real debe revisarse el destino y obtener autorización. `php artisan migrate:status` permite inspeccionar el estado. Aplicarlas corresponde a una operación de despliegue posterior; no ejecutar `migrate:fresh` ni `db:wipe`. Revertir la migración de operaciones elimina el historial de idempotencia; revertir la de expiración vuelve al comportamiento anterior. Ninguna reversión es una forma inocua de deshacer operaciones de recetas.

## 13. Alcance pendiente y comprobación

No se presentan como disponibles un módulo de incorporación de ingredientes, detección de duplicados con algoritmo/umbrales o comparación con pendientes, ni el flujo completo de retirada administrativa de recetas ajenas. Requieren las decisiones funcionales y la implementación correspondientes. Existen catálogos de lectura y comparación de una corrección con su original. Este documento no autoriza cargar ingredientes o recetas de ejemplo en la base real.

Tampoco se implementan aquí almacenamiento móvil, sincronización de favoritos invitados, borradores en servidor ni verificación del correo de registro. Los borradores incompletos siguen siendo una responsabilidad local del cliente futuro.

Para inspeccionar las rutas del checkout:

```powershell
php artisan route:list --path=api --except-vendor
```

Las regresiones de idempotencia, imagen conservada y envío administrativo a revisión están en `tests/Feature/Api/IdempotenciaRecetasTest.php` y los casos de recetas/solicitudes existentes. Los contratos de disponibilidad se cubren en `tests/Feature/Api/FavoritosContratoApiTest.php`. Las pruebas de esta aplicación rechazan bases que no cumplan las condiciones de aislamiento de `RefreshDatabaseSegura`. Una simulación determinista de concurrencia y una ejecución real con procesos MariaDB son evidencias distintas: no intercambiarlas al informar resultados.

Comprobación local desde la carpeta que contiene `artisan`:

```powershell
php artisan test --compact
node --test tests/Js/*.test.mjs
npm run build
php vendor/bin/pint --dirty --format agent
git diff --check
```

`phpunit.xml` usa SQLite en memoria. Las pruebas específicas de MariaDB se omiten si no se configuran `DB_TEST_HOST`, `DB_TEST_PORT`, `DB_TEST_USERNAME` y `DB_TEST_PASSWORD`; esa omisión no constituye una prueba de concurrencia aprobada. La cuenta de pruebas necesita permisos para crear y eliminar sus propias bases y consultar las esperas de bloqueo de InnoDB. No se debe apuntar `DB_DATABASE` a la base real para ejecutar pruebas.

Con esas credenciales de prueba configuradas, `php artisan test --compact --group=concurrencia-mariadb` ejecuta los procesos reales. Cada caso crea una base con nombre aleatorio, detiene sus procesos antes de limpiarla y comprueba que se haya eliminado únicamente esa base. El correo se simula dentro de cada proceso. Las pruebas JavaScript importan el módulo utilizado por la pantalla de usuarios; simulan sus dependencias de DOM/DataTables, sin copiar la lógica de búsqueda.
