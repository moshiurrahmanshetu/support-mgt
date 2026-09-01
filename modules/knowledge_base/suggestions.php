<?php
/**
 * Knowledge Base Article Suggestions Endpoint (support-mgt Phase 06)
 * Returns JSON array of published articles matching query
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/knowledge_base.php';

$kbEnabled = (bool)get_setting('knowledge_base_enabled', true);
if (!$kbEnabled) {
    echo json_encode(['suggestions' => []]);
    exit;
}

$query = trim($_GET['q'] ?? '');

// Need at least 3 characters for suggestions
if (mb_strlen($query) < 3) {
    echo json_encode(['suggestions' => []]);
    exit;
}

// Truncate query length for security and performance
if (mb_strlen($query) > 100) {
    $query = mb_substr($query, 0, 100);
}

try {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT 
            a.id,
            a.title,
            a.slug,
            c.name AS category_name,
            c.icon AS category_icon
        FROM knowledge_base_articles a
        JOIN knowledge_base_categories c ON a.category_id = c.id
        WHERE a.status = 'published'
          AND c.status = 'active'
          AND (a.title LIKE ? OR a.excerpt LIKE ?)
        ORDER BY 
            (CASE WHEN a.title LIKE ? THEN 1 ELSE 2 END),
            a.view_count DESC
        LIMIT 5
    ");
    $param = "%$query%";
    $stmt->execute([$param, $param, $param]);
    $results = $stmt->fetchAll();

    $suggestions = [];
    foreach ($results as $row) {
        $suggestions[] = [
            'id'            => (int)$row['id'],
            'title'         => $row['title'],
            'category_name' => $row['category_name'],
            'category_icon' => $row['category_icon'],
            'url'           => url('modules/knowledge_base/view.php?slug=' . urlencode($row['slug']))
        ];
    }

    echo json_encode([
        'query'       => $query,
        'count'       => count($suggestions),
        'suggestions' => $suggestions
    ]);
} catch (Exception $e) {
    error_log("KB suggestions error: " . $e->getMessage());
    echo json_encode(['suggestions' => []]);
}
exit;
