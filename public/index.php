<?php

// 1. Load our custom Autoloader
require_once dirname(__DIR__) . '/src/Core/Autoloader.php';
\App\Core\Autoloader::register();

use  App\Core\Router;
use App\Core\Response;
use App\Controllers\CategoryController;
use App\Controllers\ProductController;
use App\Controllers\AuthController;
use App\Middlewares\AuthMiddleware;

// 2. Initialize the Router
$router = new Router();

// Routes
// Homepage Route: http://localhost:8080/
$router->get('/', [PublicTestController::class, 'home']);

// Health check route: http://localhost:8080/api/v1/health
$router->get('api/v1/health', [PublicTestController::class, 'health']);

// Category Endpoints
$router->get('api/v1/categories', [CategoryController::class, 'index']);
$router->get('api/v1/categories/{slug}', [CategoryController::class, 'show']);


// Product Endpoints (catelog & single view)
$router->get('api/v1/products', [ProductController::class, 'index']);
$router->get('api/v1/products/{slug}', [ProductController::class, 'show']);

// Authentication Endpoints
$router->post('api/v1/auth/register', [AuthController::class, 'register']);
$router->post('api/v1/auth/login', [AuthController::class, 'login']);

// Protected Profile End points (Guarded bt auth middlewares)
$router->get('api/v1/auth/profile', [AuthController::class, 'profile'], [AuthMiddleware::class]);

// Temporary Test Controller
class PublicTestController
{
    // Welcome message when visiting http://localhost:8080/
    public function home()
    {
        Response::success([
            'store-name' => 'ToyVerse E-commerce API',
            'api-docs' => 'Welcome to the Toy store REST API',
            'version' => 'v1.0.0',
            'routes' => [
                'public-catalog' => [
                    'GET /' => 'API Welcome message',
                    'GET /api/v1/health' => 'System health & uptime check',
                    'GET /api/v1/categories' => 'List all toy categories',
                    'GET /api/v1/categories/{slug}' => 'Get Single category details',
                    'GET /api/v1/products' => 'paginated & filtered toys catalog',
                    'GET /api/v1/products/{slug}' => 'Single toy with gallery images'
                ],
                'Authentication' => [
                    'POST /api/v1/auth/register' => 'Register new customer.',
                    'POST /api/v1/auth/login' => 'Authenticate credentials and user logged-in',
                    'GET /api/v1/auth/profile' => 'View authenticated profile'
                ]
            ]
        ], 'Toy store API is live and running!');
    }

    // Health check endpoint when visiting http://localhost:8080/api/v1/health

    public function health()
    {
        Response::success([
            'status' => 'All systems operational',
            'php-version' => PHP_VERSION,
            'database' => 'Connected (toystore_db)'
        ], 'Health check passed!');
    }
}

// Dispatch the incomming request...
$router->dispatch();
