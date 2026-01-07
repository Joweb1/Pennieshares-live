<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/content_functions.php';

check_auth();

// Check if the user is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['is_admin'] !== 1) {
    header('Location: /login'); // Redirect non-admins
    exit;
}

$pageTitle = "Manage Content";
$pageDescription = "Admin panel for managing learning and news content.";

$content = null;
$editMode = false;
$errors = [];
$oldInput = [];

// Handle GET request for editing content
if (isset($_GET['id'])) {
    $contentId = (int)$_GET['id'];
    $content = getContentByIdOrSlug($contentId);
    if ($content) {
        $editMode = true;
        $pageTitle = "Edit Content: " . htmlspecialchars($content['title']);
        $oldInput = $content; // Populate form with existing data
    } else {
        $_SESSION['error_message'] = "Content not found.";
        header('Location: /admin_content');
        exit;
    }
} else {
    $pageTitle = "Add New Content";
    // Default values for new content
    $oldInput = [
        'type' => 'learning',
        'title' => '',
        'banner_image' => '',
        'content' => '',
        'status' => 'private',
        'difficulty' => 'beginner',
    ];
}

// Handle POST request for creating/updating content
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldInput = $_POST; // Keep old input for re-population

    $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $text_content = $_POST['content'] ?? ''; // HTML content, no sanitization here, handle on display
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $difficulty = filter_input(INPUT_POST, 'difficulty', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $current_banner_image = filter_input(INPUT_POST, 'current_banner_image', FILTER_SANITIZE_URL);

    // Basic Validation
    if (empty($type) || !in_array($type, ['learning', 'news'])) {
        $errors[] = "Invalid content type.";
    }
    if (empty($title)) {
        $errors[] = "Title cannot be empty.";
    }
    if (empty($text_content)) {
        $errors[] = "Content cannot be empty.";
    }
    if (empty($status) || !in_array($status, ['public', 'private'])) {
        $errors[] = "Invalid status.";
    }
    if ($type === 'learning' && (empty($difficulty) || !in_array($difficulty, ['beginner', 'intermediate', 'advanced']))) {
        $errors[] = "Invalid difficulty for learning content.";
    }


    $banner_image_path = $current_banner_image; // Default to current image if not uploaded new

    // Handle banner image upload
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        $uploadedImagePath = uploadImage($_FILES['banner_image']);
        if ($uploadedImagePath) {
            $banner_image_path = $uploadedImagePath;
        } else {
            $errors[] = "Failed to upload banner image.";
        }
    } else if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "Error uploading banner image: " . $_FILES['banner_image']['error'];
    }

    if (empty($errors)) {
        $contentData = [
            'type' => $type,
            'title' => $title,
            'banner_image' => $banner_image_path,
            'content' => $text_content,
            'status' => $status,
            'difficulty' => ($type === 'learning' ? $difficulty : null),
            'author_id' => $_SESSION['user']['id']
        ];

        if ($editMode) {
            if (updateContent($contentId, $contentData)) {
                $_SESSION['success_message'] = "Content updated successfully!";
                header('Location: /admin_content');
                exit;
            } else {
                $errors[] = "Failed to update content.";
            }
        } else {
            if (createContent($contentData)) {
                $_SESSION['success_message'] = "Content created successfully!";
                header('Location: /admin_content');
                exit;
            } else {
                $errors[] = "Failed to create content.";
            }
        }
    }
}

?>
<?php include 'assets/template/intro-template.php'; ?>

<div class="container mx-auto p-6 bg-white dark:bg-gray-800 shadow-md rounded-lg">
    <h1 class="text-3xl font-bold mb-6 text-gray-900 dark:text-white"><?= $pageTitle; ?></h1>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <ul class="list-disc pl-5">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="current_banner_image" value="<?= htmlspecialchars($oldInput['banner_image'] ?? ''); ?>">

        <div class="mb-4">
            <label for="type" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Content Type:</label>
            <select name="type" id="type" class="shadow border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline bg-white dark:bg-gray-700 dark:border-gray-600">
                <option value="learning" <?= (isset($oldInput['type']) && $oldInput['type'] === 'learning') ? 'selected' : ''; ?>>Learning</option>
                <option value="news" <?= (isset($oldInput['type']) && $oldInput['type'] === 'news') ? 'selected' : ''; ?>>News</option>
            </select>
        </div>

        <div class="mb-4">
            <label for="title" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Title:</label>
            <input type="text" name="title" id="title" value="<?= htmlspecialchars($oldInput['title'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline bg-white dark:bg-gray-700 dark:border-gray-600" required>
        </div>

        <div class="mb-4">
            <label for="banner_image" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Banner Image:</label>
            <input type="file" name="banner_image" id="banner_image" class="shadow border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline bg-white dark:bg-gray-700 dark:border-gray-600">
            <?php if (!empty($oldInput['banner_image'])): ?>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Current Image: <a href="<?= htmlspecialchars($oldInput['banner_image']); ?>" target="_blank" class="text-blue-500 hover:underline">View</a></p>
                <img src="<?= htmlspecialchars($oldInput['banner_image']); ?>" alt="Current Banner" class="mt-2 h-20 w-auto object-cover">
            <?php endif; ?>
        </div>

        <div class="mb-4">
            <label for="content" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Content (HTML allowed):</label>
            <textarea name="content" id="content" rows="15" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline bg-white dark:bg-gray-700 dark:border-gray-600" required><?= htmlspecialchars($oldInput['content'] ?? ''); ?></textarea>
        </div>

        <div class="mb-4">
            <label for="status" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Status:</label>
            <select name="status" id="status" class="shadow border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline bg-white dark:bg-gray-700 dark:border-gray-600">
                <option value="public" <?= (isset($oldInput['status']) && $oldInput['status'] === 'public') ? 'selected' : ''; ?>>Public</option>
                <option value="private" <?= (isset($oldInput['status']) && $oldInput['status'] === 'private') ? 'selected' : ''; ?>>Private</option>
            </select>
        </div>

        <div class="mb-4" id="difficulty-field" style="<?= (isset($oldInput['type']) && $oldInput['type'] === 'news') ? 'display: none;' : ''; ?>">
            <label for="difficulty" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Difficulty (for Learning):</label>
            <select name="difficulty" id="difficulty" class="shadow border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline bg-white dark:bg-gray-700 dark:border-gray-600">
                <option value="beginner" <?= (isset($oldInput['difficulty']) && $oldInput['difficulty'] === 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                <option value="intermediate" <?= (isset($oldInput['difficulty']) && $oldInput['difficulty'] === 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                <option value="advanced" <?= (isset($oldInput['difficulty']) && $oldInput['difficulty'] === 'advanced') ? 'selected' : ''; ?>>Advanced</option>
            </select>
        </div>

        <div class="flex items-center justify-between">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <?= $editMode ? 'Update Content' : 'Create Content'; ?>
            </button>
            <a href="/admin_content" class="inline-block align-baseline font-bold text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-600">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const contentTypeSelect = document.getElementById('type');
        const difficultyField = document.getElementById('difficulty-field');

        const toggleDifficultyField = () => {
            if (contentTypeSelect.value === 'learning') {
                difficultyField.style.display = 'block';
            } else {
                difficultyField.style.display = 'none';
            }
        };

        contentTypeSelect.addEventListener('change', toggleDifficultyField);

        // Initial check on page load
        toggleDifficultyField();
    });
</script>

<?php include 'assets/template/end-template.php'; ?>
