<?php

/**
 * Products Routes
 *
 * EXERCISES IN THIS FILE:
 * - Exercise 1: Pre-process product data (stock status, formatted prices)
 * - Exercise 2: Add search and filtering via query parameters
 * - Exercise 4: Full CRUD operations (create, update, delete)
 * - Exercise 5: Image upload to Supabase Storage
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// GET /api/products — List products (public)
// ============================================================
// Currently returns raw data from Supabase.
//
// EXERCISE 1: Add post-processing to transform the data:
//   - Format price as a string with 2 decimal places
//   - Add a 'stock_status' field: 'out_of_stock', 'low_stock', or 'in_stock'
//     (hint: compare stock_quantity to reorder_threshold)
//   - Add 'category_name' as a flat string instead of nested object
//   - Keep 'image_url' — the frontend uses it for thumbnails
//   - Remove fields the frontend doesn't need (supplier, reorder_threshold)
//
// EXERCISE 2: Add pre-processing for search and filtering:
//   - Read query params: ?search=wireless&category=Audio&status=active
//   - Build Supabase filters from those params
//   - Add sorting: ?sort=price&order=desc
//   - Add pagination: ?page=1&limit=10
//
// See _route_examples.php for how to read query params and build filters.
// ============================================================

$app->get('/api/products', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();
    $authHeader = $request->getHeaderLine('Authorization');
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        $auth->setToken(trim($matches[1]));
    }

    $params = $request->getQueryParams();
    $search = trim((string)($params['search'] ?? ''));
    $category = trim((string)($params['category'] ?? ''));
    $status = trim((string)($params['status'] ?? ''));
    $page = (int)($params['page'] ?? 1);
    $limit = (int)($params['limit'] ?? 50);

    if ($page < 1) {
        $page = 1;
    }

    if ($limit < 1) {
        $limit = 1;
    }

    if ($limit > 100) {
        $limit = 100;
    }

    $offset = ($page - 1) * $limit;

    $queryParams = [
        'select' => $category !== '' ? '*,categories!inner(name)' : '*,categories(name)',
        'order' => 'name.asc',
        'offset' => (string)$offset,
        'limit' => (string)$limit,
    ];

    if ($search !== '') {
        $queryParams['name'] = 'ilike.*' . rawurlencode($search) . '*';
    }

    if ($category !== '') {
        $queryParams['categories.name'] = 'eq.' . rawurlencode($category);
    }

    if ($status !== '') {
        $queryParams['status'] = 'eq.' . rawurlencode($status);
    }

    $products = $auth->query('products', $queryParams);

    // --- POST-PROCESSING (Exercise 1) ---
    // TODO: Transform $products before sending to the frontend
    // Example: $processed = array_map(function ($product) { ... }, $products);
    // Then return $processed instead of $products

    $processed = array_map(function ($product) {
        $stockQuantity = (int)($product['stock_quantity'] ?? 0);
        $reorderThreshold = (int)($product['reorder_threshold'] ?? 0);

        if ($stockQuantity === 0) {
            $stockStatus = 'out_of_stock';
        } elseif ($stockQuantity <= $reorderThreshold) {
            $stockStatus = 'low_stock';
        } else {
            $stockStatus = 'in_stock';
        }

        $categoryName = 'Uncategorized';
        if (isset($product['categories']['name'])) {
            $categoryName = $product['categories']['name'];
        } elseif (isset($product['categories'][0]['name'])) {
            $categoryName = $product['categories'][0]['name'];
        }

        return [
            'id' => $product['id'] ?? null,
            'name' => $product['name'] ?? '',
            'sku' => $product['sku'] ?? '',
            'price' => number_format((float)($product['price'] ?? 0), 2, '.', ''),
            'description' => $product['description'] ?? '',
            'stock_quantity' => $stockQuantity,
            'stock_status' => $stockStatus,
            'category_name' => $categoryName,
            'category_id' => $product['category_id'] ?? null,
            'image_url' => $product['image_url'] ?? null,
            'status' => $product['status'] ?? null,
        ];
    }, $products);

    $response->getBody()->write(json_encode($processed));
    return $response->withHeader('Content-Type', 'application/json');
});


// ============================================================
// GET /api/products/{id} — Get single product (public)
// ============================================================
// EXERCISE 4 (Step 1): Students build this route
// This is needed before update/delete — you need to fetch one product.
//
// Hints:
//   - $args['id'] contains the UUID from the URL
//   - Use $auth->query('products', ['id' => 'eq.' . $id, 'select' => '...'])
//   - Supabase returns an array even for single items — use [0] to get the first
//   - Return 404 if the product doesn't exist
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 4 (Step 1).
$app->get('/api/products/{id}', function (Request $request, Response $response, array $args) {

    $id = $args['id'];
    $auth = new SupabaseAuth();

    $product = $auth->query('products', [
        'id' => 'eq.' . $id,
        'select' => '*,categories(name)'
    ]);

    if (empty($product)) {
        $response->getBody()->write(json_encode([
            'error' => 'Product not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode($product[0]));
    return $response->withHeader('Content-Type', 'application/json');

});


// ============================================================
// POST /api/products — Create a product (admin/manager only)
// ============================================================
// EXERCISE 4 (Step 2): Students build this route
//
// Hints:
//   - Use $request->getParsedBody() to get the JSON body
//   - Validate required fields: name, sku, price
//   - Sanitize: trim strings, cast price to float
//   - Include image_url if it was sent (from Exercise 5)
//   - Use $auth->insert('products', $data)
//   - Return 201 status on success
//   - Don't forget ->add(new AuthMiddleware()) at the end!
//
// The frontend sends:
//   { name: "...", sku: "...", price: 29.99, description: "...", category_id: "uuid", image_url: "..." }
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 4 (Step 2).
$app->post('/api/products', function (Request $request, Response $response) {

    $body = $request->getParsedBody();
    $body = is_array($body) ? $body : [];

    $name = trim((string)($body['name'] ?? ''));
    $sku = trim((string)($body['sku'] ?? ''));
    $price = $body['price'] ?? null;

    if ($name === '' || $sku === '' || $price === null || !is_numeric($price)) {
        $response->getBody()->write(json_encode([
            'error' => 'name, sku and numeric price are required'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $data = [
        'name' => $name,
        'sku' => $sku,
        'price' => (float)$price,
        'description' => trim((string)($body['description'] ?? '')),
    ];

    if (array_key_exists('category_id', $body)) {
        $categoryId = trim((string)$body['category_id']);
        $data['category_id'] = $categoryId !== '' ? $categoryId : null;
    }

    if (array_key_exists('image_url', $body)) {
        $imageUrl = trim((string)$body['image_url']);
        $data['image_url'] = $imageUrl !== '' ? $imageUrl : null;
    }

    if (array_key_exists('status', $body)) {
        $status = trim((string)$body['status']);
        if (!in_array($status, ['active', 'archived'], true)) {
            $response->getBody()->write(json_encode([
                'error' => 'status must be active or archived'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        $data['status'] = $status;
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));
    $created = $auth->insert('products', $data);

    $response->getBody()->write(json_encode([
        'message' => 'Product created successfully',
        'data' => $created[0] ?? $created
    ]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// PUT /api/products/{id} — Update a product (admin/manager only)
// ============================================================
// EXERCISE 4 (Step 3): Students build this route
//
// Hints:
//   - Only update fields that were actually sent in the body
//   - Include image_url if a new image was uploaded (Exercise 5)
//   - Use $auth->update('products', 'id=eq.' . $id, $data)
//   - Return 400 if no fields to update
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 4 (Step 3).
$app->put('/api/products/{id}', function (Request $request, Response $response, array $args) {

    $id = $args['id'];
    $body = $request->getParsedBody();
    $body = is_array($body) ? $body : [];

    $data = [];

    if (array_key_exists('name', $body)) {
        $name = trim((string)$body['name']);
        if ($name === '') {
            $response->getBody()->write(json_encode([
                'error' => 'name cannot be empty'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        $data['name'] = $name;
    }

    if (array_key_exists('sku', $body)) {
        $sku = trim((string)$body['sku']);
        if ($sku === '') {
            $response->getBody()->write(json_encode([
                'error' => 'sku cannot be empty'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        $data['sku'] = $sku;
    }

    if (array_key_exists('price', $body)) {
        if ($body['price'] === '' || !is_numeric($body['price'])) {
            $response->getBody()->write(json_encode([
                'error' => 'price must be numeric'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        $data['price'] = (float)$body['price'];
    }

    if (array_key_exists('description', $body)) {
        $data['description'] = trim((string)$body['description']);
    }

    if (array_key_exists('category_id', $body)) {
        $categoryId = trim((string)$body['category_id']);
        $data['category_id'] = $categoryId !== '' ? $categoryId : null;
    }

    if (array_key_exists('image_url', $body)) {
        $imageUrl = trim((string)$body['image_url']);
        $data['image_url'] = $imageUrl !== '' ? $imageUrl : null;
    }

    if (array_key_exists('status', $body)) {
        $status = trim((string)$body['status']);
        if (!in_array($status, ['active', 'archived'], true)) {
            $response->getBody()->write(json_encode([
                'error' => 'status must be active or archived'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        $data['status'] = $status;
    }

    if (empty($data)) {
        $response->getBody()->write(json_encode([
            'error' => 'No fields to update'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));
    $updated = $auth->update('products', 'id=eq.' . $id, $data);

    if (empty($updated)) {
        $response->getBody()->write(json_encode([
            'error' => 'Product not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Product updated successfully',
        'data' => $updated[0]
    ]));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// DELETE /api/products/{id} — Delete a product (admin only)
// ============================================================
// EXERCISE 4 (Step 4): Students build this route
//
// Hints:
//   - Use $auth->delete('products', 'id=eq.' . $id)
//   - Consider: should you hard-delete or soft-delete (set status='archived')?
//   - If soft-delete, use update() instead of delete()
//   - Return a confirmation message
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 4 (Step 4).
$app->delete('/api/products/{id}', function (Request $request, Response $response, array $args) {

    $id = $args['id'];

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));
    $archived = $auth->update('products', 'id=eq.' . $id, [
        'status' => 'archived'
    ]);

    if (empty($archived)) {
        $response->getBody()->write(json_encode([
            'error' => 'Product not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Product archived successfully',
        'data' => $archived[0]
    ]));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// POST /api/products/upload-image — Upload a product image (authenticated)
// ============================================================
// EXERCISE 5: Students build this route
//
// This route receives a file upload, sends it to Supabase Storage,
// and returns the public URL. The frontend then uses this URL when
// creating or updating a product.
//
// How file uploads work in Slim:
//   - The frontend sends a FormData object (not JSON)
//   - Slim parses it automatically via addBodyParsingMiddleware()
//   - Use $request->getUploadedFiles() to get the file
//   - Each file is a PSR-7 UploadedFile object with methods:
//     ->getError()          — check for upload errors (UPLOAD_ERR_OK = success)
//     ->getClientFilename() — original filename (e.g., "photo.jpg")
//     ->getSize()           — file size in bytes
//     ->getClientMediaType()— MIME type (e.g., "image/jpeg")
//     ->getStream()         — the file data as a stream
//
// Steps:
//   1. Get the uploaded file from the request
//   2. Validate: file exists, no upload errors, correct type (image/*), size limit
//   3. Generate a unique filename (to avoid collisions)
//   4. Upload to Supabase Storage using $auth->uploadFile()
//   5. Get the public URL using $auth->getPublicUrl()
//   6. Return the URL as JSON
//
// Hints:
//   - Generate unique filename: $filename = uniqid() . '-' . $file->getClientFilename();
//   - Read file data: $fileData = (string) $file->getStream();
//   - Allowed types: ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
//   - Max size: 5 * 1024 * 1024 (5MB)
//   - Bucket name: 'product-images' (must be created in Supabase first — see TASKS.md)
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 5.
$app->post('/api/products/upload-image', function (Request $request, Response $response) {

    // $files = $request->getUploadedFiles();
    // $file = $files['image'] ?? null;
    //
    // --- PRE-PROCESSING ---
    // TODO: Check that a file was uploaded
    // if (!$file || $file->getError() !== UPLOAD_ERR_OK) { ... return 400 }
    //
    // TODO: Validate file type
    // $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    // if (!in_array($file->getClientMediaType(), $allowedTypes)) { ... return 400 }
    //
    // TODO: Validate file size (max 5MB)
    // if ($file->getSize() > 5 * 1024 * 1024) { ... return 400 }
    //
    // TODO: Generate unique filename
    // $filename = uniqid() . '-' . $file->getClientFilename();
    //
    // --- UPLOAD TO SUPABASE STORAGE ---
    // $auth = new SupabaseAuth();
    // $auth->setToken($request->getAttribute('token'));
    //
    // $fileData = (string) $file->getStream();
    // $auth->uploadFile('product-images', $filename, $fileData, $file->getClientMediaType());
    //
    // $publicUrl = $auth->getPublicUrl('product-images', $filename);
    //
    // --- POST-PROCESSING ---
    // TODO: Return the public URL
    // $response->getBody()->write(json_encode(['image_url' => $publicUrl]));
    // return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

    $response->getBody()->write(json_encode([
        'error' => 'Exercise 5: POST /api/products/upload-image is not implemented yet'
    ]));
    return $response->withStatus(501)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
