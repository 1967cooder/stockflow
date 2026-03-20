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

// $isAiQuotaError = function (string $message): bool {
//     $normalized = strtolower($message);
//     $needles = [
//         'quota exceeded',
//         'rate limit',
//         'resource_exhausted',
//         '429',
//         'too many requests',
//         'free_tier',
//     ];

//     foreach ($needles as $needle) {
//         if (str_contains($normalized, $needle)) {
//             return true;
//         }
//     }

//     return false;
// };

// $aiFallbackEnabled = function (): bool {
//     $value = strtolower(trim((string)($_ENV['AI_FALLBACK_ENABLED'] ?? 'true')));
//     $value = trim($value, " \t\n\r\0\x0B.;,!");
//     return !in_array($value, ['0', 'false', 'off', 'no'], true);
// };

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
$app->post('/api/ai/describe', function (Request $request, Response $response) {

    $body = $request->getParsedBody();
    $productId = $body['product_id'] ?? null;

    if (!$productId) {
        $response->getBody()->write(json_encode(['error' => 'product_id is required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Fetch the product from Supabase
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));
    $products = $auth->query('products', [
        'id' => 'eq.' . $productId,
        'select' => '*,categories(name)'
    ]);

    if (empty($products)) {
        $response->getBody()->write(json_encode(['error' => 'Product not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $product = $products[0];
    $categoryName = $product['categories']['name'] ?? 'General';

    // Build prompt
    $prompt = "Write a short product description (2-3 sentences) for a product called: " . $product['name'] . ". "
            . "Category: " . $categoryName . ". "
            . "Price: " . number_format((float)$product['price'], 2) . " EUR. "
            . "Make it engaging and suitable for an e-commerce product listing.";

    try {
        $ai = new GeminiAI();
        $description = $ai->ask($prompt);

        $response->getBody()->write(json_encode([
            'description' => $description
        ]));
        return $response->withHeader('Content-Type', 'application/json');

    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => 'AI generation failed: ' . $e->getMessage()
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
$app->post('/api/ai/stock-advice', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Fetch all products
    $products = $auth->query('products', [
        'select' => 'name,stock_quantity,reorder_threshold',
        'status' => 'eq.active'
    ]);

    // Filter to low-stock products in PHP
    $lowStock = array_filter($products, function ($p) {
        return (int)$p['stock_quantity'] <= (int)$p['reorder_threshold'];
    });

    if (empty($lowStock)) {
        $response->getBody()->write(json_encode([
            'advice' => 'All products are well-stocked. No reorders needed at this time.',
            'products' => []
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Build prompt
    $lines = [];
    foreach ($lowStock as $p) {
        $lines[] = "- " . $p['name'] . ": " . $p['stock_quantity'] . " in stock, threshold: " . $p['reorder_threshold'];
    }

    $prompt = "These products are running low on stock. For each, suggest a reorder quantity based on the current stock and threshold. Give a brief recommendation for each:\n\n"
            . implode("\n", $lines)
            . "\n\nKeep recommendations concise and practical.";

    try {
        $ai = new GeminiAI();
        $advice = $ai->ask($prompt);

        $response->getBody()->write(json_encode([
            'advice' => $advice,
            'products' => array_values($lowStock)
        ]));
        return $response->withHeader('Content-Type', 'application/json');

    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => 'AI generation failed: ' . $e->getMessage()
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
$app->post('/api/ai/summarize-orders', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Fetch recent orders
    $orders = $auth->query('orders', [
        'select' => '*',
        'order' => 'created_at.desc',
        'limit' => 20
    ]);

    if (empty($orders)) {
        $response->getBody()->write(json_encode([
            'summary' => 'No orders found to summarize.'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Build prompt with order data
    $lines = [];
    foreach ($orders as $o) {
        $date = date('j M Y', strtotime($o['created_at']));
        $lines[] = "- $date | " . $o['customer_name'] . " | Status: " . $o['status'] . " | Total: " . number_format((float)$o['total_amount'], 2) . " EUR";
    }

    $prompt = "Here are the recent orders for our inventory management system. Summarize the trends, identify any patterns, and provide a brief business insight:\n\n"
            . implode("\n", $lines)
            . "\n\nKeep the summary to 3-5 sentences.";

    try {
        $ai = new GeminiAI();
        $summary = $ai->ask($prompt);

        $response->getBody()->write(json_encode([
            'summary' => $summary
        ]));
        return $response->withHeader('Content-Type', 'application/json');

    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => 'AI generation failed: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add(new AuthMiddleware());