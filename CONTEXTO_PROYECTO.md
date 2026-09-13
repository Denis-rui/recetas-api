# ¿Qué Cocinamos? — Contexto del proyecto

Este documento reúne el contexto funcional, técnico y organizativo compartido por el equipo. Su finalidad es permitir que otro integrante o asistente de IA continúe el proyecto sin depender del historial de una conversación.

**Estado del documento:** requisitos y decisiones en actualización. Última actualización funcional: **12 de septiembre de 2026**. Las funcionalidades descritas son requisitos; su presencia en este documento no significa que estén implementadas. Los antecedentes de instalación de la sección 7 no sustituyen una revisión del código actual.

## 1. Cómo utilizar este documento

- Leer este archivo y las instrucciones de `AGENTS.md` antes de proponer cambios.
- Dar prioridad a las indicaciones más recientes del usuario cuando modifiquen un acuerdo anterior.
- Distinguir entre requisitos confirmados, propuestas y decisiones pendientes.
- Revisar el código y la configuración real antes de afirmar que una función existe o que una instalación funciona.
- No ampliar el alcance con funciones que el equipo no haya solicitado.
- Actualizar este contexto cuando el equipo confirme cambios, conservando la diferencia entre lo previsto y lo implementado.

## 2. Identidad y propósito

**Nombre:** ¿Qué Cocinamos?

Aplicación móvil para organizar y consultar recetas de comidas, bebidas, cócteles y postres. Ayudará a decidir qué preparar a partir de los ingredientes disponibles, mostrando cuáles se tienen y cuáles hacen falta.

**Público objetivo:** estudiantes, amas de casa y jóvenes independientes.

El proyecto se desarrolla como parte del curso universitario de Programación de Aplicaciones Móviles.

### Problema identificado

Los usuarios tienen dificultades para decidir qué preparar y encontrar, en poco tiempo, recetas compatibles con los ingredientes que tienen disponibles. Esto ocurre tanto en la alimentación diaria como en reuniones y ocasiones especiales.

La búsqueda manual de preparaciones y la información poco organizada obligan a revisar recetas una por una. Esta situación dificulta la elección y favorece la repetición de preparaciones y la monotonía en la alimentación.

### Objetivo de la solución

Facilitar la búsqueda y consulta de recetas mediante un catálogo organizado, categorías y selección de ingredientes, junto con instrucciones claras que puedan seguirse mientras se cocina.

## 3. Tipos de usuario y acceso

| Tipo de usuario      | Acceso y funciones acordadas                                                                                                 |
| -------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| Invitado o visitante | Ingresa sin cuenta. Puede explorar, buscar, seleccionar ingredientes, filtrar y guardar favoritos en el dispositivo.         |
| Usuario registrado   | Tiene las funciones de consulta del invitado, favoritos vinculados a su cuenta, edición de perfil, borradores locales, recetas privadas en su cuenta, envío de recetas a revisión, eliminación de sus recetas y valoraciones. |
| Administrador        | Cuando decide publicar una receta propia, lo hace directamente desde la app. Accede a la web para gestionar cuentas y revisar recetas y modificaciones enviadas por usuarios. También puede valorar recetas. |

La consulta del catálogo no debe exigir registro. Las operaciones administrativas sí requieren acceso autorizado. La web es exclusiva para administradores activos. Los invitados no publican ni valoran recetas.

## 4. Funcionalidades confirmadas

### 4.1. Inicio, registro y recuperación de acceso

- Al abrir la aplicación, el usuario podrá iniciar sesión o continuar sin cuenta.
- Desde la pantalla de inicio de sesión podrá acceder al registro y a la recuperación de acceso.
- El registro utilizará correo electrónico y contraseña.
- La forma técnica de autenticar las solicitudes y recuperar contraseñas todavía debe definirse.

### 4.2. Catálogo y categorías

El catálogo tendrá cuatro categorías principales:

1. Comidas.
2. Bebidas.
3. Cócteles.
4. Postres.

El usuario podrá explorar recetas, buscar por nombre y aplicar filtros por categoría. No se han confirmado otros filtros, como calorías, dietas, alergias, dificultad o precio.

### 4.3. Búsqueda por ingredientes

- El usuario seleccionará los ingredientes que tiene disponibles.
- El sistema mostrará preparaciones compatibles con esa selección.
- En los resultados o en su presentación asociada deberá poder reconocer los ingredientes disponibles y los faltantes.
- El propósito es evitar que el usuario revise manualmente cada receta para hacer esa comparación.

Queda pendiente precisar la regla de compatibilidad, el orden de los resultados, el tratamiento de cantidades y la duración de la selección. No convertir esta función en un inventario permanente de despensa sin confirmarlo.

