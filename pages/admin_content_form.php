<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/content_functions.php';
require_once __DIR__ . '/../src/functions.php';

check_auth();

// Check if the user is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['is_admin'] !== 1) {
    header('Location: /login'); // Redirect non-admins
    exit;
}

$allUsers = getAllUsers();

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
    $author_id = filter_input(INPUT_POST, 'author_id', FILTER_VALIDATE_INT);
    $created_at = filter_input(INPUT_POST, 'created_at');

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
    if ($author_id === false) {
        $errors[] = "Invalid author selected.";
    }
    // Validate the date format
    if ($created_at && !DateTime::createFromFormat('Y-m-d\TH:i', $created_at)) {
        $errors[] = "Invalid publication date format.";
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
            'author_id' => $author_id,
            'created_at' => $created_at ? date('Y-m-d H:i:s', strtotime($created_at)) : date('Y-m-d H:i:s')
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
<style>
    #editor-container {
        position: relative;
    }
    #toolbar {
        position: sticky;
        top: 0;
    }
    .editor-btn {
        background-color: #f0f0f0;
        border: 1px solid #ddd;
        border-radius: 4px;
        cursor: pointer;
        padding: 5px 10px;
        min-width: 30px;
        text-align: center;
        /* Remove font-family: monospace; */
    }
    .editor-btn:hover {
        background-color: #e0e0e0;
    }
    .editor-btn.active {
        background-color: #cce5ff;
        border-color: #b8daff;
    }
    .editor-btn .material-icons-outlined {
        font-size: 20px; /* Adjusted size for icons */
        vertical-align: middle; /* Align icon better */
    }
    .prose-editor {
        /* Mimic Tailwind's prose-xl for visual parity */
        font-size: 1.25rem; /* text-xl */
        line-height: 1.75; /* leading-relaxed */
    }
    .prose-editor h1 {
        font-size: 2.25em;
        font-weight: 800;
        margin-top: 0;
        margin-bottom: 0.88em;
    }
    .prose-editor h2 {
        font-size: 1.5em;
        font-weight: 700;
        margin-top: 2em;
        margin-bottom: 1em;
    }
    .prose-editor h3 {
        font-size: 1.25em;
        font-weight: 600;
        margin-top: 1.6em;
        margin-bottom: 0.6em;
    }
    .prose-editor p,
    .prose-editor ul,
    .prose-editor ol {
        margin-bottom: 1.25em;
    }
    .prose-editor ul, .prose-editor ol {
        padding-left: 1.5em;
    }
    .prose-editor li {
        margin-top: 0.5em;
        margin-bottom: 0.5em;
    }
    .prose-editor:focus {
        outline: none;
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }
    #symbol-picker-dropdown {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 5px;
        padding: 5px;
    }
    .symbol-item {
        cursor: pointer;
        padding: 5px 10px;
        text-align: center;
        border-radius: 4px;
    }
    .symbol-item:hover {
        background-color: #f0f0f0;
    }
