# ¿Qué Cocinamos? — Contexto del proyecto

Este documento reúne el contexto funcional, técnico y organizativo compartido por el equipo. Su finalidad es permitir que otro integrante o asistente de IA continúe el proyecto sin depender del historial de una conversación.

**Estado declarado:** análisis, diseño e instalación inicial del backend. Las funcionalidades descritas son requisitos; su presencia en este documento no significa que estén implementadas.

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
| Usuario registrado   | Tiene las funciones de consulta del invitado, favoritos vinculados a su cuenta y edición de su perfil.                       |
| Administrador        | Tiene acceso protegido a la gestión de recetas en la aplicación móvil y a la gestión de cuentas mediante una plataforma web. |

La consulta del catálogo no debe exigir registro. Las operaciones administrativas sí requieren acceso autorizado.

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
- Opción de reproducir las instrucciones mediante audio para seguirlas mientras se cocina.

La herramienta de audio, el modo de lectura y su disponibilidad sin conexión están pendientes de definición.

### 4.5. Favoritos

**Con cuenta:** los favoritos quedarán vinculados al usuario y podrá recuperarlos al cambiar de teléfono o reinstalar la aplicación, al acceder de nuevo a su cuenta.

**Sin cuenta:** los favoritos se guardarán únicamente en el dispositivo. El requisito comunicado es que no se recuperen al cambiar de equipo o reinstalar la aplicación; no se debe prometer sincronización para invitados.

Guardar una receta como favorita no confirma, por sí solo, la descarga de todo su contenido para uso sin conexión. También queda pendiente definir qué ocurre con los favoritos locales cuando un invitado crea una cuenta o inicia sesión.

### 4.6. Mi perfil

Los usuarios registrados podrán:

- Consultar y editar su nombre completo.
- Consultar y editar su correo electrónico.
- Agregar o cambiar su fotografía de perfil.

Cuando un invitado ingrese a «Mi perfil», verá una imagen anónima y un mensaje de registro en una ventana flotante sobre esa imagen, con acceso a la creación de una cuenta.

### 4.7. Administración de recetas en la aplicación móvil

La publicación y actualización de recetas serán responsabilidad exclusiva del administrador.

Desde una sección protegida de la aplicación móvil podrá:

- Registrar recetas.
- Editar recetas.
- Eliminar recetas.

En el formulario de registro de recetas habrá inicialmente **dos campos para escribir los pasos**. El administrador podrá agregar más campos según la cantidad de instrucciones necesarias.

Los dos campos iniciales describen el comportamiento del formulario; todavía no se ha confirmado que todas las recetas deban tener un mínimo obligatorio de dos pasos.

Los invitados y usuarios registrados comunes no publicarán recetas dentro del alcance acordado.

### 4.8. Administración de cuentas en la plataforma web

Desde una plataforma web, el administrador podrá:

- Consultar usuarios registrados.
- Actualizar una cuenta.
- Deshabilitar una cuenta cuando sea necesario.

La gestión de recetas se ha ubicado en la aplicación móvil y la gestión de cuentas en la web. No trasladar ni duplicar esas funciones automáticamente. La deshabilitación de una cuenta no equivale a eliminarla; sus efectos concretos sobre sesiones y acceso están pendientes.

## 5. Origen de las recetas

El equipo desarrollará su propio backend y mantendrá un catálogo propio. Las recetas ingresadas por el administrador se guardarán en la base de datos del sistema, lo que permitirá incorporar preparaciones propias y peruanas en español.

El equipo descartó depender de una API gratuita externa como fuente principal porque las opciones consideradas no cubrían sus necesidades de idioma, recetas peruanas y límites de acceso gratuito. Esto describe la evaluación del equipo; no implica que ninguna API del mercado ofrezca recetas en español.

No hay una integración externa de recetas aprobada como parte del alcance actual.

## 6. Tecnologías y distribución de responsabilidades

| Parte del sistema                  | Tecnología o herramienta acordada | Función                                                                       |
| ---------------------------------- | --------------------------------- | ----------------------------------------------------------------------------- |
| Aplicación móvil                   | React Native, Expo y TypeScript   | Pantallas e interacción con el usuario.                                       |
| Backend                            | Laravel con PHP                   | API, reglas de negocio, acceso a datos y soporte del panel web.               |
| Base de datos                      | MariaDB mediante XAMPP            | Almacenar usuarios, recetas, ingredientes, categorías y favoritos de cuentas. |
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
4. Si los favoritos incluyen contenido descargado para consulta sin conexión.
5. Tratamiento de favoritos locales al iniciar sesión o registrarse y posibles duplicados.
6. Herramienta de audio y comportamiento sin conexión.
7. Método de autenticación de la API, recuperación de contraseña y comprobación de cambios de correo.
8. Creación inicial del administrador y permisos entre varios administradores, si los hubiera.
9. Efectos de deshabilitar cuentas y eliminar recetas que otros usuarios tengan en favoritos.
10. Elección definitiva de Blade o Livewire para el panel web.
11. Diseño de tablas, relaciones, rutas de API y reglas de validación concretas.
12. Reparto detallado de programación del backend, calendario y alcance de cada entrega.
13. Alojamiento, copias de seguridad y acceso al backend desde dispositivos para las pruebas.

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
