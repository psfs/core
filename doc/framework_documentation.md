# Framework Documentation

This document provides a technical overview of the framework's dependency injection system and API access management.

## Dependency Injection

The framework utilizes a singleton pattern combined with an annotation-based dependency injection system. This allows for efficient management of class instances and simplifies the process of providing dependencies to classes.

### Singleton Pattern

The core of the dependency injection system is the `PSFS\base\Singleton` class and the `PSFS\base\types\traits\SingletonTrait`. Any class that extends `Singleton` or uses `SingletonTrait` can be managed as a singleton.

A singleton instance is retrieved using the static `getInstance()` method:

```php
$myServiceInstance = MyService::getInstance();
```

The first time `getInstance()` is called, it creates a new instance of the class and stores it in a static property. Subsequent calls will return the same stored instance.

### Automatic Dependency Injection

Dependencies are automatically injected into singleton classes based on annotations.

-   **`@Injectable` (or `@Inyectable`, `@autoload`, `@autowed`)**: This annotation marks a property as a dependency that needs to be injected. It should be used in the property's docblock.
-   **`@var`**: This annotation specifies the class of the dependency to be injected.

The injection process is handled by the `PSFS\base\types\helpers\InjectorHelper` class. When a singleton is initialized (in the `init()` method), the `InjectorHelper` scans its properties for the `@Injectable` annotation. If found, it reads the `@var` annotation to determine the dependency's class, creates or retrieves an instance of that class, and injects it into the property.

**Example:**

```php
namespace App\Service;

use PSFS\base\Singleton;
use App\AnotherService;

class MyService extends Singleton
{
    /**
     * @Injectable
     * @var AnotherService
     */
    protected $anotherService;

    public function doSomething()
    {
        // The $this->anotherService property is now available
        $this->anotherService->performAction();
    }
}
```

In this example, when `MyService::getInstance()` is called, the framework will automatically inject an instance of `AnotherService` into the `$anotherService` property.

## API Access Management

The framework provides a robust system for managing API access through annotation-based routing and a security layer that handles authentication and authorization.

### Routing

API routes are defined using the `@route` annotation in the docblock of controller methods.

-   **`@route`**: This annotation defines the URL path for the API endpoint. It can include dynamic parameters enclosed in curly braces (e.g., `{id}`).

The `PSFS\base\Router` class is responsible for discovering these routes, caching them, and matching incoming requests to the appropriate controller method. The `PSFS\base\types\helpers\RouterHelper` class assists in parsing the `@route` annotations and extracting route information.

HTTP methods can also be specified using annotations like `@get`, `@post`, etc., or by prefixing the route with the method (e.g., `GET#|#/api/users`).

**Example:**

```php
namespace App\Controller;

use PSFS\base\types\Controller;

class UserController extends Controller
{
    /**
     * @route /api/users/{id}
     * @return \PSFS\base\dto\JsonResponse
     */
    public function getUser($id)
    {
        // Logic to retrieve and return a user by ID
    }
}
```

This defines an endpoint that responds to GET requests at `/api/users/{id}`, where `{id}` is a dynamic parameter.

### Security

The framework includes a security layer to protect API endpoints.

#### Restricted Access

Routes prefixed with `/admin` or `/setup-admin` are automatically protected. The `PSFS\base\types\helpers\SecurityHelper::checkRestrictedAccess()` method is called by the router to verify if the user has the necessary administrator credentials before allowing access to these routes. If the user is not authenticated, an `AccessDeniedException` is thrown.

#### Token-Based Authentication

For more granular control over API access, the framework provides a token-based authentication mechanism.

-   `SecurityHelper::generateToken($secret, $module)`: This method generates a time-sensitive authentication token based on a secret key and a module name.
-   `SecurityHelper::checkToken($token, $secret, $module)`: This method validates a given token against the secret key and module name.

This allows you to secure specific API endpoints by requiring a valid token in the request. You would typically generate a token upon user login and require that token to be sent with subsequent API requests.

**Example of securing an endpoint:**

```php
public function getSensitiveData()
{
    $token = $this->getRequest()->getHeader('X-Auth-Token');
    $secret = Config::getParam('api.secret'); // Get the secret from configuration

    if (!SecurityHelper::checkToken($token, $secret, 'api_module')) {
        // Return a 401 Unauthorized response
        return new JsonResponse(['error' => 'Unauthorized'], 401);
    }

    // Proceed with returning sensitive data
}
```