### 4.4. Detalle de una receta

Cada receta incluirá:

- Nombre.
- Imagen.
- Descripción.
- Ingredientes.
- Cantidad de porciones.
- Categoria.
- Tiempo de preparación.
- Instrucciones numeradas y ordenadas.
- Tips: campo de texto opcional para consejos que el autor desee agregar. Puede quedar vacío y no será obligatorio para guardar la receta, enviarla a revisión o publicarla.
- Opción de reproducir las instrucciones mediante audio para seguirlas mientras se cocina.
- Valoración promedio de 1 a 5, representada con sombreritos de chef, y cantidad de valoraciones.
- Opción para compartir el enlace de la receta publicada.

La herramienta de audio, el modo de lectura y su disponibilidad sin conexión están pendientes de definición.

### 4.5. Favoritos

**Con cuenta:** los favoritos quedarán vinculados al usuario y podrá recuperarlos al cambiar de teléfono o reinstalar la aplicación, al acceder de nuevo a su cuenta.

**Sin cuenta:** los favoritos se guardarán únicamente en el dispositivo. El requisito comunicado es que no se recuperen al cambiar de equipo o reinstalar la aplicación; no se debe prometer sincronización para invitados.

Las recetas guardadas que tengan una copia descargada podrán consultarse sin conexión. Falta precisar qué contenido se descarga al guardar en favoritos y qué ocurre con los favoritos locales cuando un invitado crea una cuenta o inicia sesión.

Si el autor elimina una receta guardada por otra persona, la entrada seguirá apareciendo en sus favoritos con un mensaje como «Esta receta fue eliminada por su autor». La persona podrá quitarla usando la opción habitual de eliminar de favoritos, disponible también para las recetas vigentes. Se debe conservar una referencia que permita mostrar ese aviso.

Si el teléfono está sin conexión, la persona podrá seguir consultando la copia que ya tenía descargada. Cuando la app se conecte y confirme con el servidor que la receta fue eliminada, dejará de mostrar su contenido y conservará únicamente la entrada de favorito con el aviso correspondiente y la opción de quitarla. Volver a estar sin conexión después de confirmar la eliminación no debe habilitar de nuevo esa copia. Un fallo de conexión no demuestra que una receta haya sido eliminada.

Si un administrador elimina una receta pública de otra persona, también permanecerá el aviso en favoritos, con un texto como «Esta receta fue eliminada», sin atribuir la acción al autor. El motivo administrativo será consultable por el autor; no se ha acordado mostrarlo a quienes solo guardaron la receta.

### 4.6. Mi perfil

Los usuarios registrados podrán:

- Consultar y editar su nombre completo.
- Consultar y editar su correo electrónico.
- Agregar o cambiar su fotografía de perfil.

Cuando un invitado ingrese a «Mi perfil», verá una imagen anónima y un mensaje de registro en una ventana flotante sobre esa imagen, con acceso a la creación de una cuenta.

### 4.7. Creación, publicación y corrección de recetas en la aplicación móvil

**Cambio de alcance confirmado:** los usuarios registrados también podrán aportar recetas. Esta decisión reemplaza la restricción anterior de publicación exclusiva por administradores.

- El usuario registrado puede guardar recetas privadas en su cuenta. Solo al seleccionar Publicar envía una solicitud a revisión. No aparece en el catálogo hasta que un administrador la apruebe.
- Cuando un administrador decide publicar una receta propia, se publica directamente, sin revisión de otro administrador. Crear o guardar una receta privada no implica publicarla.
- El autor podrá consultar el estado de sus envíos desde la app y recibir allí el motivo obligatorio de un rechazo.
- El autor podrá corregir una solicitud rechazada y reenviarla a revisión.
- El formulario tendrá inicialmente dos campos para escribir pasos y permitirá agregar más. Esto no define un mínimo obligatorio de dos pasos por receta.

#### Recetas privadas y publicación

