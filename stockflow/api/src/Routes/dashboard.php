<?php

/**
 * Dashboard Routes
 *
 * EXERCISE 7: Aggregate data into dashboard summaries
 *
 * This is the capstone exercise — it combines everything:
 * pre-processing, date handling, and data aggregation.
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// GET /api/dashboard/summary — Dashboard overview (authenticated)
// ============================================================
// EXERCISE 7: Students build this route
//
// Fetch data from multiple tables and calculate summary statistics.
// This is ALL post-processing — the database gives you raw data,
// you crunch it in PHP before sending to the frontend.
//
// The frontend expects:
//   {
//     inventory: {
//       total_products: 18,
//       total_value: 12450.00,       // sum of (price * stock_quantity)
//       low_stock_count: 4,          // products where stock <= threshold
//       out_of_stock_count: 2        // products where stock = 0
//     },
//     orders: {
//       total_orders: 5,
//       by_status: {
//         draft: 1,
//         confirmed: 1,
//         fulfilled: 2,
//         cancelled: 1
//       },
//       total_revenue: 2602.00       // sum of fulfilled order totals
//     },
//     low_stock_products: [          // top 5 most urgent
//       { name: "...", stock_quantity: 2, reorder_threshold: 10 },
//       ...
//     ]
//   }
//
// Hints:
//   - Fetch all products: $auth->query('products', ['select' => '*'])
//   - Fetch all orders: $auth->query('orders', ['select' => '*'])
//   - Use PHP array functions to calculate:
//     array_filter() — filter arrays by condition
//     array_sum()    — sum values
//     array_map()    — transform arrays
//     count()        — count items
//     usort()        — sort arrays with custom comparison
//   - For total_value: loop products, sum up (price * stock_quantity)
//   - For low_stock: filter where stock_quantity <= reorder_threshold AND stock > 0
//   - For revenue: filter orders where status === 'fulfilled', then sum total_amount
// ============================================================

// STUB: Returns placeholder data until students implement Exercise 7.
// Replace the body of this route with your own logic.
$app->get('/api/dashboard/summary', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    try {
        $products = $auth->query('products', [
            'select' => 'name,price,stock_quantity,reorder_threshold'
        ]);

        $orders = $auth->query('orders', [
            'select' => 'status,total_amount'
        ]);

        $totalProducts = count($products);
        $totalValue = array_sum(array_map(
            fn($product) => (float)($product['price'] ?? 0) * (int)($product['stock_quantity'] ?? 0),
            $products
        ));

        $outOfStockCount = count(array_filter(
            $products,
            fn($product) => (int)($product['stock_quantity'] ?? 0) === 0
        ));

        $lowStockProducts = array_values(array_filter(
            $products,
            fn($product) =>
                (int)($product['stock_quantity'] ?? 0) > 0 &&
                (int)($product['stock_quantity'] ?? 0) <= (int)($product['reorder_threshold'] ?? 0)
        ));

        $lowStockCount = count($lowStockProducts);

        usort($lowStockProducts, fn($a, $b) => (int)$a['stock_quantity'] <=> (int)$b['stock_quantity']);

        $lowStockProducts = array_map(
            fn($product) => [
                'name' => (string)($product['name'] ?? ''),
                'stock_quantity' => (int)($product['stock_quantity'] ?? 0),
                'reorder_threshold' => (int)($product['reorder_threshold'] ?? 0),
            ],
            array_slice($lowStockProducts, 0, 5)
        );

        $orderStatuses = [
            'draft' => 0,
            'confirmed' => 0,
            'fulfilled' => 0,
            'cancelled' => 0,
        ];

        foreach ($orders as $order) {
            $status = (string)($order['status'] ?? '');
            if (array_key_exists($status, $orderStatuses)) {
                $orderStatuses[$status]++;
            }
        }

        $totalRevenue = array_sum(array_map(
            fn($order) => (string)($order['status'] ?? '') === 'fulfilled' ? (float)($order['total_amount'] ?? 0) : 0,
            $orders
        ));

        $summary = [
            'inventory' => [
                'total_products' => $totalProducts,
                'total_value' => number_format($totalValue, 2, '.', ''),
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
            ],
            'orders' => [
                'total_orders' => count($orders),
                'by_status' => $orderStatuses,
                'total_revenue' => number_format($totalRevenue, 2, '.', ''),
            ],
            'low_stock_products' => $lowStockProducts,
        ];

        $response->getBody()->write(json_encode($summary));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add(new AuthMiddleware());
