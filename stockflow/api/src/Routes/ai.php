<?php

/**
 * AI Routes — Gemini Integration
 *
 * EXERCISE 8: Use the GeminiAI class to add AI-powered features
 *
 * The GeminiAI class is already built (src/AI/GeminiAI.php).
 * Your job is to build the routes that USE it with real data.
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\AI\GeminiAI;
use StockFlow\Middleware\AuthMiddleware;

$isAiQuotaError = function (string $message): bool {
    $normalized = strtolower($message);
    $needles = [
        'quota exceeded',
        'rate limit',
        'resource_exhausted',
        '429',
        'too many requests',
        'free_tier',
    ];

    foreach ($needles as $needle) {
        if (str_contains($normalized, $needle)) {
            return true;
        }
    }

    return false;
};

$aiFallbackEnabled = function (): bool {
    $value = strtolower(trim((string)($_ENV['AI_FALLBACK_ENABLED'] ?? 'true')));
    $value = trim($value, " \t\n\r\0\x0B.;,!");
    return !in_array($value, ['0', 'false', 'off', 'no'], true);
};

// ============================================================
// POST /api/ai/describe — Generate a product description
// ============================================================
// EXERCISE 6 (Step 1): Students build this route
//
// Given a product name and basic details, ask Gemini to write
// a short marketing description.
//
// The frontend sends:
//   { product_id: "uuid" }
//
// Your route should:
//   1. Fetch the product from Supabase (to get name, category, price)
//   2. Build a prompt like:
//      "Write a short product description (2-3 sentences) for: {name}.
//       Category: {category}. Price: {price} EUR."
//   3. Send the prompt to Gemini using $ai->ask($prompt)
//   4. Return the generated description
//
// Hints:
//   - Create the AI instance: $ai = new GeminiAI();
//   - Call it: $description = $ai->ask($prompt);
//   - Wrap in try/catch — AI calls can fail (rate limits, network issues)
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 8 (Step 1).
// Replace the body of this route with your own logic.
$app->post('/api/ai/describe', function (Request $request, Response $response) use ($isAiQuotaError, $aiFallbackEnabled) {
    $body = $request->getParsedBody();
    $body = is_array($body) ? $body : [];
    $productId = trim((string)($body['product_id'] ?? ''));

    if ($productId === '') {
        $response->getBody()->write(json_encode([
            'error' => 'product_id is required'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $name = '';
    $category = 'Uncategorized';
    $price = '0.00';

    try {
        $products = $auth->query('products', [
            'id' => 'eq.' . $productId,
            'select' => 'name,price,categories(name)'
        ]);

        if (empty($products)) {
            $response->getBody()->write(json_encode([
                'error' => 'Product not found'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $product = $products[0];
        if (isset($product['categories']['name'])) {
            $category = (string)$product['categories']['name'];
        } elseif (isset($product['categories'][0]['name'])) {
            $category = (string)$product['categories'][0]['name'];
        }

        $name = trim((string)($product['name'] ?? 'Unknown product'));
        $price = number_format((float)($product['price'] ?? 0), 2, '.', '');
        $prompt = 'Write a 2-3 sentence product description for: ' . $name . '. Category: ' . $category . '. Price: ' . $price . ' EUR.';

        $ai = new GeminiAI();
        $description = $ai->ask($prompt);

        $response->getBody()->write(json_encode([
            'description' => $description
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
        if ($aiFallbackEnabled() && $isAiQuotaError($e->getMessage()) && $name !== '') {
            $fallbackDescription = $name . ' is a ' . $category . ' product priced at ' . $price . ' EUR. '
                . 'It offers reliable everyday performance and practical value for regular use. '
                . 'This item is a strong choice for customers looking for quality at a balanced price point.';

            $response->getBody()->write(json_encode([
                'description' => $fallbackDescription,
                'fallback' => true,
                'reason' => 'quota_exceeded'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add(new AuthMiddleware());


// ============================================================
// POST /api/ai/stock-advice — Get AI advice on stock levels
// ============================================================
// EXERCISE 6 (Step 2): Students build this route
//
// Fetch all products with low stock and ask Gemini for advice.
//
// Your route should:
//   1. Fetch products where stock_quantity <= reorder_threshold
//      (hint: you may need to fetch all products and filter in PHP,
//       or use Supabase filter syntax)
//   2. Build a prompt with the low-stock products list
//   3. Ask Gemini for reorder recommendations
//   4. Return the AI advice plus the product data
//
// Example prompt:
//   "These products are running low on stock. For each, suggest a
//    reorder quantity based on the current stock and threshold:
//    - Wireless Earbuds Pro: 5 in stock, threshold: 15
//    - USB-C Hub Pro: 2 in stock, threshold: 10
//    Give a brief recommendation for each."
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 8 (Step 2).
$app->post('/api/ai/stock-advice', function (Request $request, Response $response) use ($isAiQuotaError, $aiFallbackEnabled) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $lowStockProducts = [];

    try {
        $products = $auth->query('products', [
            'select' => 'id,name,sku,stock_quantity,reorder_threshold,price'
        ]);

        $lowStockProducts = array_values(array_filter($products, function ($product) {
            $stockQuantity = (int)($product['stock_quantity'] ?? 0);
            $reorderThreshold = (int)($product['reorder_threshold'] ?? 0);
            return $stockQuantity <= $reorderThreshold;
        }));

        $lowStockProducts = array_map(function ($product) {
            return [
                'id' => $product['id'] ?? null,
                'name' => (string)($product['name'] ?? ''),
                'sku' => (string)($product['sku'] ?? ''),
                'price' => number_format((float)($product['price'] ?? 0), 2, '.', ''),
                'stock_quantity' => (int)($product['stock_quantity'] ?? 0),
                'reorder_threshold' => (int)($product['reorder_threshold'] ?? 0),
            ];
        }, $lowStockProducts);

        if (empty($lowStockProducts)) {
            $response->getBody()->write(json_encode([
                'advice' => 'All products are currently above their reorder thresholds.',
                'products' => []
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $itemsText = implode("\n", array_map(function ($product) {
            return '- ' . $product['name'] . ': ' . $product['stock_quantity'] . ' in stock, threshold: ' . $product['reorder_threshold'];
        }, $lowStockProducts));

        $prompt = "These products are running low on stock. For each, suggest a reorder quantity based on the current stock and threshold:\n"
            . $itemsText
            . "\nGive a brief recommendation for each product.";

        $ai = new GeminiAI();
        $advice = $ai->ask($prompt);

        $response->getBody()->write(json_encode([
            'advice' => $advice,
            'products' => $lowStockProducts
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
        if ($aiFallbackEnabled() && $isAiQuotaError($e->getMessage()) && !empty($lowStockProducts)) {
            $lines = array_map(function ($product) {
                $stockQuantity = (int)($product['stock_quantity'] ?? 0);
                $reorderThreshold = (int)($product['reorder_threshold'] ?? 0);
                $deficit = max($reorderThreshold - $stockQuantity, 0);
                $recommended = max($deficit + (int)ceil($reorderThreshold * 0.5), 1);

                return '- ' . $product['name']
                    . ': reorder about ' . $recommended
                    . ' units (stock ' . $stockQuantity
                    . ', threshold ' . $reorderThreshold . ')';
            }, $lowStockProducts);

            $fallbackAdvice = 'Gemini is currently unavailable due to quota limits. Suggested reorder quantities based on current thresholds:'
                . "\n" . implode("\n", $lines);

            $response->getBody()->write(json_encode([
                'advice' => $fallbackAdvice,
                'products' => $lowStockProducts,
                'fallback' => true,
                'reason' => 'quota_exceeded'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add(new AuthMiddleware());


// ============================================================
// POST /api/ai/summarize-orders — Summarize recent orders
// ============================================================
// EXERCISE 6 (Step 3 — Stretch): Students build this route
//
// Fetch recent orders and ask Gemini to summarize trends.
//
// Your route should:
//   1. Fetch orders from the last 7 days
//   2. Build a prompt with order data (customer, total, status)
//   3. Ask Gemini to identify patterns and summarize
//   4. Return the summary
//
// This combines Exercise 3 (date handling) with Exercise 8 (AI).
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 8 (Step 3).
$app->post('/api/ai/summarize-orders', function (Request $request, Response $response) use ($isAiQuotaError, $aiFallbackEnabled) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $orders = [];

    try {
        $sevenDaysAgo = gmdate('Y-m-d\TH:i:s\Z', strtotime('-7 days'));

        $orders = $auth->query('orders', [
            'select' => 'customer_name,status,total_amount,created_at',
            'created_at' => 'gte.' . rawurlencode($sevenDaysAgo),
            'order' => 'created_at.desc'
        ]);

        if (empty($orders)) {
            $response->getBody()->write(json_encode([
                'summary' => 'No orders found in the last 7 days.',
                'orders' => []
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $ordersText = implode("\n", array_map(function ($order) {
            return '- Customer: ' . (string)($order['customer_name'] ?? 'Unknown')
                . ', Total: ' . number_format((float)($order['total_amount'] ?? 0), 2, '.', '')
                . ' EUR, Status: ' . (string)($order['status'] ?? 'unknown');
        }, $orders));

        $prompt = "Summarize the following recent orders from the last 7 days. Identify key trends, notable statuses, and any operational insights:\n"
            . $ordersText;

        $ai = new GeminiAI();
        $summary = $ai->ask($prompt);

        $response->getBody()->write(json_encode([
            'summary' => $summary,
            'orders' => $orders
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
        if ($aiFallbackEnabled() && $isAiQuotaError($e->getMessage()) && !empty($orders)) {
            $statusCounts = [];
            $totalAmount = 0.0;

            foreach ($orders as $order) {
                $status = (string)($order['status'] ?? 'unknown');
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
                $totalAmount += (float)($order['total_amount'] ?? 0);
            }

            $orderCount = count($orders);
            $averageAmount = $orderCount > 0 ? number_format($totalAmount / $orderCount, 2, '.', '') : '0.00';
            arsort($statusCounts);
            $topStatus = array_key_first($statusCounts) ?: 'unknown';
            $topStatusCount = $statusCounts[$topStatus] ?? 0;

            $fallbackSummary = 'Gemini is currently unavailable due to quota limits. '
                . 'In the last 7 days there were ' . $orderCount . ' orders with total value '
                . number_format($totalAmount, 2, '.', '') . ' EUR and average order value ' . $averageAmount . ' EUR. '
                . 'The most common status is ' . $topStatus . ' (' . $topStatusCount . ' orders).';

            $response->getBody()->write(json_encode([
                'summary' => $fallbackSummary,
                'orders' => $orders,
                'fallback' => true,
                'reason' => 'quota_exceeded'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add(new AuthMiddleware());