- Las recetas privadas se guardarán en el servidor vinculadas a la cuenta autora y podrán recuperarse al iniciar sesión en otro teléfono.
- Serán visibles solo para su autor en la aplicación y no aparecerán en el catálogo ni en la cola de revisión mientras no solicite publicarlas.
- Guardar una receta privada en la cuenta requiere conexión con el servidor. No se ha acordado sincronización automática ni edición de recetas del servidor sin conexión.
- Los borradores incompletos seguirán siendo locales. Guardar en la cuenta y solicitar publicación son operaciones distintas.
- Una receta que ya está publicada no podrá volver a privada. Su autor podrá eliminarla conforme a las reglas de eliminación; no se ofrecerá un interruptor para ocultarla como privada.
- El autor puede cancelar una solicitud de publicación mientras esté pendiente de revisión. La receta continúa privada en su cuenta y podrá corregirla antes de solicitar su publicación nuevamente.
- Mientras la solicitud de publicación esté pendiente, el autor no podrá modificar el contenido enviado. Para editarlo deberá cancelar primero la solicitud y, después de guardar los cambios, enviar una nueva solicitud de publicación.
- Una solicitud cancelada deja de estar disponible para aprobación. Al procesar una cancelación o aprobación se debe comprobar el estado vigente; una solicitud ya aprobada no puede cancelarse para devolver la receta a privada.
- La prohibición de volver a privada corresponde a las recetas ya publicadas. Cancelar un envío pendiente no elimina la receta privada.

#### Correcciones de una receta publicada

- Se permiten correcciones menores, como ortografía o redacción que no altere la preparación. Las enviadas por usuarios normales están sujetas a revisión; los administradores aplican directamente las correcciones menores de sus propias recetas.
- La versión aprobada permanece visible mientras se revisa la modificación. Los cambios pendientes se conservan por separado del contenido publicado.
- Al aprobar la corrección, se actualiza la versión publicada y se conservan sus valoraciones.
- Al rechazarla, permanece la versión aprobada y el autor recibe una explicación para corregir y reenviar.
- El autor también puede cancelar una solicitud de corrección pendiente: se cancela el envío, sin modificar ni retirar la receta ya publicada ni sus valoraciones. Una solicitud aprobada no puede cancelarse.
- Los cambios importantes en ingredientes, cantidades o método que transformen la preparación deberán enviarse como una receta nueva, con identidad y valoraciones independientes.
- El administrador evalúa si el cambio es una corrección menor. Si transforma la receta, rechaza la modificación y explica que debe presentarse como una nueva publicación.
- El administrador puede publicar recetas propias y aplicar correcciones menores en ellas sin revisión de otro administrador. Si transforma la preparación mediante cambios importantes, debe crear una receta nueva, que también publica directamente y comienza sin heredar valoraciones. Esta autorización sobre recetas propias no define permisos de edición sobre recetas ajenas.

#### Eliminación por el autor y moderación administrativa

- El usuario podrá eliminar sus propias recetas, incluidas las publicadas. Una receta eliminada dejará de estar disponible en el catálogo.
- En los favoritos de otras personas permanecerá una entrada con el aviso de receta eliminada y la opción habitual de quitarla de favoritos, según la sección 4.5.
- El mecanismo de eliminación deberá conservar la referencia necesaria para ese aviso; no se ha decidido borrar físicamente todo el contenido ni permitir restauración.
- Los administradores pueden eliminar recetas públicas de otros usuarios como acción de moderación, indicando un motivo obligatorio que el autor podrá consultar en la app. Por ejemplo, pueden retirar un duplicado que se haya aprobado por error.
- La eliminación administrativa genera el aviso de receta eliminada en los favoritos existentes. No se debe atribuir al autor una eliminación realizada por un administrador.
- Esta autorización se refiere a recetas públicas; no amplía el acceso administrativo a recetas privadas. Siguen pendientes el tratamiento de solicitudes de corrección que estuvieran abiertas, valoraciones y enlaces. Las copias descargadas siguen la regla de la sección 4.5: se consultan mientras el dispositivo no conoce la eliminación y dejan de mostrarse cuando la app la confirma con el servidor.

#### Borradores locales

- Se podrán guardar recetas incompletas como borradores únicamente en el teléfono donde se redactaron, asociados a la cuenta autora.
- Al intentar salir del formulario con cambios sin guardar, la app preguntará si se desea guardar como borrador. Ofrecerá guardar, descartar cambios o seguir editando.
- Los datos y la imagen se conservarán en almacenamiento local persistente, no en caché temporal, y se podrán retomar desde Mis recetas → Borradores.
- No habrá sincronización de borradores con el servidor ni recuperación garantizada en otro teléfono. Se pueden perder al desinstalar la app o borrar sus datos.
- La pregunta al salir del formulario no garantiza avisar ante un cierre forzado del proceso. No se ha acordado autoguardado.
- Al enviar a revisión se validan los campos obligatorios y se requiere conexión con el servidor.
- SQLite local se ha planteado como opción técnica; no reemplaza MariaDB del backend ni implica que se haya instalado una biblioteca móvil.

### 4.8. Administración de cuentas en la plataforma web

La web será exclusiva para administradores activos y permitirá:

- Iniciar y cerrar sesión.
- Consultar cuentas y buscarlas por nombre o correo.
- Crear usuarios normales y administradores con nombre completo, correo único, contraseña de al menos 12 caracteres, confirmación de contraseña y foto opcional.
- Mostrar Administrador como rol predeterminado únicamente en el formulario de creación administrativo. El registro móvil asignará Usuario normal.
- Crear cuentas activas y mostrar iniciales cuando no exista una foto.
- Editar datos de cuentas y cambiar el rol de otras cuentas en ambos sentidos.
- Deshabilitar y reactivar otras cuentas conservando sus datos.
- Mantener disponibles las recetas públicas de una cuenta deshabilitada. Deshabilitar al autor no oculta ni elimina sus publicaciones; si una receta presenta un problema, el administrador debe eliminarla por separado y registrar el motivo correspondiente.
- Conservar las solicitudes pendientes de una cuenta deshabilitada, bloqueando su aprobación mientras el autor esté inactivo. Al reactivar la cuenta pueden continuar con la revisión; no se aprueban ni publican automáticamente y no es necesario reenviarlas por la sola deshabilitación.
- Invalidar las sesiones existentes y bloquear nuevos accesos autenticados al deshabilitar. Reactivar exige iniciar una nueva sesión; no recupera las anteriores. La regla también deberá aplicarse a la API móvil cuando se implemente.
- Editar el perfil propio: nombre, correo único y foto; cambiar la contraseña propia solicitando la contraseña actual y la confirmación de la nueva.

Un administrador no podrá deshabilitarse ni cambiar su propio rol desde ninguna pantalla o solicitud. Estas restricciones se aplican también desde la gestión de cuentas, no solo desde Mi perfil.

La web tendrá además el módulo de revisión de recetas de la sección 4.9. Esto amplía el alcance anterior de administración exclusiva de cuentas; no implica trasladar a la web el formulario móvil de creación de recetas.

### 4.9. Revisión manual y alertas de posibles duplicados en la web

Los administradores revisarán solicitudes de publicación de recetas y correcciones pendientes. Las recetas privadas que no se han enviado a publicar no forman parte de la revisión. Podrán aprobar o rechazar, indicando un motivo obligatorio cuando rechacen. El autor consultará la retroalimentación en la misma app; no se han acordado notificaciones push ni correos para este flujo.

Antes de aprobar, se comprobará que el autor siga activo y la solicitud continúe pendiente. Si el autor está deshabilitado, el envío se conserva pendiente pero su aprobación queda bloqueada hasta que se reactive la cuenta. Esta regla también se aplica a las correcciones pendientes; la receta ya publicada permanece visible.

#### Comparación de contenidos

| Revisión | Lado izquierdo | Lado derecho |
| --- | --- | --- |
| Corrección de una receta publicada | Versión publicada | Modificación pendiente de aprobación |
| Alerta de posible duplicado | Receta similar encontrada | Receta que se está revisando |

Si hay varias coincidencias, flechas permitirán recorrerlas en el lado izquierdo, con un indicador de posición como «2 de 5». El contenido de la derecha permanece fijo. Se resaltarán los campos modificados al comparar una corrección con su versión publicada.

#### Reglas de duplicados

- Los títulos pueden repetirse: el mismo nombre no demuestra que dos preparaciones sean iguales.
- Se mostrarán alertas de posibles duplicados al comparar título, ingredientes, cantidades y pasos. Una coincidencia de contenido aunque cambie el título constituye una señal relevante para revisar.
- Se podrán ignorar diferencias superficiales como mayúsculas o espacios repetidos, sin eliminar cantidades ni alterar el orden de los pasos.
- Las alertas no producirán rechazo automático. El administrador decide si es una variante válida o un duplicado y explica el rechazo cuando corresponda.
- La receta original se excluye como candidato duplicado de su propia modificación.
- Se evitará crear envíos repetidos por doble pulsación o reintentos de conexión; este control es distinto de detectar recetas similares.
- No se garantiza detectar todas las copias o paráfrasis. El algoritmo, los umbrales y la comparación con otros envíos pendientes quedan por definir y probar.

#### Estados confirmados de las solicitudes de revisión

Los siguientes estados se aplican a solicitudes de publicación y de corrección:

| Estado | Significado |
| --- | --- |
| Pendiente de revisión | El autor envió el contenido y todavía no existe una decisión. |
| Aprobada | El administrador aceptó la publicación o la corrección. |
| Rechazada | El administrador no aceptó el envío y registró un motivo obligatorio, visible para el autor. |
| Cancelada | El autor retiró el envío mientras estaba pendiente, antes de su aprobación. |

