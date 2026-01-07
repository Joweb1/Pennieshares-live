<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/content_functions.php';

$pageTitle = "Article View"; 
$content = null;

// Retrieve the slug from the URL
$slug = filter_input(INPUT_GET, 'slug', FILTER_SANITIZE_URL);

if ($slug) {
    $content = getContentByIdOrSlug($slug);
    if ($content) {
        $pageTitle = htmlspecialchars($content['title']);
    } else {
        // Content not found, redirect to learning page
        $_SESSION['error_message'] = "Content not found.";
        header('Location: /learning');
        exit;
    }
} else {
    // No slug provided, redirect to learning page
    header('Location: /learning');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #f4f7fa;
            --bg-secondary: #ffffff;
            --text-primary: #111418;
            --text-secondary: #5a6470;
            --accent-color: #0c7ff2;
            --border-color: #dde3e9;
        }
        html[data-theme="dark"] {
            --bg-primary: #111418;
            --bg-secondary: #1b2127;
            --text-primary: #ffffff;
            --text-secondary: #9cabba;
            --border-color: #3b4754;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            margin: 0;
        }
        .view-header {
            padding: 1rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: var(--bg-primary);
        }
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 500;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1.5rem 2rem;
        }
        .article-header img {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: 12px;
            margin-top: 1rem;
        }
        .article-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        .article-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--text-secondary);
        }
        .article-content h2 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 2.5rem;
            margin-bottom: 1rem;
        }
        .article-content p {
            margin-bottom: 1.5rem;
        }
        .article-content .lead {
            font-size: 1.25rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        .article-content img {
            max-width: 100%;
            border-radius: 8px;
            margin: 2rem 0;
        }
        .article-content ul {
            margin-bottom: 1.5rem;
            padding-left: 1.5rem;
        }
        .article-content blockquote {
            margin: 2rem 0;
            padding-left: 1.5rem;
            border-left: 4px solid var(--accent-color);
            font-style: italic;
            color: var(--text-primary);
        }
        .custom-table {
            width: 100%;
            border-collapse: collapse;
            margin: 2rem 0;
        }
        .custom-table th, .custom-table td {
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            text-align: left;
        }
        .custom-table th {
            background-color: var(--bg-secondary);
        }
    </style>
</head>
<body>

    <header class="view-header">
        <a href="/learning" class="back-button">
            <span class="material-icons-outlined">chevron_left</span>
            Back to Learning
        </a>
    </header>

    <main class="container">
        <article>
            <div class="article-header">
                <img src="<?= htmlspecialchars($content['banner_image'] ?? '/assets/images/placeholder.jpg') ?>" alt="<?= $pageTitle ?>">
                <h1><?= $pageTitle ?></h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    By <?= htmlspecialchars($content['author_username'] ?? 'Admin') ?> on <?= date('F j, Y', strtotime($content['created_at'])) ?>
                    <?php if ($content['type'] === 'learning'): ?>
                        <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                        <?php 
                            if ($content['difficulty'] === 'beginner') echo 'bg-green-100 text-green-800';
                            elseif ($content['difficulty'] === 'intermediate') echo 'bg-yellow-100 text-yellow-800';
                            else echo 'bg-red-100 text-red-800';
                        ?>">
                            <?= htmlspecialchars(ucfirst($content['difficulty'])) ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="article-content">
                <?= $content['content'] ?>
            </div>
        </article>
    </main>

    <script>
        (function() {
            const html = document.documentElement;
            const applyTheme = (theme) => {
                html.setAttribute('data-theme', theme);
            };
            
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            if (savedTheme) {
                applyTheme(savedTheme);
            } else if (prefersDark) {
                applyTheme('dark');
            } else {
                applyTheme('light');
            }
        })();
    </script>

</body>
</html>
