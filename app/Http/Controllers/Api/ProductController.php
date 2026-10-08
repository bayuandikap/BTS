<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Sample Product"),
 *     @OA\Property(property="price", type="number", format="float", example=49.99),
 *     @OA\Property(property="description", type="string", nullable=true, example="A sample product description."),
 *     @OA\Property(property="category", type="string", example="electronics"),
 *     @OA\Property(
 *         property="images",
 *         type="array",
 *         @OA\Items(type="string", example="https://example.com/img1.jpg")
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00.000000Z"),
 *     @OA\Property(property="created_by", type="string", example="johndoe"),
 *     @OA\Property(property="created_by_id", type="integer", example=5),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-02T00:00:00.000000Z"),
 *     @OA\Property(property="updated_by", type="string", nullable=true, example="janedoe"),
 *     @OA\Property(property="updated_by_id", type="integer", nullable=true, example=7)
 * )
 */
class ProductController extends Controller
{
    /**
     * List all products with optional search, category filter, and pagination.
     *
     * @OA\Get(
     *     path="/api/products",
     *     summary="List all products",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Filter products by keyword in title or description (case-insensitive)",
     *         required=false,
     *         @OA\Schema(type="string", example="laptop")
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter products by category (case-insensitive)",
     *         required=false,
     *         @OA\Schema(type="string", example="electronics")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of products per page (enables pagination when combined with page)",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of products (array when no pagination, paginated object when limit is provided)",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/Product")
     *                 ),
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="current_page", type="integer", example=1),
     *                         @OA\Property(property="per_page", type="integer", example=10),
     *                         @OA\Property(property="total", type="integer", example=50),
     *                         @OA\Property(property="last_page", type="integer", example=5)
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->category) {
            $query->whereRaw('LOWER(category) = ?', [strtolower($request->category)]);
        }

        if ($request->limit) {
            $paginated = $query->paginate($request->limit, ['*'], 'page', $request->get('page', 1));

            return response()->json([
                'data' => $paginated->items(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ]);
        }

        return response()->json($query->get());
    }

    /**
     * Get a single product by ID.
     *
     * @OA\Get(
     *     path="/api/products/{id}",
     *     summary="Get a single product",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product found",
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product not found.")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        return response()->json($product);
    }

    /**
     * Create a new product (requires authentication).
     *
     * @OA\Post(
     *     path="/api/products",
     *     summary="Create a new product",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","price","category","images"},
     *             @OA\Property(property="title", type="string", maxLength=255, example="Laptop Pro"),
     *             @OA\Property(property="price", type="number", format="float", minimum=0, example=1299.99),
     *             @OA\Property(property="category", type="string", maxLength=255, example="electronics"),
     *             @OA\Property(
     *                 property="images",
     *                 type="array",
     *                 minItems=1,
     *                 @OA\Items(type="string", format="url", example="https://example.com/img1.jpg")
     *             ),
     *             @OA\Property(property="description", type="string", nullable=true, example="A high-performance laptop.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The title field is required."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\AdditionalProperties(
     *                     type="array",
     *                     @OA\Items(type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'category'    => 'required|string|max:255',
            'images'      => 'required|array|min:1',
            'images.*'    => 'url',
            'description' => 'nullable|string',
        ]);

        $product = Product::create(array_merge($validated, [
            'created_by'    => $request->user()->username,
            'created_by_id' => $request->user()->id,
        ]));

        return response()->json($product, 201);
    }

    /**
     * Update an existing product (requires authentication).
     *
     * @OA\Put(
     *     path="/api/products/{id}",
     *     summary="Update an existing product",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=255, example="Laptop Pro Max"),
     *             @OA\Property(property="price", type="number", format="float", minimum=0, example=1499.99),
     *             @OA\Property(property="category", type="string", maxLength=255, example="electronics"),
     *             @OA\Property(
     *                 property="images",
     *                 type="array",
     *                 minItems=1,
     *                 @OA\Items(type="string", format="url", example="https://example.com/img2.jpg")
     *             ),
     *             @OA\Property(property="description", type="string", nullable=true, example="Updated description.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The price must be at least 0."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\AdditionalProperties(
     *                     type="array",
     *                     @OA\Items(type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'price'       => 'sometimes|numeric|min:0',
            'category'    => 'sometimes|string|max:255',
            'images'      => 'sometimes|array|min:1',
            'images.*'    => 'url',
            'description' => 'nullable|string',
        ]);

        $product->update(array_merge($validated, [
            'updated_by'    => $request->user()->username,
            'updated_by_id' => $request->user()->id,
        ]));

        return response()->json($product);
    }

    /**
     * Delete a product (requires authentication).
     *
     * @OA\Delete(
     *     path="/api/products/{id}",
     *     summary="Delete a product",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product not found.")
     *         )
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully.']);
    }
}