Una solicitud pendiente puede aprobarse, rechazarse o cancelarse. Una solicitud cancelada no se puede aprobar; después de corregir el contenido, el autor realiza un nuevo envío. Deshabilitar al autor conserva el estado Pendiente de revisión, pero bloquea la aprobación hasta reactivar la cuenta, sin crear un estado adicional ni aprobar automáticamente.

Estos estados corresponden a la solicitud, no a la visibilidad de la receta. Una publicación inicial pendiente, rechazada o cancelada no convierte la receta privada en pública. Una receta pública puede tener una corrección pendiente, rechazada o cancelada y conservar su versión publicada. Cancelar una corrección no devuelve la receta a privada ni elimina sus valoraciones.

El autor también deberá reconocer sus borradores locales, recetas privadas en la cuenta y recetas eliminadas. La visibilidad, el estado de revisión y el lugar de almacenamiento son conceptos separados. Una receta publicada no vuelve a privada. Las recetas que publica directamente un administrador no requieren crear una solicitud de revisión ficticia.

### 4.10. Valoraciones

- Se utilizará una escala de 1 a 5, representada con iconos de sombreritos de chef en lugar de estrellas.
- Solo las personas con cuenta podrán valorar. Habrá una valoración por cuenta y receta, modificable sin sumar otro voto.
- Se mostrará el promedio y la cantidad de valoraciones.
- Los usuarios y administradores pueden valorar sus propias recetas. No aplicar una prohibición de autovaloración.
- Las correcciones menores aprobadas conservan las valoraciones. Las preparaciones nuevas derivadas de cambios importantes comienzan con sus propias valoraciones, sin heredar las de la receta original.

### 4.11. Compartir recetas y recuperar el destino después de instalar

El usuario podrá compartir un enlace que identifique una receta publicada. El comportamiento requerido para Android es:

1. Si la app está instalada, al abrir el enlace se accede al detalle de esa receta.
2. Si no está instalada, el enlace conduce a la ficha de la app en Google Play para instalarla.
3. Al abrir por primera vez la app después de instalarla mediante ese recorrido, se recupera el destino del enlace original y se muestra esa receta, en lugar de perder el destino y dejar al usuario en el inicio.

El tercer punto requiere enlaces diferidos. No significa que la instalación abra por sí sola la aplicación. Consultar una receta continúa sin exigir una cuenta.

**Dependencias técnicas pendientes:** dominio y enlace HTTPS, asociación del dominio con la app, ficha de Google Play, mecanismo para conservar y recuperar el identificador durante la instalación e integración con React Native/Expo. Los App Links resuelven el acceso con la app instalada; no implementan por sí solos la recuperación tras instalar. Google Play Install Referrer es una opción por evaluar, no una integración elegida o terminada. El recorrido completo debe probarse con una instalación real desde Google Play.

