<?php

// 1. Load our custom Autoloader
require_once dirname(__DIR__) . '/src/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Controllers\AdminAuthController;
use App\Controllers\AdminController;
use  App\Core\Router;
use App\Core\Response;
use App\Controllers\CategoryController;
use App\Controllers\ProductController;
use App\Controllers\AuthController;
use App\Middlewares\SecurityMiddleware;
use App\Middlewares\RateLimitMiddleware;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\StaffMiddleware;
use App\Middlewares\AdminMiddleware;

// 2. Initialize the Router
$router = new Router();
(new SecurityMiddleware())->handle();
(new RateLimitMiddleware())->handle();

// Routes
// Homepage Route: http://localhost:8080/
$router->get('/', [PublicTestController::class, 'home']);

// Health check route: http://localhost:8080/api/v1/health
$router->get('/api/v1/health', [PublicTestController::class, 'health']);

// Public Routes
// Category Endpoints
$router->get('api/v1/categories', [CategoryController::class, 'index']);
$router->get('api/v1/categories/{slug}', [CategoryController::class, 'show']);
// subcategory endpoints
$router->get('/api/v1/categories/{slug}/subcategories', [CategoryController::class, 'subcategories']);
$router->get('api/v1/subcategories', [CategoryController::class, 'allSubcategories']);
$router->get('api/v1/subcategories/{slug}', [CategoryController::class, 'singleSubcategory']);


// Product Endpoints (catelog & single view)
$router->get('api/v1/products', [ProductController::class, 'index']);
$router->get('api/v1/products/{slug}', [ProductController::class, 'show']);

// Authentication Endpoints
$router->post('api/v1/auth/register', [AuthController::class, 'register']);
$router->post('api/v1/auth/login', [AuthController::class, 'login']);

// Protected Profile End points (Guarded bt auth middlewares)
$router->get('api/v1/auth/profile', [AuthController::class, 'profile'], [AuthMiddleware::class]);

// BACKOFFICE AUTHENTICATION
// staff login (both admin & managers)
$router->post('api/v1/admin/auth/login', [AdminAuthController::class, 'login']);

// OPERATIONAL STAFF ROUTES (mannagers and  super admins)
// Execute dashboard metrics
$router->get('api/v1/admin/dashboard', [AdminController::class, 'dashboard'], [AuthMiddleware::class, StaffMiddleware::class]);

// Inventory & Catalog Management
$router->get('/api/v1/admin/products', [AdminController::class, 'index'], [AuthMiddleware::class, StaffMiddleware::class]);
$router->post('/api/v1/admin/products', [AdminController::class, 'store'], [AuthMiddleware::class, StaffMiddleware::class]);
$router->put('/api/v1/admin/products/{id}', [AdminController::class, 'update'], [AuthMiddleware::class, StaffMiddleware::class]);
$router->get('/api/v1/admin/products/{id}', [AdminController::class, 'show'], [AuthMiddleware::class, StaffMiddleware::class]);
$router->patch('/api/v1/admin/products/{id}/status', [AdminController::class, 'toggleStatus'], [AuthMiddleware::class, StaffMiddleware::class]);
$router->patch('/api/v1/admin/products/{id}/featured', [AdminController::class, 'toggleFeatured'], [AuthMiddleware::class, StaffMiddleware::class]);
// Staff subcategory management--
$router->get('api/v1/admin/subcategories', [AdminController::class, 'listSubcategories'], [AuthMiddleware::class, StaffMiddleware::class]);
$router->post('/api/v1/admin/subcategories', [AuthController::class, 'createSubcategory'], [AuthMiddleware::class, StaffMiddleware::class]);


// Executive SUPER ADMIN ONLY ROUTES
// safe product deletion (hard delete junk items/soft delete items with orders)
$router->delete('/api/v1/admin/products/{id}', [AdminController::class, 'destroy'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->delete('api/v1/admin/products/{id}', [AuthController::class, 'deleteSubcategory'], [AuthMiddleware::class, StaffMiddleware::class]);

// Team & Staff Management
$router->get('/api/v1/admin/managers', [AdminController::class, 'listManagers'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/api/v1/admin/managers', [AdminController::class, 'createManager'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->delete('/api/v1/admin/managers/{id}', [AdminController::class, 'deleteManager'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/api/v1/admin/users', [AdminController::class, 'listUsers'], [AuthMiddleware::class, StaffMiddleware::class]);
$router->get('/api/v1/admin/users/{id}', [AdminController::class, 'showUser'], [AuthMiddleware::class, StaffMiddleware::class]);

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
                    'GET /api/v1/categories/{slug}/subcategories' => 'List subcategories under a category',
                    'GET /api/v1/subcategories' => 'List all active subcategories',
                    'GET /api/v1/subcategories /{slug}' => 'Get any single subcategory details',
                    'GET /api/v1/products' => 'paginated & filtered toys catalog',
                    'GET /api/v1/products/{slug}' => 'Single toy with gallery images'
                ],
                'Authentication' => [
                    'POST /api/v1/auth/register' => 'Register new customer.',
                    'POST /api/v1/auth/login' => 'Authenticate credentials and user logged-in',
                    'GET /api/v1/auth/profile' => 'View authenticated profile'
                ],
                'staff-portal' => 'POST /api/v1/admin/auth/login',
                'admin-dashboard' => 'GET /api/v1/admin/dashboard'
            ]
        ], 'Toy store API is live and running...');
    }

    // Health check endpoint when visiting http://localhost:8080/api/v1/health

    public function health(): void
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
