<?php
/**
 * Knowledge Base & Support Center Helper (support-mgt Phase 06)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/settings.php';

/**
 * Generate a unique SEO slug from a title string
 *
 * @param string $table Target table name (knowledge_base_categories or knowledge_base_articles)
 * @param string $title Title or Name
 * @param int|null $excludeId ID to exclude during updates
 * @return string
 */
function generate_unique_slug(string $table, string $title, ?int $excludeId = null): string {
    $db = get_db();

    // 1. Basic clean slug
    $slug = preg_replace('~[^\pL\d]+~u', '-', $title);
    $slug = iconv('utf-8', 'us-ascii//TRANSLIT', $slug);
    $slug = preg_replace('~[^-\w]+~', '', $slug);
    $slug = trim($slug, '-');
    $slug = preg_replace('~-+~', '-', $slug);
    $slug = strtolower($slug);

    if (empty($slug)) {
        $slug = 'item-' . bin2hex(random_bytes(3));
    }

    $originalSlug = $slug;
    $counter = 1;

    // 2. Check for collisions
    while (true) {
        $checkSql = "SELECT id FROM `{$table}` WHERE slug = ?";
        $params = [$slug];

        if ($excludeId !== null) {
            $checkSql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $db->prepare($checkSql);
        $stmt->execute($params);

        if (!$stmt->fetch()) {
            return $slug;
        }

        $counter++;
        $slug = $originalSlug . '-' . $counter;
    }
}

/**
 * Increment view count for an article (session-guarded to prevent refresh abuse)
 *
 * @param int $articleId
 * @return void
 */
function increment_article_view_count(int $articleId): void {
    if ($articleId <= 0) {
        return;
    }

    if (!isset($_SESSION['viewed_articles']) || !is_array($_SESSION['viewed_articles'])) {
        $_SESSION['viewed_articles'] = [];
    }

    // Only increment once per session
    if (!in_array($articleId, $_SESSION['viewed_articles'], true)) {
        try {
            $db = get_db();
            $stmt = $db->prepare("UPDATE knowledge_base_articles SET view_count = view_count + 1 WHERE id = ?");
            $stmt->execute([$articleId]);
            $_SESSION['viewed_articles'][] = $articleId;
        } catch (Exception $e) {
            error_log("Failed to increment article view count: " . $e->getMessage());
        }
    }
}

/**
 * Format and sanitize article body for safe public rendering (preserves safe formatting & line breaks)
 *
 * @param string $content
 * @return string
 */
function render_article_content(string $content): string {
    // 1. Convert Markdown-style formatting safely
    $text = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

    // Headings (### -> <h5>, ## -> <h4>, # -> <h3>)
    $text = preg_replace('/^### (.+)$/m', '<h5 class="fw-bold mt-4 mb-2 text-dark">$1</h5>', $text);
    $text = preg_replace('/^## (.+)$/m', '<h4 class="fw-bold mt-4 mb-3 text-dark">$1</h4>', $text);
    $text = preg_replace('/^# (.+)$/m', '<h3 class="fw-bold mt-4 mb-3 text-dark">$1</h3>', $text);

    // Bold (**text** or __text__)
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $text);

    // Italics (*text* or _text_)
    $text = preg_replace('/\*([^\*]+)\*/', '<em>$1</em>', $text);

    // Code blocks (`code`)
    $text = preg_replace('/`([^`]+)`/', '<code class="bg-light px-2 py-1 rounded text-primary border">$1</code>', $text);

    // Unordered lists (- item or * item)
    $text = preg_replace('/^\s*[-*]\s+(.+)$/m', '<li>$1</li>', $text);
    $text = preg_replace('/(<li>.*<\/li>)/s', '<ul class="mb-3 ps-3">$1</ul>', $text);

    // Clean duplicate <ul> wrappers if present
    $text = str_replace("</ul>\n<ul class=\"mb-3 ps-3\">", '', $text);

    // Ordered lists (1. item)
    $text = preg_replace('/^\s*\d+\.\s+(.+)$/m', '<li class="mb-1">$1</li>', $text);

    // Convert line breaks
    $text = nl2br($text);

    return $text;
}

/**
 * Get active categories with count of published articles
 *
 * @return array
 */
function get_active_categories_with_counts(): array {
    try {
        $db = get_db();
        $stmt = $db->query("
            SELECT 
                c.*,
                COUNT(a.id) AS article_count
            FROM knowledge_base_categories c
            LEFT JOIN knowledge_base_articles a ON c.id = a.category_id AND a.status = 'published'
            WHERE c.status = 'active'
            GROUP BY c.id
            ORDER BY c.sort_order ASC, c.name ASC
        ");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Failed to fetch active KB categories: " . $e->getMessage());
        return [];
    }
}

/**
 * Get featured published articles
 *
 * @param int $limit
 * @return array
 */
function get_featured_articles(int $limit = 6): array {
    try {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT a.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon
            FROM knowledge_base_articles a
            JOIN knowledge_base_categories c ON a.category_id = c.id
            WHERE a.status = 'published' AND a.is_featured = 1 AND c.status = 'active'
            ORDER BY a.published_at DESC, a.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Failed to fetch featured KB articles: " . $e->getMessage());
        return [];
    }
}

/**
 * Get popular (most viewed) published articles
 *
 * @param int $limit
 * @return array
 */
function get_popular_articles(int $limit = 6): array {
    try {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT a.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon
            FROM knowledge_base_articles a
            JOIN knowledge_base_categories c ON a.category_id = c.id
            WHERE a.status = 'published' AND c.status = 'active'
            ORDER BY a.view_count DESC, a.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Failed to fetch popular KB articles: " . $e->getMessage());
        return [];
    }
}

/**
 * Get related articles in the same category
 *
 * @param int $categoryId
 * @param int $currentArticleId
 * @param int $limit
 * @return array
 */
function get_related_articles(int $categoryId, int $currentArticleId, int $limit = 4): array {
    try {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT a.id, a.title, a.slug, a.excerpt, a.view_count, a.published_at, a.created_at
            FROM knowledge_base_articles a
            WHERE a.category_id = ? AND a.id != ? AND a.status = 'published'
            ORDER BY a.view_count DESC, a.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(2, $currentArticleId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Failed to fetch related KB articles: " . $e->getMessage());
        return [];
    }
}