Referencias técnicas: [Android App Links](https://developer.android.com/training/app-links) y [Google Play Install Referrer](https://developer.android.com/google/play/installreferrer).

Queda pendiente acordar el comportamiento si la receta deja de estar disponible, si no hay conexión al recuperar el destino o si el enlace se abre fuera de Android. No se ha solicitado un catálogo web público.

### 4.12. Mejora futura: inteligencia artificial

Se evaluará el uso de IA para detectar duplicados y asistir o automatizar parte de la revisión y aprobación de recetas. El usuario la ha planteado como posible mejora para un segundo o tercer sprint, sin fijar todavía el sprint ni comprometer una aprobación automática.

Antes de incorporarla deberán evaluarse la precisión, los errores de aceptación y rechazo, el costo, la integración y las decisiones que seguirán requiriendo intervención humana. En la primera versión la revisión de los envíos de usuarios será manual. Esta mejora es independiente de Laravel Boost, que solo asiste al desarrollo.

## 5. Origen de las recetas

El equipo desarrollará su propio backend y mantendrá un catálogo propio. Incluirá recetas publicadas directamente por administradores y aportes de usuarios aprobados mediante revisión manual. El servidor también conservará recetas privadas vinculadas a sus autores, que no forman parte del catálogo público ni requieren revisión. Los borradores permanecerán únicamente en el teléfono del autor.

El equipo descartó depender de una API gratuita externa como fuente principal porque las opciones consideradas no cubrían sus necesidades de idioma, recetas peruanas y límites de acceso gratuito. Esto describe la evaluación del equipo; no implica que ninguna API del mercado ofrezca recetas en español.

No hay una integración externa de recetas aprobada como parte del alcance actual.

## 6. Tecnologías y distribución de responsabilidades

| Parte del sistema                  | Tecnología o herramienta acordada | Función                                                                       |
| ---------------------------------- | --------------------------------- | ----------------------------------------------------------------------------- |
| Aplicación móvil                   | React Native, Expo y TypeScript   | Pantallas e interacción con el usuario.                                       |
| Backend                            | Laravel con PHP                   | API, reglas de negocio, acceso a datos y soporte de gestión de cuentas y revisión web. |
| Base de datos                      | MariaDB mediante XAMPP            | Almacenar cuentas, recetas, ingredientes, categorías, favoritos, valoraciones y solicitudes de revisión enviadas. |
| Administración de la base de datos | phpMyAdmin                        | Consultar y administrar la base de datos durante el desarrollo.               |
| Control de versiones               | Git y GitHub                      | Registrar cambios y colaborar entre integrantes.                              |

**XAMPP es el paquete de herramientas; MariaDB es el motor de base de datos. SQL es el lenguaje de consultas.** La decisión más reciente fue mantener MariaDB con XAMPP en lugar de utilizar SQLite como base principal del proyecto.

El flujo previsto es: la aplicación móvil envía una solicitud a Laravel, Laravel aplica las reglas y consulta MariaDB, y devuelve los datos que React Native presenta en pantalla. Los teléfonos no accederán directamente a MariaDB.

### Plataformas móviles

Se mantendrá una base de código adaptable a Android e iOS. Durante el curso se priorizarán la implementación y las pruebas en **Android**. La posibilidad de ejecutar en iOS no debe presentarse como una compatibilidad ya probada.

### Interfaz web y autenticación: decisiones pendientes

- Se conversó sobre Blade y Livewire para el panel web. No quedó confirmada la selección final.
- Blade se propuso como una base sencilla; Livewire como alternativa para formularios y tablas interactivas usando PHP y Blade.
- React para una interfaz web y React Native para la aplicación móvil son tecnologías con usos distintos. La opción «React» del instalador de Laravel no crea la aplicación móvil.
- Sanctum se mencionó como una posibilidad para autenticar la API. No se ha aprobado ni verificado su implementación.
- No están aprobados proveedores externos de inicio de sesión, como Google.
- No se ha elegido alojamiento para producción.

## 7. Instalación y herramientas de asistencia

Durante la conversación, Jaime inició nuevamente la instalación de Laravel y consultó las opciones del instalador.

| Opción consultada             | Orientación o decisión conversada                                                     | Estado                                                                                       |
| ----------------------------- | ------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| Starter kit                   | Se recomendó comenzar sin kit para aprender el backend.                               | No se confirmó qué respuesta se introdujo.                                                   |
| Frontend                      | Se explicaron Blade y Livewire.                                                       | Elección final pendiente de verificar.                                                       |
| Laravel Boost                 | Se recomendó configurar `guidelines`, `skills` y `mcp`.                               | Existe Boost declarado en el proyecto; no se comprobó el funcionamiento de cada integración. |
| Integración con Laravel Cloud | Se recomendó seleccionar `None` para el desarrollo local.                             | No hay despliegue en Cloud acordado.                                                         |
| Agentes de IA                 | Jaime utiliza Codex y también Antigravity; se indicó seleccionar `codex,antigravity`. | No se ha verificado que ambas integraciones estén activas.                                   |
| Base de datos                 | El usuario eligió continuar con MariaDB mediante XAMPP.                               | Decisión confirmada; conexión efectiva pendiente de comprobar.                               |

Boost sirve de apoyo al desarrollo. No es una función de inteligencia artificial destinada a los usuarios de la aplicación.

### Observaciones del proyecto al crear este documento

- La raíz de Laravel es la carpeta que contiene `artisan`, `composer.json` y este archivo. En el espacio de trabajo revisado existe una carpeta `recetas-api` dentro de otra del mismo nombre.
- `composer.json` declara `laravel/framework` con restricción `^13.17` y PHP `^8.3`. Son requisitos declarados, no una comprobación de las versiones efectivamente instaladas o utilizadas por la terminal.
- También declara Laravel Boost y Pest como dependencias de desarrollo.
- Existe un archivo `AGENTS.md` con instrucciones de Laravel Boost.
- No se revisaron credenciales, la conexión de base de datos ni el funcionamiento de la aplicación para redactar este contexto.
- La carpeta revisada no estaba inicializada como repositorio Git. Esto no permite concluir si existe un repositorio remoto creado por otra vía.

Estas observaciones pueden cambiar. Revisar los archivos y el entorno antes de utilizarlas como diagnóstico actual.

## 8. Equipo, roles y actividades

Los cuatro integrantes participan en el diseño de las pantallas. Los roles son iniciales y **podrán combinarse o rotarse**.

| Nombres completos         | GitHub             | Rol inicial                                            | Responsabilidades                                                                         | Actividades actuales y previstas                                                                                                                                                                                                            |
| ------------------------- | ------------------ | ------------------------------------------------------ | ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Jaime Denis Ruis Medina   | Denis-rui          | Desarrollador React Native y encargado del repositorio | Desarrollar funcionalidades y organizar el control de versiones.                          | **Actuales:** participar en los diseños, preparar el repositorio e iniciar el aprendizaje y la configuración del backend. **Previstas:** implementar pantallas y navegación, conectar la aplicación con Laravel y corregir errores.         |
| Liliana Bustamante Tauma  | lbustamantet27-max | Coordinadora y analista                                | Coordinar tareas y analizar el problema, las necesidades y la solución.                   | **Actuales:** describir la problemática y la solución y participar en los diseños. Ella propuso la idea de la aplicación. **Previstas:** organizar tareas, seguir avances y revisar la correspondencia entre necesidades y funcionalidades. |
| Cristhian Uriarte Flores  | cristhianUF        | Diseñador UI/UX                                        | Organizar la presentación visual y revisar la consistencia y navegación de las pantallas. | **Actuales:** participar en los diseños, incorporar los mockups al documento y describirlos. **Previstas:** ajustar diseños y contrastarlos con las pantallas implementadas.                                                                |
| Jordy Yair Pasapera Pérez | jypp16             | Responsable de pruebas y apoyo en requisitos           | Documentar requisitos y comprobar el cumplimiento funcional.                              | **Actuales:** elaborar requisitos y participar en los diseños. **Previstas:** preparar casos de prueba, probar en Android, registrar errores y verificar correcciones.                                                                      |

Las actividades futuras son una distribución inicial propuesta, no evidencia de trabajo terminado. El equipo debe ajustarlas al rotar los roles y planificar cada etapa.

## 9. Repositorio y entrega académica

- **Nombre sugerido:** `que-cocinamos`.
- **Visibilidad requerida por la consigna:** pública.
- **Propietario previsto:** cuenta de Jaime, `Denis-rui`.
- **Enlace definitivo y creación remota:** pendientes de confirmar. No presentar una dirección sugerida como un repositorio ya existente.
- **Título de la tabla del informe:** «Roles, responsabilidades y actividades del equipo de desarrollo».
- El informe debe incluir la tabla con nombres completos, usuarios de GitHub, rol inicial, responsabilidades y actividades asignadas, junto con el enlace y el QR del repositorio público.
- Para generar el QR se sugirió [QRCode Monkey](https://www.qrcode-monkey.com/es/). Usar el enlace definitivo y comprobarlo escaneando la imagen.

### Contenido inicial sugerido

Un `README.md` con nombre, descripción, objetivo, funcionalidades previstas, tecnologías, integrantes y estado actual. Los requisitos y mockups pueden incorporarse cuando estén revisados. El código se añadirá conforme avance el desarrollo.

**Descripción breve propuesta:** «Aplicación móvil para descubrir recetas de comidas, bebidas, cócteles y postres según los ingredientes disponibles, con instrucciones paso a paso y favoritos».

### Forma de colaboración propuesta

- Invitar a los integrantes como colaboradores.
- Trabajar con ramas por tarea y mensajes de cambios descriptivos.
- Revisar el trabajo de otro integrante antes de incorporarlo a la rama principal.
- Mantener actualizadas las responsabilidades cuando roten los roles.
- Evitar publicar contraseñas, claves o archivos `.env` con datos reales. Usar ejemplos de configuración sin secretos.

Estas prácticas se propusieron para el equipo; no se ha comprobado que estén configuradas como reglas del repositorio.

## 10. Decisiones que deben aclararse antes de implementarlas

1. Regla de compatibilidad por ingredientes: coincidencia completa o parcial, orden de resultados y comparación de cantidades.
2. Persistencia de la selección de ingredientes al cerrar la aplicación.
3. Si una receta puede pertenecer a una o varias categorías.
4. Contenido que se descarga al guardar en favoritos y momento de la descarga. Ya se confirmó que una copia descargada puede consultarse sin conexión y que, al confirmar su eliminación con el servidor, se deja de mostrar el contenido y se conserva el aviso en favoritos.
5. Tratamiento de favoritos locales al iniciar sesión o registrarse y posibles duplicados.
6. Herramienta de audio y comportamiento sin conexión.
7. Método de autenticación de la API, recuperación de contraseña y comprobación de cambios de correo.
8. Mecanismo para preparar la primera cuenta administradora. Los permisos generales y las restricciones sobre la cuenta propia están definidos en la sección 4.8.
9. Detalles de eliminación: conservación de contenido en el servidor, posible restauración y efectos en solicitudes abiertas, valoraciones y enlaces. Ya se confirmó que el autor puede eliminar sus recetas, que el administrador puede eliminar recetas públicas ajenas con un motivo obligatorio visible para el autor y que los favoritos existentes mostrarán un aviso. Las copias descargadas dejan de mostrarse al confirmar la eliminación con el servidor, según la sección 4.5. Deshabilitar al autor mantiene sus recetas públicas disponibles y sus solicitudes pendientes conservadas, pero bloquea su aprobación hasta reactivar la cuenta, sin publicación automática. La eliminación de una receta requiere una acción independiente. No se permite pasar de pública a privada.
10. Elección definitiva de Blade o Livewire para el panel web.
11. Diseño de tablas, relaciones, rutas de API y reglas de validación concretas.
12. Reparto detallado de programación del backend, calendario y alcance de cada entrega.
13. Alojamiento, copias de seguridad y acceso al backend desde dispositivos para las pruebas.
14. Detalles técnicos para resolver revisiones simultáneas sin aprobar envíos cancelados ni aplicar decisiones sobre un estado desactualizado. Ya se confirmaron los estados Pendiente de revisión, Aprobada, Rechazada y Cancelada para publicaciones y correcciones. Cancelar una publicación inicial conserva la receta privada; cancelar una corrección conserva la versión pública. Para editar una publicación inicial pendiente se cancela primero y se vuelve a enviar después de los cambios.
15. Aplicación de alertas de duplicados a la publicación directa de administradores y permisos de edición sobre recetas ajenas. Ya se confirmó que el administrador publica y corrige sus propias recetas sin revisión, manteniendo la obligación de crear una receta nueva ante cambios importantes y sin trasladar valoraciones.
16. Detalles de borradores locales al cerrar sesión o cambiar de cuenta y al confirmar que se guardaron en la cuenta o se enviaron a revisión. Las recetas privadas se conservan en el servidor; los borradores no se sincronizan.
17. Criterios y algoritmo para alertas de duplicados, incluyendo qué estados de otras recetas se comparan. No fijar porcentajes sin validarlos con ejemplos.
18. Retirada de valoraciones por su autor, si se desea permitirla; por ahora solo se ha confirmado que podrá modificarlas.
19. Implementación y pruebas del enlace diferido, comportamiento sin conexión o con receta no disponible y destinos fuera de Android.
20. Viabilidad, alcance y sprint de la mejora futura con IA. No sustituir la revisión manual inicial sin un nuevo acuerdo.

Algunos de estos asuntos tienen antecedentes en otras conversaciones. Este archivo no los da por cerrados cuando no están confirmados en el contexto que originó esta documentación. Si se recuperan acuerdos anteriores, contrastarlos con el usuario antes de convertirlos en requisitos nuevos.

## 11. Cómo acompañar el aprendizaje

Jaime quiere entender qué hace el backend y cómo se construye. La ayuda debe permitirle aprender y verificar el resultado.

- Explicar en español, con ejemplos de recetas, ingredientes, usuarios y favoritos.
- Antes de proponer un cambio de configuración, explicar qué resuelve y qué opción corresponde al proyecto.
- Ante un error, revisar el mensaje exacto y separar la causa de los problemas independientes.
- Avanzar por pasos comprensibles y evitar bloques grandes de código sin explicación.
- Resolver las decisiones pendientes con preguntas concretas, preferentemente una por vez.
- Para una solicitud de explicación o análisis sin cambios, respetar ese alcance.
- No afirmar que algo está terminado sin comprobarlo.

Como recursos de aprendizaje se recomendaron [API RESTful con Laravel desde cero de Coders Free](https://codersfree.com/cursos/aprende-a-crear-una-api-restful-con-laravel) y [Laravel 12 desde cero de El Rincón de Isma](https://www.youtube.com/watch?v=vvGVVyatV_U). Los ejemplos y versiones de los cursos pueden diferir del proyecto; comprobarlos antes de copiar instrucciones. No se ha confirmado compra ni finalización de cursos.

## 12. Antes de continuar el trabajo

Leer la petición actual, revisar este contexto y `AGENTS.md`, identificar el estado real de los archivos y comprobar solo lo necesario para la tarea. Si el código contradice un requisito, explicar la diferencia. Si una decisión permanece pendiente y cambia el diseño del sistema, aclararla antes de implementarla.
