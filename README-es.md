> 📘 **This document is also available in English:**  
> [Read in English](README.md)

## Tabla de contenidos

*   **[✨ Características y Pilares Arquitectónicos](#-características-y-pilares-arquitectónicos)**
    *   [Filosofía, propósito e inspiración](#filosofía-propósito-e-inspiración)
    *   [1. Pilares Arquitectónicos: Características Innovadoras](#1-pilares-arquitectónicos-características-innovadoras)
    *   [2. Fundamentos: Prácticas y Estándares de la Industria](#2-fundamentos-prácticas-y-estándares-de-la-industria)
    *   [3. Experiencia de Desarrollador (DX) y Testing](#3-experiencia-de-desarrollador-dx-y-testing)

*   **[⚙️ El Ciclo de Vida de una Petición](#️-el-ciclo-de-vida-de-una-petición)**
    *   *Un desglose paso a paso de cómo el framework procesa una solicitud, desde la entrada HTTP hasta la respuesta final.*

*   **[📖 Guía de Uso](#-guía-de-uso)**
    *   *Guía práctica para empezar a desarrollar, cubriendo desde las convenciones de nombrado hasta ejemplos de código detallados.*
    *   **[1. Organización del Código y Convenciones](#1-organización-del-código-y-convenciones)**
    *   **[2. Enrutamiento](#2-enrutamiento)**
    *   **[3. Creando un Controlador Multi-Capa (Guía Práctica)](#3-creando-un-controlador-multi-capa-guía-práctica)**
    *   **[4. Internacionalización (Traducciones)](#4-internacionalización-traducciones)**
    *   **[5. El Modelo `User`: El Origen del Contexto y la Seguridad](#5-el-modelo-user-el-origen-del-contexto-y-la-seguridad)**

## ✨ Características y Pilares Arquitectónicos
### Filosofía, propósito e inspiración

El propósito de este framework es permitir la construcción de un ecosistema de aplicaciones sobre una base de código compartida. Permite extender una aplicación principal con lógicas específicas para grupos de marcas (ej. `vwgroup`) y marcas individuales (ej. `audi`), manteniendo la consistencia de las funcionalidades base.

Para ello, su núcleo es una **arquitectura de N-capas híbrida (vertical y horizontal) soportada por un Contenedor de Inyección de Dependencias con Motor de autocarga y resolución de capas**. Las capas verticales gestionan la herencia de funcionalidades (multi-marca) y las horizontales los permisos (multi-rol). Este modelo arquitectónico es directamente aplicable tanto a plataformas SaaS **multi-tenant** como a aplicaciones que requieren distintos niveles de funcionalidad por rol y/o suscripción, ej.: HubSpot.

Inspirado en un problema de negocio real, fue desarrollado para soportar **código legacy** y tecnologías de vista como **XSLT**, añadiéndoles capacidades modernas.

La arquitectura implementa una separación de responsabilidades siguiendo el patrón **MVCS (Model-View-Controller-Service)**. Los **Controladores** actúan como coordinadores: reciben la petición, delegan la lógica de negocio a los **Servicios** y ensamblan la respuesta.

### 1. Pilares Arquitectónicos: Características Innovadoras

Estas son las características que definen la identidad y la capacidad arquitectónica del framework.

*   **Arquitectura de Capas Híbrida (Vertical y Horizontal)**:
    *   **Capas Verticales (Multi-Marca)**: Un sistema de herencia a nivel de aplicación (`1base` -> `2vwgroup` -> `3audi`) que permite la sobreescritura jerárquica de cualquier componente (código, vistas, traducciones y assets). Esta herencia se aplica a todo el stack: desde el código PHP (Controladores, Servicios, Modelos...) hasta los recursos de frontend (Vistas, CSS, JS e imágenes).
    *   **Capas Horizontales (Multi-Rol)**: Un despachador de acciones que resuelve qué método del Controlador ejecutar basándose en el rol del usuario (`_Admin`, `_Manager`), integrando la lógica de permisos en la propia arquitectura. También es posible habilitar el fallback de un Controllador a roles inferiores o sobreescribir un Controlador completo para que lo use un rol determinado. Ej.: `EmissionsSPAController_Manager.php`.

*   **Motor de Invocación Padre Dinámica (`parentResponse`)**: Implementa el patrón **Decorator** a nivel de arquitectura. A través de **metaprogramación** (`debug_backtrace`, `Reflection`), permite a un método de una capa hija invocar a su contraparte en la capa padre, **preservando el estado (`$this`)** del objeto actual. Esto facilita la extensión de funcionalidades sin duplicar código.

*   **Contenedor de Inyección de Dependencias (DI) con Autocarga Implícita y Cacheo de Instancias**: El framework opera bajo el principio de **"Convención sobre Configuración"**. Al seguir una estructura de directorios y una convención de nombrado (`ClassName_LayerSuffix`), el `LayerResolver` actúa como un **contenedor de DI y autoloader implícito**. Inyecta dependencias en los constructores y, para optimizar el rendimiento, **cachea las instancias de los servicios y helpers (componentes sin estado)** una vez resueltas. Esto asegura que se utilice la misma instancia (`singleton` a nivel de petición) en todo el ciclo de vida de la aplicación, evitando la sobrecarga de creación de objetos. Los **modelos (componentes con estado) se excluyen deliberadamente de este cacheo** para prevenir bugs sutiles de estado compartido. 
  
*   **Patrón Service Locator y Acceso Global (`App::getInstance()`)**: La clase `App` se implementa como un **Singleton**, siendo el único componente con acceso estático global a través de `App::getInstance()`. Esto la convierte en un **Service Locator** que sirve como "puerta de entrada" al núcleo de la aplicación, permitiendo el acceso controlado a componentes clave como el Contenedor de DI (`LayerResolver`). Su uso está pensado como un recurso para casos específicos, favoreciendo siempre la inyección de dependencias explícita como patrón principal.  

*   **Fábrica de Modelos Jerárquica**: Desacopla los modelos de la lógica de conexión a la base de datos. La `ModelFactory` resuelve la conexión PDO correcta basándose en el contexto (ej. usando el `id_dealer` del usuario para BBDD multi-tenant) y la inyecta en el modelo. La configuración de en qué BD se encuentra cada modelo es declarativa en `config.php`.

*   **Motor de Vistas Jerárquico con Pre-compilador**: Aporta un sistema de **herencia y composición** a las plantillas XSLT. La clase `View` actúa como un pre-compilador que resuelve marcadores personalizados (`[PARENT_TEMPLATE_PATH]`, `[VIEW_PATH:...]`), aplicando un **fallback recursivo** a través de las capas y cacheando el resultado final.

### 2. Fundamentos: Prácticas y Estándares de la Industria

El framework se asienta sobre patrones de diseño probados, facilitando su adopción.

*   **Patrón Front Controller**: Un único punto de entrada (`public/index.php`) gestiona el ciclo de vida de la aplicación. (Similar a **Symfony**, **Laravel**).

*   **Enrutador Declarativo**: Un archivo `routes.php` mapea verbos HTTP y URIs a acciones de controlador. Soporta parámetros en ruta mediante expresiones regulares. (Similar a **Laravel**, **Slim**).

*   **Configuración Centralizada y Declarativa**: Un único archivo (`config.php`) define toda la "personalidad" de la aplicación: capas, roles, conexiones a BBDD y tipos de componentes. (Filosofía de configuración similar a **Django**.)
  
*   **Middleware de Autenticación y Contexto**: Un `AuthService` actúa como middleware, validando la petición vía **tokens de sesión** en BBDD y estableciendo el **contexto de la aplicación** (capa vertical y rol horizontal) para el resto del ciclo. Incluye protección contra **session hijacking** y **fuerza bruta**.

*   **ORM estilo Active Record**: Modelos con funcionalidades CRUD (`find`, `save`, `delete`) y **carga perezosa de relaciones** vía propiedades mágicas, simplificando la interacción con la BBDD. (Similar a **Eloquent de Laravel**, **Active Record de Rails**).

*   **Respuestas Basadas en Objetos**: Los controladores devuelven objetos (`ViewResponse`, `JsonResponse`), desacoplando la lógica de la generación de la salida final. (Similar a **Symfony**).

*   **Colecciones Funcionales**: Los resultados de consultas se envuelven en un objeto `Collection` con una API fluida (`map`, `filter`, `pluck`). (Similar a las **Colecciones de Laravel**).

*   **Soporte Híbrido para Código Legacy**: El enrutador puede coexistir con scripts PHP tradicionales, permitiendo una estrategia de **modernización progresiva**.

### 3. Experiencia de Desarrollador (DX) y Testing

El framework está diseñado para ser mantenible y fácil de depurar.

*   **Arquitectura Orientada a Pruebas**: Proporciona un entorno de pruebas (`TestApp`) que permite **mockear servicios y factories** para ejecutar tests de integración contra una base de datos en memoria (**SQLite::memory:**), simulando el ciclo de vida completo de la petición.

*   **Panel de Depuración con Suplantación de Identidad (Impersonation)**: Herramienta de desarrollo que permite cambiar en tiempo real la **capa vertical y el rol horizontal** desde el frontend para depurar diferentes contextos de usuario sin cambiar de sesión.

*   **Logging Avanzado**: El sistema de manejo de excepciones captura un **stack trace completo y enriquecido** en un formato estructurado y legible, facilitando la identificación de la causa raíz de los errores.

*   **Sistema de Traducción por Capas**: Los archivos de internacionalización (i18n) se cargan y fusionan en cascada siguiendo la jerarquía de capas, permitiendo sobreescrituras específicas para cada marca o nivel.  

## ⚙️ El Ciclo de Vida de una Petición

Para comprender cómo se orquestan estos pilares, es útil seguir el flujo de una petición a través del framework:

1.  **Punto de Entrada (`index.php`)**: La petición llega y se inicializa la `App`, cargando la configuración y preparando el `include_path` con los directorios de las capas.
2.  **Enrutamiento (`Router`)**: Se analiza la URI y el verbo HTTP para encontrar una coincidencia en `routes.php`, generando un "plan de acción" (controlador, método, parámetros).
3.  **Middleware de Autenticación (`AuthService`)**: Actúa como un *guardia*. Intercepta el plan de acción, valida el token de sesión del usuario contra la base de datos y, si tiene éxito, lee los campos `layer_user` y `level_user` del modelo `User`.
4.  **Establecimiento de Contexto**: El `AuthService` informa a la instancia de `App` de la **capa vertical** y el **rol horizontal** del usuario. Este contexto gobernará el resto del ciclo de vida.
5.  **Despacho Inteligente (`App::dispatchAction`)**: La `App` utiliza el contexto para resolver la acción final, siguiendo un orden de prioridad:
    1.  ¿Existe un Controlador de reemplazo para el rol (ej. `DashboardController_Manager.php`)?
    2.  Si no, ¿existe un método específico para el rol en el Controlador de la capa más alta (ej. `showProfile_Admin()`)?
    3.  Si no, ¿existe un método para un rol inferior (si el fallback está activo)?
    4.  Como último recurso, se ejecuta el método base (ej. `showProfile()`).
6.  **Construcción del Grafo de Dependencias (`LayerResolver`)**: Antes de instanciar el Controlador, el `LayerResolver` orquesta la construcción de su **grafo de dependencias completo**. De forma recursiva, analiza el constructor de cada componente solicitado (Servicios, Helpers, etc.) y resuelve sus propias dependencias primero. Durante todo este proceso, utiliza el contexto de capa del usuario para garantizar que se inyecte la implementación más específica de cada componente, construyendo el árbol de objetos de adentro hacia afuera.
7.  **Ejecución**: El método del Controlador se ejecuta. Utiliza los servicios inyectados para la lógica de negocio y devuelve un objeto de respuesta (`ViewResponse`, `JsonResponse`).
8.  **Middleware de Respuesta (`App::applyMiddleware`)**: La `App` intercepta el objeto de respuesta devuelto por el controlador. Esto permite aplicar una capa de middleware final para modificar o enriquecer la respuesta antes de su envío.
9.  **Envío de Respuesta (`App::sendResponse`)**: La `App` recibe el objeto de respuesta final, renderiza la plantilla (si es una `ViewResponse`), establece las cabeceras HTTP y envía el contenido al cliente.


## 📖 Guía de Uso

### 1. Organización del Código y Convenciones

El framework opera bajo el principio de **"Convención sobre Configuración"**. Para que la autocarga, la herencia de capas y la inyección de dependencias funcionen de forma predecible, es necesario seguir un conjunto de reglas de nombrado y estructura de archivos. El archivo `config.php` es el "plano" que define estas reglas.

---
### Arquitectura: Las Dos Dimensiones de Capas

El framework se organiza en dos ejes: capas verticales para la herencia de funcionalidades y capas horizontales para el control de acceso por roles.

#### 1. Capas Verticales (Multi-Marca): La Herencia Jerárquica

Definen la funcionalidad base y cómo se extiende o sobreescribe en niveles superiores. Es ideal para plataformas multi-tenant o familias de productos. Esta jerarquía se define en el array `layers` de `config.php`.

```php
// config.php
'layers' => [
    'audi' => [
        'directory' => '3audi',      // Nombre de la carpeta.
        'suffix'    => '3Audi',      // Sufijo para las clases (ej. _3Audi).
        'layer'     =>  3,           // Nivel de prioridad (más alto = más específico).
    ],
    // ...
    'base' => [
        'directory' => '1base',
        'suffix'    => 'Base',
        'layer'     =>  1,
    ]
],
```

#### 2. Capas Horizontales (Multi-Rol): Control del Flujo de Ejecución

Definen qué código se ejecuta basándose en el rol del usuario. **Este sistema se aplica exclusivamente a la capa de Controladores**, ya que su propósito es dirigir el flujo de la petición, no alterar la lógica de negocio, que permanece encapsulada en los servicios. El mapeo de roles se define en el array `user_roles` de `config.php`.

```php
<?php
// config.php
'user_roles' => [
    3 => 'Admin',
    2 => 'Manager',
    1 => 'Seller',
],
```

Existen dos estrategias para implementar la lógica de roles:

**Opción A: Sufijo en el Método (Para Extensiones y Modificaciones)**

Es la forma más común. Se añade un sufijo `_[NombreDelRol]` a un método para crear una versión específica para ese rol. Es ideal para añadir, modificar o quitar porciones de lógica a una acción existente.

*   `showProfile()`: Lógica para todos los usuarios.
*   `showProfile_Manager()`: Lógica específica que extiende o modifica `showProfile()` para el rol `Manager`.

**Opción B: Sufijo en la Clase del Controlador (Para Reemplazos Completos)**

Se utiliza cuando la lógica para un rol es tan radicalmente diferente que no tiene sentido extender el controlador base. En este caso, se crea un archivo de controlador completamente nuevo con el sufijo del rol.

*   `DashboardController.php`: Define el dashboard para usuarios estándar.
*   `DashboardController_Manager.php`: Define un dashboard **totalmente diferente** solo para el rol `Manager`.

Cuando una petición llega a la acción `DashboardController@show`, si el usuario es `Manager`, el framework ni siquiera cargará el `DashboardController` normal; cargará y ejecutará directamente `DashboardController_Manager`, reemplazando por completo el flujo para esa ruta y ese rol.

---
#### Reglas de Nombrado

Para que la arquitectura descrita funcione, sigue estas convenciones:

1.  **Nombre Base del Componente**: Debe incluir el tipo de componente como sufijo.
    *   **Correcto**: `ProfileController`, `AuthService`, `MenuHelper`.
    *   **Incorrecto**: `Profile`, `Auth`, `Menu`.

    *   **Excepción para Modelos**: Los modelos son el único caso especial. **No llevan sufijo** en su nombre base.
    *   Nombre Base: `User` (no `UserModel`).
    *   Archivo: `1base/models/User.php`.
    *   Clase: `class User_Base extends ORM`.
  
2.  **Nombre del Archivo**: `[Nombre Base del Componente].php`. Para controladores específicos de un rol, se añade el sufijo del rol: `[Nombre Base]_[Rol].php`.
    *   Ejemplos: `ProfileController.php`, `AuthService.php`, `DashboardController_Manager.php`.

3.  **Ubicación del Archivo**: `[DirectorioCapa]/[CarpetaComponente]/[NombreArchivo].php`.
    *   Ejemplos: `1base/controllers/ProfileController.php`, `3audi/controllers/DashboardController_Manager.php`.

4.  **Nombre de la Clase**: `[Nombre Base del Componente]_[SufijoDeCapa]`.
    *   Ejemplos: `ProfileController_Base`, `AuthService_2GrupoVW`.

5.  **Nombre del Método (para roles)**: `[Nombre de la acción]_[NombreDelRol]`.
    *   Ejemplo: El método base es `showProfile()`. La versión para el rol `Manager` se llamará `showProfile_Manager()`.

6.  **Herencia de Clases**: Una clase de una capa superior **debe extender** a su contraparte de la capa inmediatamente inferior (excepto en los controladores de reemplazo total por rol).
    *   Ejemplo: `class ProfileController_2GrupoVW extends ProfileController_Base`.

7.  **Herencia de Clases Base (¡Importante!)**: La **primera clase de la cadena de herencia** de cualquier componente debe extender a su clase base abstracta correspondiente. Esto es crucial para heredar los métodos de ayuda y funcionalidades del framework.

    Por ejemplo, si un `NewController` se crea por primera vez en la capa `2vwgroup`, su clase se declarará así:

    `class NewController_2GrupoVW extends Controller`

    Luego, si se extiende en una capa superior, seguirá la herencia normal:

    `class NewController_3Audi extends NewController_2GrupoVW`

    Las herencias base obligatorias son:
    *   `MyController_Suffix` **extends** `Controller`
    *   `MyService_Suffix` **extends** `Service`
    *   `MyHelper_Suffix` **extends** `Helper`
    *   `MyModel_Suffix` **extends** `ORM`

    No seguir esta regla te dejará sin acceso a helpers como `getView()`, `translate()`, `parentResponse()`, etc. Puedes explorar el código de estas clases base en `lib/components/` para ver todas las herramientas disponibles. A su vez, todas estas clases heredan de `Component`, la clase raíz que provee funcionalidades comunes a todos los componentes del framework.

### 2. Enrutamiento

El archivo `routes.php` mapea URLs a acciones de controlador. La estructura es un array organizado por verbo HTTP (`GET`, `POST`, etc.).

#### Sintaxis Básica

```php
// routes.php
'VERBO_HTTP' => [
    'patrón/de/la/url' => [
        'controller' => 'NombreController', 
        'action'     => 'nombreMetodo'
    ],
],
```

#### Ejemplos

**Ruta Estática:**

Una petición `GET` a `/login` ejecuta el método `showLogin` en `AuthController`.

```php
'GET' => [
    '/login' => [
        'controller' => 'AuthController', 
        'action'     => 'showLogin'
    ],
],
```

**Ruta con Parámetros:**

Se usan expresiones regulares `(...)` para capturar segmentos de la URL.

```php
<?php
'GET' => [
    '/api/configurator/session/(\d+)/colors' => [
        'controller' => 'ConfiguratorController',
        'action'     => 'getColorsForSessionAPI', 
    ],
],
```

En este caso, una visita a `/api/configurator/session/123/colors` ejecutará `getColorsForSessionAPI($sessionId)` en `ConfiguratorController`, donde `$sessionId` tendrá el valor `'123'`. Los parámetros capturados se pasan como argumentos al método en el orden en que aparecen.


### 3. Creando un Controlador Multi-Capa (Guía Práctica)

El Controlador es el punto de entrada para cualquier acción del usuario. En este framework, los controladores no solo gestionan peticiones, sino que están preparados para gestionar la arquitectura de capas.

Vamos a crear una sección "Mi Perfil" para ilustrar los conceptos clave de forma rápida y variada, demostrando la potencia y flexibilidad de la capa de Controller.

---
#### 0. Como crear Servicios, Modelos, Helpers... etc para que sean inyectados correctamente

```php

//IMPORTANTE: No hay ni "use" ni "require", solo siguiendo la convención automáticamente se cargarán las clases necesarias.

/**
 * Interfaz para ProfileService.
 * Es esencial implementar esta interface en el componente Padre de los Servicios, Helpers y Modelos...
 * El motor recursivo utilizará esta referencia para resolver la inyección de dependencias con la clase de la capa adecuada en función de los permisos del usuario: ProfileController_Base, ProfileController_2GrupoVW... etc
 */
interface SomeService{}

//Archivo en: 1base/services/SomeService.php
class someService_Base extends Service implements SomeService{
    ...
}

//Archivo en: 2vwgroup/services/SomeService.php
class someService_2Grupo_VW extends someService_Base{
    ...
}
```

#### 1. El Controlador Base (Capa `1base`)

Todo empieza en la capa `1base`. Aquí definimos la funcionalidad principal que estará disponible para todas las capas superiores.

**Archivo**: `1base/controllers/ProfileController.php`

```php

// En el Controler NO es necesaria una interface. Un Controller nunca se inyectará.
// interface ProfileController {}

class ProfileController_Base extends Controller //implements ProfileController
//Nota: El controlador de la primera capa siempre debe extender a "Controller" para qué él y sus hijos de capas superiores puedan acceder a los métodos que permiten una buena experiencia de desarollo
{
    protected TranslatorService $translator;
    protected SomeService $someService;

    /**
     * El constructor declara las dependencias.
     * Simplemente "pides" lo que necesitas y el framework lo inyecta.
     * 
     * NOTA: siempre hay que definir el nombre sin capa, el "LayerResolver" se encargará de darle a cada usuario la capa más superior a la que tenga acceso y el servicio exista.
     */
    public function __construct(TranslatorService $translator, SomeService $someService)
    {
        $this->translator = $translator;
        $this->someService = $someService;
    }

    /**
     * Muestra la página de perfil base.
     */
    public function showProfile()
    {   
        // ✨ Helper del "Controller" padre: getContext() permite acceder a datos del contexto de "App"
        $user = $this->getContext('user'); 

        // ✨ Helper del "Controller" padre: getView() obtiene una instancia de "View" con la plantilla de la vista "profile" y en el segundo le inyectamos datos a la plantilla
        $view = $this->getView('profile', [
            // ✨ Helper del "Controller" padre: translate() -> accede al servicio de traducción y traduce (solo si ha sido inyectado)
            'profile_title' => $this->translate('profile_page_title'), 
            'user_name'     => $user->name,
            'user_email'    => $user->username . '@example.com',
        ]);
        
        // ✨ Helper de "View": add(key, value) inyecta datos en la plantilla
        // En este caso, añadimos un script JS específico a la vista.
        $view->add('scripts', '/1base/js/profile_page.js');

        // ✨ Helper del "Controller" padre: view(), 
        // Envuelve la instancia de View en un "ResponseView"
        // Importante: no sirve devolver la lista directamente, tenemos que llamar a la función para que se envuelva correctamente y "App" pueda procesarlo
        return $this->view($view);
    }

    /**
     * Muestra la página de perfil para el rol "Manager".
     */
    public function showProfile_Manager()
    {
        
        // Obtenemos la "ResponseView" del método general (sin usuario) y la desempaquetamos
        // Esto nos ayuda a poder modificar y extender sin necesidad de rehacer de cero la vista para roles especificos
        $view = $this->showProfile()->getContent();
        // Importante: getContent() es un método de "ViewResponse" que devuelve la vista

        // Añadimos información exclusiva para el Manager.
        $view->set('manager_badge', $this->translate('profile_manager_badge'));
        $view->set('is_manager', true);
        
        // ✨ Helper de "View": Añade un bloque de datos JSON para que lo use el JavaScript del frontened.
        // Esto se renderizará como <script type="application/json">...</script>
        $view->addJson('manager-data', [
            'canEdit' => true, 
            'apiEndpoint' => '/api/profile/save'
        ]);

        return $this->view($view); //devolvemos la vista con envolviendola con "view()"
    }
}
```

#### 2. Extendiendo el Controlador (Capa `2vwgroup`)

Ahora, la capa `2vwgroup` necesita personalizar la página de perfil. En lugar de copiar y pegar, simplemente **extiende** el controlador base.

**Archivo**: `2vwgroup/controllers/ProfileController.php`

```php
class ProfileController_2GrupoVW extends ProfileController_Base
{

    /**
     * Sobrescribimos el método base para añadir un detalle específico de VW.
     */
    public function showProfile() 
    {
        // Reutilizamos la lógica del padre (`ProfileController_Base::showProfile`).
        // `parentResponse()` ejecuta el método correspondiente de la capa inferior y devuelve su objeto "View" o "Json" desempaquetado, listo para ser modificado.
        $view = $this->parentResponse();

        // Añadimos nuestro toque personal.
        $view->set('brand_message', $this->translate('profile_vw_brand_message'));
        $view->set('page_background_image', '/2vwgroup/img/BackgroundVW.jpg');

        return $this->view($view);              
    }

    /**
     * Un método específico para el rol "Admin", que solo existe en esta capa.
     */

    // NOTA: se puede habilitar el fallback a roles inferiores (en vez de directamente al básico)
    // Creando una propiedad así y poniendola "true;        
    public $userLevelFallback = false;

    public function showProfile_Admin() 
    {

        // Como está en "false y el método "showProfile_Admin" no existe en su clase "ProfileController_Base", el framework hace un fallback a `showProfile()`
        $view = $this->parentResponse();
        //NOTA: Si estuviera en "true", haría fallback a "showProfile_Manager"

        // Añadimos los datos del Admin.
        $view->set('admin_secret_code', 'TOP_SECRET_ADMIN_DATA');
        $view->set('is_admin', true);
        
        // ✨ Helper del "Controller" padre: Podemos eliminar datos o scripts añadidos por el padre si es necesario.
        $view->removeValue('scripts', '/1base/js/profile_page.js'); //elimina un script specífico
        $view->remove('scripts'); //elimina todos los scripts

        $view->add('scripts', '/2vwgroup/js/admin_profile.js'); // Y añadir uno nuevo.

        return $this->view($view); 
    }

    /**
     * ✨ SHOWCASE: Endpoint API para demostrar los helpers JSON y su enriquecimiento.
     */
    public function getProfileApi()
    {
        $user = $this->getContext('user');
        
        if (empty($user)) {
            // El helper `jsonError` formatea una respuesta de error estándar.
            return $this->jsonError($this->translate('api_error_unauthorized'), 401);
        }

        $profileData = [
            'name' => $user->name,
            'dealer' => $user->id_dealer,
        ];
        
        // 1. Creamos una respuesta JSON base.
        $baseResponse = $this->json($profileData);

        // 2. La enriquecemos con metadatos adicionales.
        $finalResponse = $this->enrichJsonResponse($baseResponse, [
            'metadata' => [
                'timestamp' => time(),
                'source' => 'VWGroup API'
            ]
        ]);
        
        // 3. Devolvemos la respuesta final.
        return $finalResponse;
    }
}
```

#### Resumen de Conceptos Clave

*   **Interfaz como Contrato**: Define `interface MiComponente {}` para que el `LayerResolver` pueda encontrar e inyectar tus clases.
*   **Inyección por Constructor**: Simplemente declara tus dependencias con su tipo (`TranslatorService`) en el `__construct` y el framework hará el resto.
*   **`parentResponse()`**: Ejecuta la lógica del padre de capas inferiores y te da su resultado (`View` o `Json`) para que puedas extenderlo.
*   **Sufijos de Rol (`_Manager`, `_Admin`)**: La forma de crear lógica específica para un rol. Si un método con sufijo no existe en la jerarquía, el framework busca el método base sin sufijo como **fallback**.
*   **Helpers Methods(`getView`, `view`, `json`, `translate`)**: La clase `Controller` base te da un conjunto de atajos para hacer tu código más limpio, legible y consistente.
*   **Gestión de Assets (`$view->add`, `$view->removeValue`)**: Los controladores pueden añadir o quitar dinámicamente archivos JS y CSS de las vistas, permitiendo un control granular.
*   **Puente de Datos JS (`$view->addJson`)**: Permite pasar datos de forma segura y estructurada desde PHP a tu JavaScript de frontend.
*   **Respuestas API Robustas (`json`, `jsonError`, `enrichJsonResponse`)**: Un conjunto de herramientas para construir y estandarizar las respuestas de tus endpoints API.

### 4. Internacionalización (Traducciones)

El framework incluye un sistema de internacionalización que, al igual que los demás componentes, opera en base a la jerarquía de capas.

#### 1. Crear Archivos de Traducción

Los archivos de traducción son simples arrays de PHP que devuelven pares `clave => valor`. Deben ubicarse en la carpeta `services/translations/` de la capa correspondiente. El nombre del archivo debe ser el código del idioma (ej. `es.php`, `en.php`).

**Ejemplo de archivo**: `1base/services/translations/es.php`

```php
// 1base/services/translations/es.php
return [
    'profile_page_title' => 'Mi Perfil',
    'welcome_message'    => 'Bienvenido, %s',
];
```

#### 2. Sobrescribir Traducciones (Herencia de Capas)

Para sobrescribir una traducción en una capa superior, simplemente crea un archivo con el mismo nombre y redefine la clave. El `TranslatorService` fusionará los archivos en cascada, dando prioridad a las capas más altas.

**Ejemplo de archivo**: `2vwgroup/services/translations/es.php`

```php
// 2vwgroup/services/translations/es.php
return [
    // Sobrescribimos esta clave de la capa `1base`
    'profile_page_title' => 'Perfil de Usuario - Grupo VW',
    
    // Y añadimos una nueva, específica para esta capa
    'profile_vw_brand_message' => 'Estás en el portal del Grupo Volkswagen.',
];
```

#### 3. Usar el Traductor en un Controlador

Para usar las traducciones, inyecta el `TranslatorService` en el constructor de tu controlador y utiliza el helper `translate()`.

```php
class ProfileController_Base extends Controller
{
    protected TranslatorService $translator;

    public function __construct(TranslatorService $translator)
    {
        $this->translator = $translator;
    }

    public function showProfile()
    {
        // ...
        $title = $this->translate('profile_page_title');
        
        // Para claves con placeholders (%s, %d...), pasa un array como segundo argumento.
        $welcome = $this->translate('welcome_message', [$user->name]);
        // ...
    }
}
```

El `TranslatorService` detectará automáticamente el idioma del usuario (vía URL, cookie o cabeceras del navegador) y cargará el conjunto de traducciones correcto.


### 5. El Modelo `User`: El Origen del Contexto y la seguridad

Toda la magia de las capas verticales y horizontales comienza con el usuario autenticado. El `AuthService` utiliza el modelo `User` para determinar el contexto de la aplicación, basándose en dos campos clave en la tabla de la base de datos.

#### Campos Requeridos en la Tabla de Usuarios

Para que el sistema de capas funcione, tu tabla de usuarios (o la entidad que uses para la autenticación) debe contener dos columnas:

1.  **`layer_user`** (Entero): Este campo almacena el **nivel de capa vertical** máximo al que el usuario tiene acceso. El valor debe corresponder a la clave `layer` definida en el array `layers` de `config.php`.
    *   Ejemplo: Un `layer_user` con valor `2` dará al usuario acceso a los componentes de `1base` y `2vwgroup`, pero no a los de `3audi`.

2.  **`level_user`** (Entero): Este campo almacena el **nivel de rol horizontal** del usuario. El valor debe corresponder a una de las claves del array `user_roles` de `config.php`.
    *   Ejemplo: Un `level_user` con valor `3` hará que el despachador busque métodos con el sufijo `_Admin` en los controladores.

#### El Flujo de Autenticación

1.  Un usuario inicia sesión.
2.  El `AuthService` verifica las credenciales y carga el registro del usuario desde la base de datos a través del modelo `User`.
3.  El `AuthService` lee los valores de `layer_user` y `level_user` de la instancia del modelo.
4.  Finalmente, establece estos dos valores en el contexto global de la `App`.

A partir de ese momento, cada vez que el `LayerResolver` necesite encontrar un componente o el Despachador necesite ejecutar una acción, consultarán estos valores de contexto para tomar la decisión correcta, aplicando así de forma dinámica la arquitectura de capas a la petición del usuario.