</style>

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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label for="author_id" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Author:</label>
                <select name="author_id" id="author_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline bg-white dark:bg-gray-700 dark:border-gray-600">
                    <?php foreach ($allUsers as $user): ?>
                        <option value="<?= $user['id']; ?>" <?= (isset($oldInput['author_id']) && $oldInput['author_id'] == $user['id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($user['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="created_at" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Publication Date:</label>
                <input type="datetime-local" name="created_at" id="created_at" value="<?= isset($oldInput['created_at']) ? date('Y-m-d\TH:i', strtotime($oldInput['created_at'])) : date('Y-m-d\TH:i'); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline bg-white dark:bg-gray-700 dark:border-gray-600">
            </div>
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
            <label for="content-editor" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Content:</label>
            <div id="editor-container" class="border border-gray-300 dark:border-gray-600 rounded-lg">
                <div id="toolbar" class="sticky top-0 z-10 bg-gray-100 dark:bg-gray-700 p-2 rounded-t-lg flex flex-wrap items-center gap-2 border-b border-gray-300 dark:border-gray-600">
                    <!-- Basic Formatting -->
                    <button type="button" class="editor-btn" data-command="bold"><span class="material-icons-outlined">format_bold</span></button>
                    <button type="button" class="editor-btn" data-command="italic"><span class="material-icons-outlined">format_italic</span></button>
                    <button type="button" class="editor-btn" data-command="underline"><span class="material-icons-outlined">format_underlined</span></button>
                    <button type="button" class="editor-btn" data-command="superscript"><span class="material-icons-outlined">format_superscript</span></button>
                    <button type="button" class="editor-btn" data-command="subscript"><span class="material-icons-outlined">format_subscript</span></button>
                    <!-- Layout -->
                    <button type="button" class="editor-btn" data-command="formatBlock" data-value="H1">H1</button>
                    <button type="button" class="editor-btn" data-command="formatBlock" data-value="H2">H2</button>
                    <button type="button" class="editor-btn" data-command="formatBlock" data-value="H3">H3</button>
                    <button type="button" class="editor-btn" data-command="formatBlock" data-value="P">P</button>
                    <button type="button" class="editor-btn" data-command="insertUnorderedList"><span class="material-icons-outlined">format_list_bulleted</span></button>
                    <button type="button" class="editor-btn" data-command="insertOrderedList"><span class="material-icons-outlined">format_list_numbered</span></button>
                    <!-- Alignment -->
                    <button type="button" class="editor-btn" data-command="justifyLeft"><span class="material-icons-outlined">format_align_left</span></button>
                    <button type="button" class="editor-btn" data-command="justifyCenter"><span class="material-icons-outlined">format_align_center</span></button>
                    <button type="button" class="editor-btn" data-command="justifyRight"><span class="material-icons-outlined">format_align_right</span></button>
                        <!-- History -->
                        <button type="button" class="editor-btn" data-command="undo"><span class="material-icons-outlined">undo</span></button>
                        <button type="button" class="editor-btn" data-command="redo"><span class="material-icons-outlined">redo</span></button>
                        <!-- Mathematical Integration -->
                        <button type="button" id="fraction-tool-btn" class="editor-btn"><sup>1</sup>&frasl;<sub>2</sub></button>
                    </div>                <div id="content-editor" contenteditable="true" class="prose-editor p-4 h-96 overflow-y-auto">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            <textarea name="content" id="content" class="hidden" required><?= htmlspecialchars($oldInput['content'] ?? ''); ?></textarea>
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
        const toolbar = document.getElementById('toolbar');
        const editor = document.getElementById('content-editor');
        const hiddenTextarea = document.getElementById('content');

        // 1. Load existing HTML content into the editor
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = hiddenTextarea.value;
        editor.innerHTML = tempDiv.textContent || tempDiv.innerText || "";

        // 2. Add event listener for toolbar commands
        toolbar.addEventListener('click', (e) => {
            const target = e.target.closest('.editor-btn');
            if (!target) return;

            e.preventDefault();
            const command = target.dataset.command;
            const value = target.dataset.value || null;

            document.execCommand(command, false, value);
            editor.focus();
        });

        // 3. Sync editor content back to the hidden textarea on input
        editor.addEventListener('input', () => {
            hiddenTextarea.value = editor.innerHTML;
        });

        // Fraction Tool Logic (Remaining from Mathematical Tools)
        const fractionToolBtn = document.getElementById('fraction-tool-btn');
        fractionToolBtn.addEventListener('click', () => {
            editor.focus();
            const fractionHTML = '<sup>1</sup>&frasl;<sub>2</sub>';
            document.execCommand('insertHTML', false, fractionHTML);
        });

        // 5. Sanitize pasted content
        editor.addEventListener('paste', (e) => {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
        });

        // --- Logic from previous step for difficulty field ---
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
        toggleDifficultyField(); // Initial check
    });
</script>

<?php include 'assets/template/end-template.php'; ?>
