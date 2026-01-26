<?php

require_once __DIR__ . '/init.php'; // For database connection ($pdo_mysql) and other utilities

/**
 * Generates a URL-friendly slug from a string.
 * @param string $text The input string.
 * @return string The generated slug.
 */
function generateSlug(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text); // Replace non-alphanumeric with a dash
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text); // Transliterate
    $text = preg_replace('~[^-\w]+~', '', $text); // Remove unwanted characters
    $text = trim($text, '-'); // Trim dashes from beginning and end
    $text = preg_replace('~-+~', '-', $text); // Replace multiple dashes with a single one
    $text = strtolower($text); // Convert to lowercase

    if (empty($text)) {
        return 'n-a';
    }
    return $text;
}

/**
 * Creates new content (learning or news) in the database.
 * @param array $data Associative array containing content data.
 * @return int|false The ID of the new content, or false on failure.
 */
function createContent(array $data) {
    global $pdo_mysql;

    $type = $data['type'] ?? 'learning';
    $title = $data['title'] ?? '';
    $slug = generateSlug($title);
    $banner_image = $data['banner_image'] ?? null;
    $content = $data['content'] ?? '';
    $status = $data['status'] ?? 'private';
    $difficulty = $data['difficulty'] ?? 'beginner';
    $author_id = $data['author_id'] ?? $_SESSION['user']['id'] ?? null;

    if (empty($title) || empty($content) || !$author_id) {
        return false; // Basic validation
    }

    // Ensure slug is unique
    $originalSlug = $slug;
    $counter = 1;
    while (slugExists($slug)) {
        $slug = $originalSlug . '-' . $counter++;
    }

    try {
        $stmt = $pdo_mysql->prepare("INSERT INTO content (type, title, slug, banner_image, content, status, difficulty, author_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$type, $title, $slug, $banner_image, $content, $status, $difficulty, $author_id]);
        return $pdo_mysql->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error creating content: " . $e->getMessage());
        return false;
    }
}

/**
 * Checks if a slug already exists in the content table.
 * @param string $slug The slug to check.
 * @return bool True if the slug exists, false otherwise.
 */
function slugExists(string $slug): bool {
    global $pdo_mysql;
    $stmt = $pdo_mysql->prepare("SELECT COUNT(*) FROM content WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Retrieves content by ID or slug.
 * @param int|string $identifier Content ID or slug.
 * @return array|false Content data or false if not found.
 */
function getContentByIdOrSlug($identifier) {
    global $pdo_mysql;
    $field = is_numeric($identifier) ? 'id' : 'slug';
    try {
        $stmt = $pdo_mysql->prepare("SELECT c.*, u.username as author_username FROM content c LEFT JOIN users u ON c.author_id = u.id WHERE c.$field = ?");
        $stmt->execute([$identifier]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching content: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates existing content in the database.
 * @param int $id The ID of the content to update.
 * @param array $data Associative array containing updated content data.
 * @return bool True on success, false on failure.
 */
function updateContent(int $id, array $data): bool {
    global $pdo_mysql;

    $fields = [];
    $values = [];

    if (isset($data['type'])) { $fields[] = 'type = ?'; $values[] = $data['type']; }
    if (isset($data['title'])) { $fields[] = 'title = ?'; $values[] = $data['title']; }
    // Slug generation logic - only update if title changes AND it's not explicitly set to null/empty in data
    if (isset($data['title']) || isset($data['slug'])) {
        $currentContent = getContentByIdOrSlug($id);
        $baseTitle = $data['title'] ?? ($currentContent['title'] ?? '');
        $newSlug = generateSlug($baseTitle);
        $originalSlug = $newSlug;
        $counter = 1;
        while (slugExistsExceptSelf($newSlug, $id)) {
            $newSlug = $originalSlug . '-' . $counter++;
        }
        $fields[] = 'slug = ?'; $values[] = $newSlug;
    }
    if (isset($data['banner_image'])) { $fields[] = 'banner_image = ?'; $values[] = $data['banner_image']; }
    if (isset($data['content'])) { $fields[] = 'content = ?'; $values[] = $data['content']; }
    if (isset($data['status'])) { $fields[] = 'status = ?'; $values[] = $data['status']; }
    if (isset($data['difficulty'])) { $fields[] = 'difficulty = ?'; $values[] = $data['difficulty']; }
    // author_id is typically set on creation, not updated frequently, but could be added if needed

    if (empty($fields)) {
        return false; // No data to update
    }

    $values[] = $id; // Add ID for WHERE clause

    $query = "UPDATE content SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    try {
        $stmt = $pdo_mysql->prepare($query);
        return $stmt->execute($values);
    } catch (PDOException $e) {
        error_log("Error updating content ID $id: " . $e->getMessage());
        return false;
    }
}

/**
 * Checks if a slug exists for any content except the given ID.
 * Used for updating content slugs.
 * @param string $slug The slug to check.
 * @param int $excludeId The ID of the content to exclude from the check.
 * @return bool True if the slug exists, false otherwise.
 */
function slugExistsExceptSelf(string $slug, int $excludeId): bool {
    global $pdo_mysql;
    $stmt = $pdo_mysql->prepare("SELECT COUNT(*) FROM content WHERE slug = ? AND id != ?");
    $stmt->execute([$slug, $excludeId]);
    return $stmt->fetchColumn() > 0;
}


/**
 * Deletes content from the database.
 * @param int $id The ID of the content to delete.
 * @return bool True on success, false on failure.
 */
function deleteContent(int $id): bool {
    global $pdo_mysql;
    try {
        $stmt = $pdo_mysql->prepare("DELETE FROM content WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Error deleting content ID $id: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieves content based on type, status, with optional limit, offset, and random ordering.
 * @param string|null $type Filter by 'learning' or 'news' or null for all.
 * @param string|null $status Filter by 'public', 'private' or null for all.
 * @param int|null $limit Maximum number of results to return.
 * @param int|null $offset Offset for pagination.
 * @param bool $random Whether to order results randomly.
 * @return array An array of content data.
 */
function getAllContent(?string $type = null, ?string $status = null, ?int $limit = null, ?int $offset = null, bool $random = false): array {
    global $pdo_mysql;
    $query = "SELECT c.*, u.username as author_username FROM content c LEFT JOIN users u ON c.author_id = u.id";
    $conditions = [];
    $values = [];

    if ($type) {
        $conditions[] = "c.type = ?";
        $values[] = $type;
    }
    if ($status) {
        $conditions[] = "c.status = ?";
        $values[] = $status;
    }

    if (!empty($conditions)) {
        $query .= " WHERE " . implode(' AND ', $conditions);
    }
    
    if ($random) {
        $query .= " ORDER BY RAND()";
    } else {
        $query .= " ORDER BY c.created_at DESC";
    }

    // Append LIMIT and OFFSET directly to the query string after validation
    if ($limit !== null) {
        // Ensure limit is an integer to prevent SQL injection
        $query .= " LIMIT " . (int)$limit;
    }
    if ($offset !== null) {
        // Ensure offset is an integer to prevent SQL injection
        $query .= " OFFSET " . (int)$offset;
    }

    try {
        $stmt = $pdo_mysql->prepare($query);

        // Bind WHERE clause parameters
        foreach ($values as $key => $value) {
            // PDO parameters are 1-indexed for positional
            $stmt->bindValue($key + 1, $value);
        }

        // LIMIT and OFFSET are now directly in the query string, no need to bind here.

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching all content: " . $e->getMessage());
        return [];
    }
}

/**
 * Gets the total count of content entries based on type and status.
 * @param string|null $type Filter by 'learning' or 'news' or null for all.
 * @param string|null $status Filter by 'public', 'private' or null for all.
 * @return int The total number of content entries.
 */
function getTotalContentCount(?string $type = null, ?string $status = null): int {
    global $pdo_mysql;
    $query = "SELECT COUNT(*) FROM content";
    $conditions = [];
    $values = [];

    if ($type) {
        $conditions[] = "type = ?";
        $values[] = $type;
    }
    if ($status) {
        $conditions[] = "status = ?";
        $values[] = $status;
    }

    if (!empty($conditions)) {
        $query .= " WHERE " . implode(' AND ', $conditions);
    }

    try {
        $stmt = $pdo_mysql->prepare($query);
        $stmt->execute($values);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting total content count: " . $e->getMessage());
        return 0;
    }
}

function uploadImage($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedMimeTypes)) {
        return false;
    }

    $uploadDir = 'uploads/content_banners/';
    $absoluteUploadDir = __DIR__ . '/../' . $uploadDir;
    if (!is_dir($absoluteUploadDir)) {
        mkdir($absoluteUploadDir, 0755, true);
    }

    $newFileName = uniqid() . '-' . basename($file['name']);
    $destination = $absoluteUploadDir . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Return the web-accessible path, relative to project root
        return $uploadDir . $newFileName;
    }

    return false;
}
