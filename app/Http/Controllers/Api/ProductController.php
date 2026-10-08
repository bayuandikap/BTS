<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    #[OA\Get(
        path: '/api/products',
        summary: 'List all products',
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Search by title or description', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', description: 'Filter by category', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', description: 'Items per page', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', description: 'Page number', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of products'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->whereRaw('LOWER(category) = ?', [strtolower($category)]);
        }

        $limit = $request->query('limit');
        $page  = $request->query('page');

        if ($limit) {
            $paginated = $query->paginate((int) $limit, ['*'], 'page', (int) ($page ?? 1));

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

    #[OA\Get(
        path: '/api/products/{id}',
        summary: 'Get a single product',
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product detail'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        return response()->json($product);
    }

    #[OA\Post(
        path: '/api/products',
        summary: 'Create a new product',
        tags: ['Products'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'price', 'category', 'images'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Awesome T-Shirt'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 99.99),
                    new OA\Property(property: 'description', type: 'string', example: 'High-quality cotton t-shirt'),
                    new OA\Property(property: 'category', type: 'string', example: 'Clothes'),
                    new OA\Property(
                        property: 'images',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['https://placeimg.com/640/480/any']
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Product created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'category'    => 'required|string|max:255',
            'images'      => 'required|array|min:1',
            'images.*'    => 'string',
        ]);

        $product = Product::create(array_merge($validated, [
            'created_by'    => $request->user()->username,
            'created_by_id' => $request->user()->id,
        ]));

        return response()->json($product, 201);
    }

    #[OA\Put(
        path: '/api/products/{id}',
        summary: 'Update a product',
        tags: ['Products'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Updated T-Shirt'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 79.99),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'category', type: 'string'),
                    new OA\Property(
                        property: 'images',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Product updated'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Product not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'price'       => 'sometimes|numeric|min:0',
            'description' => 'sometimes|nullable|string',
            'category'    => 'sometimes|string|max:255',
            'images'      => 'sometimes|array|min:1',
            'images.*'    => 'string',
        ]);

        $product->update(array_merge($validated, [
            'updated_by'    => $request->user()->username,
            'updated_by_id' => $request->user()->id,
        ]));

        return response()->json($product->fresh());
    }

    #[OA\Delete(
        path: '/api/products/{id}',
        summary: 'Delete a product',
        tags: ['Products'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully.']);
    }
}
