> 📘 **Este documento también está disponible en español:**  
> [Leer en español](README-es.md)

> 🌳 This backend framework works alongside **LifeTree**: my frontend framework, available on [GitHub](https://github.com/david-camba/lifetree)

> 🚀 Live Demo Available: [Access the Demo🌐](https://david.camba.com)
> *Best viewed on desktop | Deployed with Docker on Google Cloud*
 

## Table of Contents

*   **[✨ Architectural Features and Pillars](#-architectural-features-and-pillars)**
    *   [Philosophy, Purpose, and Inspiration](#philosophy-purpose-and-inspiration)
    *   [1. Architectural Pillars: Innovative Features](#1-architectural-pillars-innovative-features)
    *   [2. Fundamentals: Industry Practices and Standards](#2-fundamentals-industry-practices-and-standards)
    *   [3. Developer Experience (DX) and Testing](#3-developer-experience-dx-and-testing)

*   **[⚙️ The Request Lifecycle](#️-the-request-lifecycle)**
    *   *A step-by-step breakdown of how the framework processes a request, from the HTTP input to the final response.*

*   **[📖 Usage Guide](#-usage-guide)**
    *   *A practical guide to start developing, covering everything from naming conventions to detailed code examples.*
    *   **[1. Code Organization and Conventions](#1-code-organization-and-conventions)**
    *   **[2. Routing](#2-routing)**
    *   **[3. Creating a Multi-Layer Controller (Practical Guide)](#3-creating-a-multi-layer-controller-practical-guide)**
    *   **[4. Internationalization (Translations)](#4-internationalization-translations)**
    *   **[5. The `User` Model: The Origin of Context and Security](#5-the-user-model-the-origin-of-context-and-security)**

## ✨ Architectural Features and Pillars
### Philosophy, Purpose, and Inspiration

The purpose of this framework is to enable the construction of an ecosystem of applications on a shared codebase. It allows a main application to be extended with specific logic for brand groups (e.g., `vwgroup`) and individual brands (e.g., `audi`), while maintaining the consistency of core functionalities.

To achieve this, its core is a **hybrid N-layer architecture (vertical and horizontal) supported by a Dependency Injection Container with an autoloading and layer resolution engine**. The vertical layers manage functionality inheritance (multi-brand), and the horizontal layers manage permissions (multi-role). This architectural model is directly applicable to both **multi-tenant** SaaS platforms and applications that require different levels of functionality by role and/or subscription, e.g., HubSpot.

Inspired by a real business problem, it was developed to support **legacy code** and view technologies like **XSLT**, adding modern capabilities to them.

The architecture implements a separation of concerns following the **MVCS (Model-View-Controller-Service)** pattern. **Controllers** act as coordinators: they receive the request, delegate business logic to **Services**, and assemble the response.

### 1. Architectural Pillars: Innovative Features

These are the features that define the identity and architectural capability of the framework.

*   **Hybrid Layer Architecture (Vertical and Horizontal)**:
    *   **Vertical Layers (Multi-Brand)**: An application-level inheritance system (`1base` -> `2vwgroup` -> `3audi`) that allows for the hierarchical overriding of any component (code, views, translations, and assets). This inheritance applies to the entire stack: from PHP code (Controllers, Services, Models...) to frontend resources (Views, CSS, JS, and images).
    *   **Horizontal Layers (Multi-Role)**: An action dispatcher that resolves which Controller method to execute based on the user's role (`_Admin`, `_Manager`), integrating permission logic directly into the architecture. It is also possible to enable a Controller to fallback to lower roles or to completely override a Controller for a specific role to use. E.g.: `EmissionsSPAController_Manager.php`.

*   **Dynamic Parent Invocation Engine (`parentResponse`)**: Implements the **Decorator** pattern at the architectural level. Through **metaprogramming** (`debug_backtrace`, `Reflection`), it allows a method in a child layer to invoke its counterpart in the parent layer, **preserving the state (`$this`)** of the current object. This facilitates extending functionalities without duplicating code.

*   **Dependency Injection (DI) Container with Implicit Autoloading and Instance Caching**: The framework operates on the **"Convention over Configuration"** principle. By following a directory structure and a naming convention (`ClassName_LayerSuffix`), the `LayerResolver` acts as an **implicit DI container and autoloader**. It injects dependencies into constructors and, to optimize performance, **caches instances of services and helpers (stateless components)** once they are resolved. This ensures that the same instance (`singleton` at the request level) is used throughout the application's lifecycle, avoiding the overhead of object creation. **Models (stateful components) are deliberately excluded from this caching** to prevent subtle shared-state bugs.

*   **Service Locator Pattern and Global Access (`App::getInstance()`)**: The `App` class is implemented as a **Singleton**, being the only component with global static access via `App::getInstance()`. This makes it a **Service Locator** that serves as the "gateway" to the application's core, allowing controlled access to key components like the DI Container (`LayerResolver`). Its use is intended as a resource for specific cases, always favoring explicit dependency injection as the primary pattern.

*   **Hierarchical Model Factory**: Decouples models from the database connection logic. The `ModelFactory` resolves the correct PDO connection based on the context (e.g., using the user's `id_dealer` for multi-tenant DBs) and injects it into the model. The configuration of which DB each model resides in is declarative in `config.php`.

*   **Hierarchical View Engine with a Pre-compiler**: Provides an **inheritance and composition** system for XSLT templates. The `View` class acts as a pre-compiler that resolves custom markers (`[PARENT_TEMPLATE_PATH]`, `[VIEW_PATH:...]`), applying a **recursive fallback** through the layers and caching the final result.

### 2. Fundamentals: Industry Practices and Standards

The framework is built on proven design patterns, making it easier to adopt.

*   **Front Controller Pattern**: A single entry point (`public/index.php`) manages the application's lifecycle. (Similar to **Symfony**, **Laravel**).

*   **Declarative Router**: A `routes.php` file maps HTTP verbs and URIs to controller actions. It supports route parameters via regular expressions. (Similar to **Laravel**, **Slim**).

*   **Centralized and Declarative Configuration**: A single file (`config.php`) defines the entire "personality" of the application: layers, roles, DB connections, and component types. (Configuration philosophy similar to **Django**.)

*   **Authentication and Context Middleware**: An `AuthService` acts as middleware, validating the request via **session tokens** in the database and establishing the **application context** (vertical layer and horizontal role) for the rest of the cycle. It includes protection against **session hijacking** and **brute force**.

*   **Active Record style ORM**: Models with CRUD functionalities (`find`, `save`, `delete`) and **lazy loading of relationships** via magic properties, simplifying interaction with the database. (Similar to **Laravel's Eloquent**, **Rails' Active Record**).

*   **Object-Based Responses**: Controllers return objects (`ViewResponse`, `JsonResponse`), decoupling the logic from the final output generation. (Similar to **Symfony**).

*   **Functional Collections**: Query results are wrapped in a `Collection` object with a fluent API (`map`, `filter`, `pluck`). (Similar to **Laravel's Collections**).

*   **Hybrid Support for Legacy Code**: The router can coexist with traditional PHP scripts, allowing for a **progressive modernization** strategy.

### 3. Developer Experience (DX) and Testing

The framework is designed to be maintainable and easy to debug.

*   **Test-Oriented Architecture**: Provides a testing environment (`TestApp`) that allows **mocking services and factories** to run integration tests against an in-memory database (**SQLite::memory:**), simulating the entire request lifecycle.

*   **Debug Panel with Impersonation**: A development tool that allows real-time switching of the **vertical layer and horizontal role** from the frontend to debug different user contexts without changing sessions.

*   **Advanced Logging**: The exception handling system captures a **complete and enriched stack trace** in a structured and readable format, making it easier to identify the root cause of errors.

*   **Layer-Based Translation System**: Internationalization (i18n) files are loaded and merged in cascade following the layer hierarchy, allowing for specific overrides for each brand or level.

## ⚙️ The Request Lifecycle

To understand how these pillars are orchestrated, it's helpful to follow the flow of a request through the framework:

1.  **Entry Point (`index.php`)**: The request arrives, and the `App` is initialized, loading the configuration and preparing the `include_path` with the layer directories.
2.  **Routing (`Router`)**: The URI and HTTP verb are analyzed to find a match in `routes.php`, generating an "action plan" (controller, method, parameters).
3.  **Authentication Middleware (`AuthService`)**: Acts as a *guard*. It intercepts the action plan, validates the user's session token against the database, and if successful, reads the `layer_user` and `level_user` fields from the `User` model.
4.  **Context Establishment**: The `AuthService` informs the `App` instance of the user's **vertical layer** and **horizontal role**. This context will govern the rest of the lifecycle.
5.  **Intelligent Dispatch (`App::dispatchAction`)**: The `App` uses the context to resolve the final action, following a priority order:
    1.  Does a replacement Controller for the role exist (e.g., `DashboardController_Manager.php`)?
    2.  If not, does a specific method for the role exist in the Controller of the highest layer (e.g., `showProfile_Admin()`)?
    3.  If not, does a method for a lower role exist (if fallback is active)?
    4.  As a last resort, the base method is executed (e.g., `showProfile()`).
6.  **Dependency Graph Construction (`LayerResolver`)**: Before instantiating the Controller, the `LayerResolver` orchestrates the construction of its **complete dependency graph**. It recursively analyzes the constructor of each requested component (Services, Helpers, etc.) and resolves their own dependencies first. Throughout this process, it uses the user's layer context to ensure that the most specific implementation of each component is injected, building the object tree from the inside out.
7.  **Execution**: The Controller method is executed. It uses the injected services for business logic and returns a response object (`ViewResponse`, `JsonResponse`).
8.  **Response Middleware (`App::applyMiddleware`)**: The `App` intercepts the response object returned by the controller. This allows a final middleware layer to be applied to modify or enrich the response before it is sent.
9.  **Sending the Response (`App::sendResponse`)**: The `App` receives the final response object, renders the template (if it's a `ViewResponse`), sets the HTTP headers, and sends the content to the client.


## 📖 Usage Guide

### 1. Code Organization and Conventions

The framework operates on the **"Convention over Configuration"** principle. For autoloading, layer inheritance, and dependency injection to work predictably, a set of naming and file structure rules must be followed. The `config.php` file is the "blueprint" that defines these rules.

---
### Architecture: The Two Layer Dimensions

The framework is organized along two axes: vertical layers for functionality inheritance and horizontal layers for role-based access control.

#### 1. Vertical Layers (Multi-Brand): Hierarchical Inheritance

They define the base functionality and how it is extended or overridden in higher levels. This is ideal for multi-tenant platforms or product families. This hierarchy is defined in the `layers` array of `config.php`.

```php
// config.php
'layers' => [
    'audi' => [
        'directory' => '3audi',      // Folder name.
        'suffix'    => '3Audi',      // Suffix for classes (e.g., _3Audi).
        'layer'     =>  3,           // Priority level (higher = more specific).
    ],
    // ...
    'base' => [
        'directory' => '1base',
        'suffix'    => 'Base',
        'layer'     =>  1,
    ]
],
```

#### 2. Horizontal Layers (Multi-Role): Execution Flow Control

They define what code is executed based on the user's role. **This system applies exclusively to the Controller layer**, as its purpose is to direct the request flow, not to alter business logic, which remains encapsulated in services. The role mapping is defined in the `user_roles` array of `config.php`.

```php
<?php
// config.php
'user_roles' => [
    3 => 'Admin',
    2 => 'Manager',
    1 => 'Seller',
],
```

There are two strategies for implementing role-based logic:

**Option A: Method Suffix (For Extensions and Modifications)**

This is the most common way. A `_[RoleName]` suffix is added to a method to create a specific version for that role. It is ideal for adding, modifying, or removing portions of logic from an existing action.

*   `showProfile()`: Logic for all users.
*   `showProfile_Manager()`: Specific logic that extends or modifies `showProfile()` for the `Manager` role.

**Option B: Controller Class Suffix (For Complete Replacements)**

This is used when the logic for a role is so radically different that extending the base controller makes no sense. In this case, a completely new controller file is created with the role suffix.

*   `DashboardController.php`: Defines the dashboard for standard users.
*   `DashboardController_Manager.php`: Defines a **completely different** dashboard only for the `Manager` role.

When a request for the `DashboardController@show` action arrives, if the user is a `Manager`, the framework will not even load the normal `DashboardController`; it will directly load and execute `DashboardController_Manager`, completely replacing the flow for that route and role.

---
#### Naming Rules

For the described architecture to work, follow these conventions:

1.  **Base Component Name**: Must include the component type as a suffix.
    *   **Correct**: `ProfileController`, `AuthService`, `MenuHelper`.
    *   **Incorrect**: `Profile`, `Auth`, `Menu`.

    *   **Exception for Models**: Models are the only special case. They **do not have a suffix** in their base name.
    *   Base Name: `User` (not `UserModel`).
    *   File: `1base/models/User.php`.
    *   Class: `class User_Base extends ORM`.

2.  **File Name**: `[BaseComponentName].php`. For role-specific controllers, the role suffix is added: `[BaseName]_[Role].php`.
    *   Examples: `ProfileController.php`, `AuthService.php`, `DashboardController_Manager.php`.

3.  **File Location**: `[LayerDirectory]/[ComponentFolder]/[FileName].php`.
    *   Examples: `1base/controllers/ProfileController.php`, `3audi/controllers/DashboardController_Manager.php`.

4.  **Class Name**: `[BaseComponentName]_[LayerSuffix]`.
    *   Examples: `ProfileController_Base`, `AuthService_2GrupoVW`.

5.  **Method Name (for roles)**: `[ActionName]_[RoleName]`.
    *   Example: The base method is `showProfile()`. The version for the `Manager` role will be named `showProfile_Manager()`.

6.  **Class Inheritance**: A class from a higher layer **must extend** its counterpart from the immediately lower layer (except for total role-based controller replacements).
    *   Example: `class ProfileController_2GrupoVW extends ProfileController_Base`.

7.  **Base Class Inheritance (Important!)**: The **first class in the inheritance chain** of any component must extend its corresponding abstract base class. This is crucial for inheriting helper methods and framework functionalities.

    For example, if a `NewController` is created for the first time in the `2vwgroup` layer, its class will be declared as follows:

    `class NewController_2GrupoVW extends Controller`

    Then, if it is extended in a higher layer, it will follow normal inheritance:

    `class NewController_3Audi extends NewController_2GrupoVW`

    The mandatory base inheritances are:
    *   `MyController_Suffix` **extends** `Controller`
    *   `MyService_Suffix` **extends** `Service`
    *   `MyHelper_Suffix` **extends** `Helper`
    *   `MyModel_Suffix` **extends** `ORM`

    Not following this rule will leave you without access to helpers like `getView()`, `translate()`, `parentResponse()`, etc. You can explore the code of these base classes in `lib/components/` to see all the available tools. In turn, all these classes inherit from `Component`, the root class that provides common functionalities to all framework components.

### 2. Routing

The `routes.php` file maps URLs to controller actions. The structure is an array organized by HTTP verb (`GET`, `POST`, etc.).

#### Basic Syntax

```php
// routes.php
'HTTP_VERB' => [
    'url/pattern' => [
        'controller' => 'ControllerName',
        'action'     => 'methodName'
    ],
],
```

#### Examples

**Static Route:**

A `GET` request to `/login` executes the `showLogin` method in `AuthController`.

```php
'GET' => [
    '/login' => [
        'controller' => 'AuthController',
        'action'     => 'showLogin'
    ],
],
```

**Route with Parameters:**

Regular expressions `(...)` are used to capture segments of the URL.

```php
<?php
'GET' => [
    '/api/configurator/session/(\d+)/colors' => [
        'controller' => 'ConfiguratorController',
        'action'     => 'getColorsForSessionAPI',
    ],
],
```

In this case, a visit to `/api/configurator/session/123/colors` will execute `getColorsForSessionAPI($sessionId)` in `ConfiguratorController`, where `$sessionId` will have the value `'123'`. Captured parameters are passed as arguments to the method in the order they appear.


### 3. Creating a Multi-Layer Controller (Practical Guide)

The Controller is the entry point for any user action. In this framework, controllers not only manage requests but are also designed to handle the layer architecture.

Let's create a "My Profile" section to illustrate the key concepts quickly and in various ways, demonstrating the power and flexibility of the Controller layer.

---
#### 0. How to create Services, Models, Helpers... to be injected correctly

```php

// IMPORTANT: There are no "use" or "require" statements; classes are loaded automatically by following the convention.

/**
 * Interface for ProfileService.
 * It's essential to implement this interface in the parent component of Services, Helpers, and Models...
 * The recursive engine will use this reference to resolve dependency injection with the appropriate layer class based on user permissions: ProfileController_Base, ProfileController_2GrupoVW... etc
 */
interface SomeService{}

// File at: 1base/services/SomeService.php
class someService_Base extends Service implements SomeService{
    ...
}

// File at: 2vwgroup/services/SomeService.php
class someService_2Grupo_VW extends someService_Base{
    ...
}
```

#### 1. The Base Controller (`1base` Layer)

Everything starts in the `1base` layer. Here, we define the core functionality that will be available to all higher layers.

**File**: `1base/controllers/ProfileController.php`

```php

// An interface is NOT necessary for a Controller. A Controller will never be injected.
// interface ProfileController {}

class ProfileController_Base extends Controller //implements ProfileController
// Note: The controller of the first layer must always extend "Controller" so that it and its children from higher layers can access methods that provide a good developer experience.
{
    protected TranslatorService $translator;
    protected SomeService $someService;

    /**
     * The constructor declares the dependencies.
     * You simply "ask" for what you need, and the framework injects it.
     *
     * NOTE: You must always define the name without a layer suffix; the "LayerResolver" will provide each user with the highest layer they have access to where the service exists.
     */
    public function __construct(TranslatorService $translator, SomeService $someService)
    {
        $this->translator = $translator;
        $this->someService = $someService;
    }

    /**
     * Shows the base profile page.
     */
    public function showProfile()
    {
        // ✨ Helper from the parent "Controller": getContext() allows access to data from the "App" context
        $user = $this->getContext('user');

        // ✨ Helper from the parent "Controller": getView() gets an instance of "View" with the "profile" view template and injects data into the template in the second argument.
        $view = $this->getView('profile', [
            // ✨ Helper from the parent "Controller": translate() -> accesses the translation service and translates (only if injected)
            'profile_title' => $this->translate('profile_page_title'),
            'user_name'     => $user->name,
            'user_email'    => $user->username . '@example.com',
        ]);

        // ✨ Helper from "View": add(key, value) injects data into the template.
        // In this case, we add a specific JS script to the view.
        $view->add('scripts', '/1base/js/profile_page.js');

        // ✨ Helper from the parent "Controller": view(),
        // Wraps the View instance in a "ResponseView".
        // Important: returning the view directly won't work; we must call this function to wrap it correctly so "App" can process it.
        return $this->view($view);
    }

    /**
     * Shows the profile page for the "Manager" role.
     */
    public function showProfile_Manager()
    {

        // We get the "ResponseView" from the general method (without role) and unpack it.
        // This helps us modify and extend without redoing the view from scratch for specific roles.
        $view = $this->showProfile()->getContent();
        // Important: getContent() is a method of "ViewResponse" that returns the view.

        // We add information exclusive to the Manager.
        $view->set('manager_badge', $this->translate('profile_manager_badge'));
        $view->set('is_manager', true);

        // ✨ Helper from "View": Adds a JSON data block for the frontend JavaScript to use.
        // This will be rendered as <script type="application/json">...</script>
        $view->addJson('manager-data', [
            'canEdit' => true,
            'apiEndpoint' => '/api/profile/save'
        ]);

        return $this->view($view); // We return the view, wrapping it with "view()"
    }
}
```

#### 2. Extending the Controller (`2vwgroup` Layer)

Now, the `2vwgroup` layer needs to customize the profile page. Instead of copying and pasting, it simply **extends** the base controller.

**File**: `2vwgroup/controllers/ProfileController.php`

```php
class ProfileController_2GrupoVW extends ProfileController_Base
{

    /**
     * We override the base method to add a VW-specific detail.
     */
    public function showProfile()
    {
        // We reuse the logic from the parent (`ProfileController_Base::showProfile`).
        // `parentResponse()` executes the corresponding method from the lower layer and returns its "View" or "Json" object, unpacked and ready to be modified.
        $view = $this->parentResponse();

        // We add our personal touch.
        $view->set('brand_message', $this->translate('profile_vw_brand_message'));
        $view->set('page_background_image', '/2vwgroup/img/BackgroundVW.jpg');

        return $this->view($view);
    }

    /**
     * A specific method for the "Admin" role, which only exists in this layer.
     */

    // NOTE: Fallback to lower roles (instead of directly to the base) can be enabled
    // by creating a property like this and setting it to "true".
    public $userLevelFallback = false;

    public function showProfile_Admin()
    {

        // Since it's "false" and the "showProfile_Admin" method does not exist in its "ProfileController_Base" class, the framework falls back to `showProfile()`
        $view = $this->parentResponse();
        // NOTE: If it were "true", it would fall back to "showProfile_Manager".

        // We add the Admin's data.
        $view->set('admin_secret_code', 'TOP_SECRET_ADMIN_DATA');
        $view->set('is_admin', true);

        // ✨ Helper from the parent "Controller": We can remove data or scripts added by the parent if needed.
        $view->removeValue('scripts', '/1base/js/profile_page.js'); // Removes a specific script
        $view->remove('scripts'); // Removes all scripts

        $view->add('scripts', '/2vwgroup/js/admin_profile.js'); // And add a new one.

        return $this->view($view);
    }

    /**
     * ✨ SHOWCASE: API endpoint to demonstrate JSON helpers and their enrichment.
     */
    public function getProfileApi()
    {
        $user = $this->getContext('user');

        if (empty($user)) {
            // The `jsonError` helper formats a standard error response.
            return $this->jsonError($this->translate('api_error_unauthorized'), 401);
        }

        $profileData = [
            'name' => $user->name,
            'dealer' => $user->id_dealer,
        ];

        // 1. We create a base JSON response.
        $baseResponse = $this->json($profileData);

        // 2. We enrich it with additional metadata.
        $finalResponse = $this->enrichJsonResponse($baseResponse, [
            'metadata' => [
                'timestamp' => time(),
                'source' => 'VWGroup API'
            ]
        ]);

        // 3. We return the final response.
        return $finalResponse;
    }
}
```

#### Summary of Key Concepts

*   **Interface as a Contract**: Define `interface MyComponent {}` so the `LayerResolver` can find and inject your classes.
*   **Constructor Injection**: Simply declare your dependencies with their type (`TranslatorService`) in the `__construct`, and the framework will do the rest.
*   **`parentResponse()`**: Executes the logic of the parent from lower layers and gives you its result (`View` or `Json`) so you can extend it.
*   **Role Suffixes (`_Manager`, `_Admin`)**: The way to create logic specific to a role. If a method with a suffix doesn't exist in the hierarchy, the framework looks for the base method without a suffix as a **fallback**.
*   **Helper Methods (`getView`, `view`, `json`, `translate`)**: The base `Controller` class gives you a set of shortcuts to make your code cleaner, more readable, and consistent.
*   **Asset Management (`$view->add`, `$view->removeValue`)**: Controllers can dynamically add or remove JS and CSS files from views, allowing for granular control.
*   **JS Data Bridge (`$view->addJson`)**: Allows you to pass data securely and structured from PHP to your frontend JavaScript.
*   **Robust API Responses (`json`, `jsonError`, `enrichJsonResponse`)**: A set of tools to build and standardize the responses of your API endpoints.

### 4. Internationalization (Translations)

The framework includes an internationalization system that, like other components, operates based on the layer hierarchy.

#### 1. Create Translation Files

Translation files are simple PHP arrays that return `key => value` pairs. They should be located in the `services/translations/` folder of the corresponding layer. The filename must be the language code (e.g., `es.php`, `en.php`).

**Example file**: `1base/services/translations/en.php`

```php
// 1base/services/translations/en.php
return [
    'profile_page_title' => 'My Profile',
    'welcome_message'    => 'Welcome, %s',
];
```

#### 2. Overriding Translations (Layer Inheritance)

To override a translation in a higher layer, simply create a file with the same name and redefine the key. The `TranslatorService` will merge the files in cascade, giving priority to the higher layers.

**Example file**: `2vwgroup/services/translations/en.php`

```php
// 2vwgroup/services/translations/en.php
return [
    // We override this key from the `1base` layer
    'profile_page_title' => 'User Profile - VW Group',

    // And we add a new one, specific to this layer
    'profile_vw_brand_message' => 'You are on the Volkswagen Group portal.',
];
```

#### 3. Using the Translator in a Controller

To use translations, inject the `TranslatorService` into your controller's constructor and use the `translate()` helper.

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

        // For keys with placeholders (%s, %d...), pass an array as the second argument.
        $welcome = $this->translate('welcome_message', [$user->name]);
        // ...
    }
}
```

The `TranslatorService` will automatically detect the user's language (via URL, cookie, or browser headers) and load the correct set of translations.


### 5. The `User` Model: The Origin of Context and Security

All the magic of vertical and horizontal layers begins with the authenticated user. The `AuthService` uses the `User` model to determine the application's context, based on two key fields in the database table.

#### Required Fields in the Users Table

For the layer system to work, your users table (or the entity you use for authentication) must contain two columns:

1.  **`layer_user`** (Integer): This field stores the maximum **vertical layer level** the user has access to. The value must correspond to the `layer` key defined in the `layers` array of `config.php`.
    *   Example: A `layer_user` with a value of `2` will give the user access to components from `1base` and `2vwgroup`, but not from `3audi`.

2.  **`level_user`** (Integer): This field stores the user's **horizontal role level**. The value must correspond to one of the keys in the `user_roles` array of `config.php`.
    *   Example: A `level_user` with a value of `3` will cause the dispatcher to look for methods with the `_Admin` suffix in controllers.

#### The Authentication Flow

1.  A user logs in.
2.  The `AuthService` verifies the credentials and loads the user's record from the database via the `User` model.
3.  The `AuthService` reads the `layer_user` and `level_user` values from the model instance.
4.  Finally, it sets these two values in the global context of the `App`.

From that moment on, every time the `LayerResolver` needs to find a component or the Dispatcher needs to execute an action, they will consult these context values to make the right decision, thus dynamically applying the layer architecture to the user's request.
