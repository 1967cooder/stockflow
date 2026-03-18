<?php

/**
 * Orders Routes
 *
 * EXERCISES IN THIS FILE:
 * - Exercise 3: Date/time handling (timestamps, relative dates)
 * - Exercise 6: CRUD operations for orders and order items
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

$formatOrder = function (array $order): array {
    $timestamp = isset($order['created_at']) ? strtotime((string)$order['created_at']) : false;
    $createdDate = $order['created_at'] ?? null;
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

    $order['created_date'] = $createdDate;
    $order['created_ago'] = $createdAgo;
    $order['total_amount'] = number_format((float)($order['total_amount'] ?? 0), 2, '.', '');

    return $order;
};

// ============================================================
// GET /api/orders — List orders (authenticated)
// ============================================================
// Currently returns raw order data.
//
// EXERCISE 3: Add date/time post-processing:
//   - Format 'created_at' as a human-readable date (e.g., "9 Mar 2026, 14:30")
//   - Add a 'created_ago' field with relative time (e.g., "2 days ago")
//   - Add an 'age_days' field (number of days since creation)
//   - Format 'total_amount' as currency with 2 decimal places
//
// EXERCISE 5 (Step 1): Add filtering:
//   - Filter by status: ?status=confirmed
//   - Sort by date: ?sort=created_at&order=desc
//
// PHP date/time hints:
//   $timestamp = strtotime($row['created_at']);         // Parse ISO date to Unix timestamp
//   $formatted = date('j M Y, H:i', $timestamp);       // "9 Mar 2026, 14:30"
//   $daysAgo = floor((time() - $timestamp) / 86400);   // 86400 = seconds in a day
//
//   For relative time, you can build a simple helper:
//   if ($daysAgo === 0) return 'Today';
//   if ($daysAgo === 1) return 'Yesterday';
//   return $daysAgo . ' days ago';
// ============================================================

$app->get('/api/orders', function (Request $request, Response $response) use ($formatOrder) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $params = $request->getQueryParams();
    $status = trim((string)($params['status'] ?? ''));

    $query = [
        'order' => 'created_at.desc'
    ];

    if ($status !== '') {
        $query['status'] = 'eq.' . rawurlencode($status);
    }

    $orders = $auth->query('orders', $query);

    $orders = array_map($formatOrder, $orders);

    $response->getBody()->write(json_encode($orders));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware());


// ============================================================
// GET /api/orders/{id} — Get single order with items (authenticated)
// ============================================================
// EXERCISE 5 (Step 2): Students build this route
//
// Hints:
//   - Fetch the order: query('orders', ['id' => 'eq.' . $id])
//   - Fetch its items: query('order_items', ['order_id' => 'eq.' . $id])
//   - Combine them: $order['items'] = $items
//   - Return 404 if order not found
//   - Apply the same date formatting from Exercise 3
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 6 (Step 2).
$app->get('/api/orders/{id}', function (Request $request, Response $response, array $args) use ($formatOrder) {

    $id = $args['id'];
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $orders = $auth->query('orders', [
        'id' => 'eq.' . $id,
    ]);

    if (empty($orders)) {
        $response->getBody()->write(json_encode([
            'error' => 'Order not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $items = $auth->query('order_items', [
        'order_id' => 'eq.' . $id,
        'order' => 'created_at.asc',
    ]);

    $items = array_map(function ($item) {
        $item['quantity'] = (int)($item['quantity'] ?? 0);
        $item['unit_price'] = number_format((float)($item['unit_price'] ?? 0), 2, '.', '');
        $item['line_total'] = number_format((float)($item['line_total'] ?? 0), 2, '.', '');

        return $item;
    }, $items);

    $order = $formatOrder($orders[0]);
    $order['items'] = $items;

    $response->getBody()->write(json_encode($order));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// POST /api/orders — Create an order with items (authenticated)
// ============================================================
// EXERCISE 5 (Step 3): Students build this route
//
// This is the most complex exercise — creating an order involves:
//   1. Validate the order data (customer_name required)
//   2. Insert the order (without items first)
//   3. Loop through items and insert each one
//   4. Calculate the total_amount from the items
//   5. Update the order with the calculated total
//
// The frontend sends:
//   {
//     customer_name: "Company Oy",
//     notes: "Rush order",
//     items: [
//       { product_id: "uuid", product_name: "Widget", quantity: 3, unit_price: 29.99 },
//       { product_id: "uuid", product_name: "Gadget", quantity: 1, unit_price: 49.99 }
//     ]
//   }
//
// EXERCISE 3 (bonus): Record timestamps correctly:
//   - The database auto-sets created_at, but you should understand that
//     Supabase stores timestamps in UTC (TIMESTAMPTZ)
//   - When displaying, the frontend handles timezone conversion
//   - If you need to set a date manually in PHP: date('c') gives ISO 8601 format
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 6 (Step 3).
$app->post('/api/orders', function (Request $request, Response $response) use ($formatOrder) {

    $body = $request->getParsedBody();
    $body = is_array($body) ? $body : [];

    $customerName = trim((string)($body['customer_name'] ?? ''));
    $notes = trim((string)($body['notes'] ?? ''));
    $items = $body['items'] ?? null;

    if ($customerName === '') {
        $response->getBody()->write(json_encode([
            'error' => 'customer_name is required'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if (!is_array($items) || count($items) === 0) {
        $response->getBody()->write(json_encode([
            'error' => 'items must be a non-empty array'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $normalizedItems = [];
    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            $response->getBody()->write(json_encode([
                'error' => 'Item ' . ($index + 1) . ' is invalid'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $productId = trim((string)($item['product_id'] ?? ''));
        $productName = trim((string)($item['product_name'] ?? ''));
        $quantity = $item['quantity'] ?? null;
        $unitPrice = $item['unit_price'] ?? null;

        if ($productName === '') {
            $productName = 'Unknown product';
        }

        if (
            $productId === '' ||
            $quantity === null ||
            !is_numeric($quantity) ||
            (float)$quantity <= 0 ||
            $unitPrice === null ||
            !is_numeric($unitPrice) ||
            (float)$unitPrice < 0
        ) {
            $response->getBody()->write(json_encode([
                'error' => 'Each item must include product_id, quantity (> 0), and unit_price (>= 0)'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $normalizedItems[] = [
            'product_id' => $productId,
            'product_name' => $productName,
            'quantity' => (int)$quantity,
            'unit_price' => round((float)$unitPrice, 2),
        ];
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    try {
        $orderRows = $auth->insert('orders', [
            'customer_name' => $customerName,
            'notes' => $notes,
            'status' => 'draft',
            'total_amount' => 0,
        ]);

        if (empty($orderRows) || !isset($orderRows[0]['id'])) {
            throw new \RuntimeException('Failed to create order');
        }

        $orderId = $orderRows[0]['id'];
        $totalAmount = 0.0;
        $createdItems = [];

        foreach ($normalizedItems as $item) {
            $lineTotal = round($item['quantity'] * $item['unit_price'], 2);
            $totalAmount = round($totalAmount + $lineTotal, 2);

            $itemRows = $auth->insert('order_items', [
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $lineTotal,
            ]);

            if (!empty($itemRows[0])) {
                $createdItem = $itemRows[0];
                $createdItem['quantity'] = (int)($createdItem['quantity'] ?? $item['quantity']);
                $createdItem['unit_price'] = number_format((float)($createdItem['unit_price'] ?? $item['unit_price']), 2, '.', '');
                $createdItem['line_total'] = number_format((float)($createdItem['line_total'] ?? $lineTotal), 2, '.', '');
                $createdItems[] = $createdItem;
            }
        }

        $updatedOrderRows = $auth->update('orders', 'id=eq.' . $orderId, [
            'total_amount' => $totalAmount,
        ]);

        $order = !empty($updatedOrderRows[0]) ? $updatedOrderRows[0] : $orderRows[0];
        $order = $formatOrder($order);
        $order['items'] = $createdItems;

        $response->getBody()->write(json_encode([
            'message' => 'Order created successfully',
            'data' => $order,
        ]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

})->add(new AuthMiddleware());


// ============================================================
// PUT /api/orders/{id}/status — Update order status (authenticated)
// ============================================================
// EXERCISE 5 (Step 4): Students build this route
//
// This teaches state machine logic — not every status transition is valid:
//   draft → confirmed → fulfilled
//   draft → cancelled
//   confirmed → cancelled
//
// Hints:
//   - Fetch the current order to check its current status
//   - Define valid transitions as an array:
//     $validTransitions = [
//         'draft' => ['confirmed', 'cancelled'],
//         'confirmed' => ['fulfilled', 'cancelled'],
//     ];
//   - Return 400 if the transition is not valid
//   - Use $auth->update() to change the status
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 6 (Step 4).
$app->put('/api/orders/{id}/status', function (Request $request, Response $response, array $args) use ($formatOrder) {

    $id = $args['id'];
    $body = $request->getParsedBody();
    $body = is_array($body) ? $body : [];
    $newStatus = trim((string)($body['status'] ?? ''));

    $allowedStatuses = ['draft', 'confirmed', 'fulfilled', 'cancelled'];
    if (!in_array($newStatus, $allowedStatuses, true)) {
        $response->getBody()->write(json_encode([
            'error' => 'status must be one of: draft, confirmed, fulfilled, cancelled'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $orders = $auth->query('orders', [
        'id' => 'eq.' . $id,
    ]);

    if (empty($orders)) {
        $response->getBody()->write(json_encode([
            'error' => 'Order not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $order = $orders[0];
    $currentStatus = (string)($order['status'] ?? '');

    $validTransitions = [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['fulfilled', 'cancelled'],
    ];

    $isValidTransition = isset($validTransitions[$currentStatus]) && in_array($newStatus, $validTransitions[$currentStatus], true);
    if (!$isValidTransition) {
        $response->getBody()->write(json_encode([
            'error' => 'Cannot change from ' . $currentStatus . ' to ' . $newStatus
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $updatedRows = $auth->update('orders', 'id=eq.' . $id, [
        'status' => $newStatus,
    ]);

    $updatedOrder = !empty($updatedRows[0]) ? $updatedRows[0] : array_merge($order, ['status' => $newStatus]);
    $updatedOrder = $formatOrder($updatedOrder);

    $response->getBody()->write(json_encode([
        'message' => 'Order status updated successfully',
        'data' => $updatedOrder,
    ]));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
