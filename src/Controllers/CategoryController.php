<?php

namespace App\Controllers;

use App\Models\Category;
use App\Core\Response;

class CategoryController
{

    // GET /api/v1/categories
    // return all active categories

    public function index(): void
    {
        $categories = Category::all();
        Response::success($categories, 'Categories retrieved successfully');
    }


    // GET /api/v1/categories/(slug)
    // return details for a single category

    public function show(string $slug): void
    {
        $category = Category::findBySlug($slug);

        if (!$category) {
            Response::error("Category with slug '{$slug}' not found", 404);
        }

        Response::success($category, 'Category details retrieved successfully');
    }
}
