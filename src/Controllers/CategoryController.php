<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Subcategory;
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

    /**
     * ------------------------
     * SUBCATEGORIES END POINTS
     * --------------------------
     * GET /api/v1/categories/{slug}/subcategories
     * Return subcategories that belong to that parent category
     * ex: /api/v1/catgories/building-blocks/subcategories
     */


    public function subcategories(string $slug): void
    {
        $category = Category::findBySlug($slug);
        if (!$category) {
            Response::error("Category with slug '{$slug}' not found", 404);
        }
        $subcategories = Subcategory::findByCategorySlug($slug);
        Response::success([
            'category_name' => $category['name'],
            'category_slug' => $category['slug'],
            'count'         => count($subcategories),
            'subcategories' => $subcategories
        ], 'Category subcategories retrieved successfully');
    }
    /**
     * GET /api/v1/subcategories
     * Returns all active subcategories across the entire store with parent names.
     */
    public function allSubcategories(): void
    {
        $subcategories = Subcategory::all();
        Response::success([
            'count'         => count($subcategories),
            'subcategories' => $subcategories
        ], 'All subcategories retrieved successfully');
    }
    /**
     * GET /api/v1/subcategories/{slug}
     * Returns details for a single subcategory by its slug.
     */
    public function singleSubcategory(string $slug): void
    {
        $subcategory = Subcategory::findBySlug($slug);
        if (!$subcategory) {
            Response::error("Subcategory with slug '{$slug}' not found", 404);
        }
        Response::success($subcategory, 'Subcategory details retrieved successfully');
    }
}
