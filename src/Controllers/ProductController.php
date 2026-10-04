<?php

namespace App\Controllers;

use App\Models\Product;
use App\Core\Request;
use App\Core\Response;

class ProductController
{
    // Return pagination, filtered, and searched toys

    public function index(): void
    {


        // Read all incoming URL query parameters (?category=...&age=)
        $filters = Request::getQueryParams();

        // Query the database through the model
        $result = Product::paginate($filters);

        Response::success($result, 'Products retrieved successfullu');
    }

    // GET /api/v1/products/{slug}
    // Return a single toy with full gallery images

    public function show(string $slug): void
    {
        $product = Product::findBySlug($slug);

        if (!$product) {
            Response::error("Product with slug '{$slug}' not found", 404);
        }

        Response::success($product, "Product details retrieved successfully");
    }
}
