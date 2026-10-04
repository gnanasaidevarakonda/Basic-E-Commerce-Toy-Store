<?php

namespace App\Core;

class Router
{
    // Storage array holding all registered routes
    private array $routes = [];

    // 1. Register a GET route.
    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    // 2. Register a POST route
    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    // 3. Register a PUT route
    public function put(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    // 4. Register a DELETE route
    public function delete(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    # Internal helper to store routes in our list.
    private function addRoute(string $method, string $path, array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => '/' . ltrim($path, '/') ?: '/',
            'controller' => $handler[0],
            'action' => $handler[1],
            'middlewares' => $middleware
        ];
    }

    # 5. Dispatcher: Matches the incomming request against our routes list
    public function dispatch(): void
    {
        $requestMethod = Request::getMethod();
        $requestPath = Request::getPath();

        foreach ($this->routes as $route) {

            // check if HTTP method matches (GET===GET, POST===POST)
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            // convert route wildcard like {id} or {slug} into regex
            // eg. "/products/{id}" becomes "#^/products/(?P<$1>[^/]+)$#"

            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = "#^" . $pattern . "$#";


            // Test if current URL matches this route pattern
            if (preg_match($pattern, $requestPath, $matches)) {

                // Extract only named parameters (like "id" or "slug")
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                // Run any assaigned Middleware(eg. check login, check admin role)
                foreach ($route['middlewares'] as $middlewareClass) {
                    $middleware = new $middlewareClass();
                    $middleware->handle();
                }

                // Instantiate the controller and call it's action method
                $controllerClass = $route['controller'];
                $actionMethod = $route['action'];

                if (!class_exists($controllerClass)) {
                    Response::error("Controller '{$controllerClass}' not found", 500);
                }

                $controller = new $controllerClass();
                if (!method_exists($controller, $actionMethod)) {
                    Response::error("Action '{$actionMethod}' not found in controller", 500);
                }

                // Call the controller methos and pass the URL parameters
                call_user_func_array([$controller, $actionMethod], $params);
                return;
            }
        }


        // If no routes matched, return a clean 404 error
        Response::error("Route '{$requestMethod} {$requestPath}' not found", 404);
    }
}
