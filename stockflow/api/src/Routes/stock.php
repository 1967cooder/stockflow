<?php

/**
 * Stock Movement Routes
 *
 * EXERCISES IN THIS FILE:
 * - Exercise 3: Date/time recording for stock movements
 * (Dashboard analytics are in dashboard.php — Exercise 7)
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// GET /api/stock/movements — List stock movements (authenticated)
// ============================================================
// EXERCISE 3 (Step 2): Students build this route
//
// Stock movements track inventory changes (in, out, adjustment).
// Each movement has a timestamp — this is where date/time matters most.
//
// Hints:
//   - Query the stock_movements table
//   - Join with products: 'select' => '*,products(name,sku)'
//   - Sort by newest first: 'order' => 'created_at.desc'
//   - Post-process: format dates, add relative time
//   - Optional filter: ?product_id=uuid to see movements for one product
// ============================================================

// STUB: Returns empty array until students implement Exercise 3 (Step 2).
// Replace the body of this route with your own logic.
$app->get('/api/stock/movements', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $params = $request->getQueryParams();
    $productId = trim((string)($params['product_id'] ?? ''));

    $query = [
        'select' => '*,products(name,sku)',
        'order' => 'created_at.desc',
    ];

    if ($productId !== '') {
        $query['product_id'] = 'eq.' . $productId;
    }

    $movements = $auth->query('stock_movements', $query);

    $movements = array_map(function ($movement) {
        $timestamp = isset($movement['created_at']) ? strtotime((string)$movement['created_at']) : false;
        $createdDate = $movement['created_at'] ?? null;
        $createdAgo = null;

        if ($timestamp !== false) {
            $createdDate = date('j M Y, H:i', $timestamp);
            $daysAgo = (int)floor((time() - $timestamp) / 86400);

            if ($daysAgo <= 0) {
                $createdAgo = 'Today';
            } elseif ($daysAgo === 1) {
                $createdAgo = 'Yesterday';
            } else {
                $createdAgo = $daysAgo . ' days ago';
            }
        }

        $productName = null;
        if (isset($movement['products']['name'])) {
            $productName = $movement['products']['name'];
        } elseif (isset($movement['products'][0]['name'])) {
            $productName = $movement['products'][0]['name'];
        }

        $movement['product_name'] = $productName;
        $movement['created_date'] = $createdDate;
        $movement['created_ago'] = $createdAgo;

        return $movement;
    }, $movements);

    $response->getBody()->write(json_encode($movements));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// POST /api/stock/movements — Record a stock movement (authenticated)
// ============================================================
// EXERCISE 3 (Step 3): Students build this route
//
// When stock moves in or out, we record it AND update the product's stock_quantity.
// This is a two-step operation:
//   1. Insert the movement record
//   2. Update the product's stock_quantity
//
// The frontend sends:
//   {
//     product_id: "uuid",
//     quantity: 10,
//     movement_type: "in",       // "in", "out", or "adjustment"
//     reason: "Supplier delivery",
//     notes: "Invoice #12345"
//   }
//
// EXERCISE 3 focus: The created_at timestamp is auto-set by the database.
// But if you needed to record a movement for a past date, you could send:
//   'created_at' => date('c', strtotime('2026-03-01'))  // ISO 8601 format
//
// Hints:
//   - Validate: product_id, quantity (> 0), movement_type (in/out/adjustment)
//   - For "out" movements, check that enough stock exists
//   - Calculate new stock: for "in" add, for "out" subtract, for "adjustment" set directly
//   - Update the product's stock_quantity after inserting the movement
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 3 (Step 3).
// Replace the body of this route with your own logic.
$app->post('/api/stock/movements', function (Request $request, Response $response) {
    $body = $request->getParsedBody();
    $body = is_array($body) ? $body : [];

    $productId = trim((string)($body['product_id'] ?? ''));
    $quantity = $body['quantity'] ?? null;
    $movementType = trim((string)($body['movement_type'] ?? ''));

    if ($productId === '' || $quantity === null || !is_numeric($quantity) || (float)$quantity <= 0) {
        $response->getBody()->write(json_encode([
            'error' => 'product_id and quantity (> 0) are required'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if (!in_array($movementType, ['in', 'out', 'adjustment'], true)) {
        $response->getBody()->write(json_encode([
            'error' => 'movement_type must be in, out, or adjustment'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $productRows = $auth->query('products', [
        'id' => 'eq.' . $productId,
        'select' => 'id,name,stock_quantity',
    ]);

    if (empty($productRows)) {
        $response->getBody()->write(json_encode([
            'error' => 'Product not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $product = $productRows[0];
    $currentStock = (int)($product['stock_quantity'] ?? 0);
    $quantity = (int)$quantity;

    if ($movementType === 'in') {
        $newStock = $currentStock + $quantity;
    } elseif ($movementType === 'out') {
        $newStock = $currentStock - $quantity;
    } else {
        $newStock = $quantity;
    }

    $created = $auth->insert('stock_movements', [
        'product_id' => $productId,
        'quantity' => $quantity,
        'movement_type' => $movementType,
        'reason' => trim((string)($body['reason'] ?? '')),
        'notes' => trim((string)($body['notes'] ?? '')),
    ]);

    $auth->update('products', 'id=eq.' . $productId, [
        'stock_quantity' => $newStock,
    ]);

    $movement = $created[0] ?? [];
    $movement['product_name'] = $product['name'] ?? null;
    $timestamp = isset($movement['created_at']) ? strtotime((string)$movement['created_at']) : false;
    if ($timestamp !== false) {
        $movement['created_date'] = date('j M Y, H:i', $timestamp);
        $daysAgo = (int)floor((time() - $timestamp) / 86400);
        if ($daysAgo <= 0) {
            $movement['created_ago'] = 'Today';
        } elseif ($daysAgo === 1) {
            $movement['created_ago'] = 'Yesterday';
        } else {
            $movement['created_ago'] = $daysAgo . ' days ago';
        }
    }

    $response->getBody()->write(json_encode([
        'message' => 'Stock movement recorded successfully',
        'data' => $movement,
        'stock_quantity' => $newStock,
    ]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
