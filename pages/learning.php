<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/content_functions.php'; // Include content management functions

$pageTitle = "Learning Center";

// --- Learning Content Pagination ---
$itemsPerPage = 6;
if (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) {
    $currentPage = (int)$_GET['page'];
} else {
    $currentPage = 1;
}
$offset = ($currentPage - 1) * $itemsPerPage;

// Fetch public learning content with pagination
$learningContent = getAllContent('learning', 'public', $itemsPerPage, $offset);
$totalLearningContent = getTotalContentCount('learning', 'public');
$totalPages = ceil($totalLearningContent / $itemsPerPage);

// --- Random News Content ---
// Fetch 5 random public news content
$newsContent = getAllContent('news', 'public', 5, null, true);

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
        .learning-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background-color: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .learning-header .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .learning-header .brand img {
            width: 32px;
            height: 32px;
        }
        .learning-header .brand h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }
        .kebab-menu-button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
        }
        .kebab-menu-button .icon {
            font-size: 24px;
            color: var(--text-primary);
            transition: color 0.2s ease;
        }
        html[data-theme='dark'] .kebab-menu-button .icon {
            color: #ffffff;
        }
        .modal-menu {
            display: none;
            position: fixed;
            top: 60px;
            right: 1.5rem;
            background-color: var(--bg-secondary);
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            z-index: 100;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        .modal-menu.visible {
            display: block;
        }
        .modal-menu ul {
            list-style: none;
            margin: 0;
            padding: 0.5rem;
        }
        .modal-menu ul li a {
            display: block;
            padding: 0.75rem 1.5rem;
            color: var(--text-primary);
            text-decoration: none;
            transition: background-color 0.2s ease;
        }
        .modal-menu ul li a:hover {
            background-color: var(--bg-primary);
        }
        .container {
            padding: 2rem 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        .course-card {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .course-card-image {
            width: 100%;
            height: 160px;
            object-fit: cover;
        }
        .course-card-content {
            padding: 1rem;
            flex-grow: 1;
        }
        .course-card-category {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--accent-color);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }
        .course-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .course-card-description {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        .news-section {
            margin-top: 4rem;
        }
        .news-carousel {
            display: flex;
            overflow-x: auto;
            gap: 1.5rem;
            padding-bottom: 1.5rem;
            scrollbar-width: none; /* Firefox */
        }
        .news-carousel::-webkit-scrollbar {
            display: none; /* Safari and Chrome */
        }
        .news-card {
            flex: 0 0 320px;
            background-color: var(--bg-secondary);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-decoration: none;
            color: var(--text-primary);
        }
        .news-card-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .news-card-content {
            padding: 1rem;
        }
        .news-card-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .news-card-source {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        .theme-toggle-button {
            background-color: var(--bg-tertiary);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.3s ease;
            position: relative;
            margin: 0 auto; /* Center the button in the list item */
        }
        .theme-toggle-button:hover {
            transform: scale(1.1) rotate(15deg);
        }
        .theme-toggle-button .sun-icon,
        .theme-toggle-button .moon-icon {
            position: absolute;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .theme-toggle-button .sun-icon {
            opacity: 0;
            transform: scale(0);
        }
        .theme-toggle-button .moon-icon {
            opacity: 1;
            transform: scale(1);
        }
        html[data-theme='dark'] .theme-toggle-button .sun-icon {
            opacity: 1;
            transform: scale(1);
        }
        html[data-theme='dark'] .theme-toggle-button .moon-icon {
            opacity: 0;
            transform: scale(0);
        }
        html[data-theme='dark'] .theme-toggle-button .material-icons-outlined {
            color: #ffffff;
        }

        /* Pagination Styles */
        .pagination-button {
            display: inline-flex; /* Changed to inline-flex */
            align-items: center;
            justify-content: center;
            min-width: 40px; /* Re-added */
            max-width: 80px; /* Re-added */
            height: 40px;
            padding: 0 10px;
            border-radius: 8px;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
            text-decoration: none;
            font-weight: 500;
            white-space: nowrap; /* Prevent text from wrapping inside the button */
        }
        .pagination-wrapper {
            flex-wrap: nowrap; /* Force buttons to stay in a single row */
            overflow-x: auto; /* Allow scrolling if too many buttons for small screens */
            padding-bottom: 5px; /* Prevent scrollbar from overlapping content */
            width: fit-content; /* Ensure the wrapper only takes up the space it needs */
            justify-content: center !important; /* Explicitly center content */
            margin: 0 auto !important; /* Force center alignment */
        }
        .pagination-button:hover {
            background-color: var(--bg-tertiary);
            border-color: var(--accent-color);
            color: var(--accent-color);
        }
        .pagination-button.active {
            background-color: var(--accent-color);
            color: var(--accent-text);
            border-color: var(--accent-color);
        }
        .pagination-button.active:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>

    <header class="learning-header">
        <div class="brand">
            <img src="/assets/images/logo.png" alt="Pennieshare Logo">
            <h1>Pennieshare Learning</h1>
        </div>
        <button class="kebab-menu-button" id="kebab-menu-btn" aria-label="Options menu">
            <span class="material-icons-outlined icon">more_vert</span>
        </button>
        <div class="modal-menu" id="modal-menu">
            <ul>
                <li><a href="/terms">Terms</a></li>
                <li><a href="/logout">Logout</a></li>
                <li>
                    <button id="theme-toggle-learning" class="theme-toggle-button">
                        <span class="material-icons-outlined sun-icon">light_mode</span>
                        <span class="material-icons-outlined moon-icon">dark_mode</span>
                    </button>
                </li>
            </ul>
        </div>
    </header>

    <main class="container">
        <section class="learning-section">
            <h2 class="section-title">Trending Courses</h2>
            <div class="course-grid">
                <?php if (empty($learningContent)): ?>
                    <p>No learning content available yet.</p>
                <?php else: ?>
                    <?php foreach ($learningContent as $content): ?>
                        <a href="/learning_view?slug=<?= htmlspecialchars($content['slug']) ?>" class="course-card">
                            <img src="<?= htmlspecialchars($content['banner_image'] ?? '/assets/images/placeholder.jpg') ?>" alt="<?= htmlspecialchars($content['title']) ?>" class="course-card-image">
                            <div class="course-card-content">
                                <p class="course-card-category"><?= htmlspecialchars(ucfirst($content['difficulty'])) ?></p>
                                <h3 class="course-card-title"><?= htmlspecialchars($content['title']) ?></h3>
                                <p class="course-card-description"><?= htmlspecialchars(substr(strip_tags($content['content']), 0, 100)) ?>...</p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination-wrapper flex justify-center items-center space-x-4 mt-8">
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=<?= $currentPage - 1 ?>" class="pagination-button flex-shrink-0">
                            <span class="material-icons-outlined">chevron_left</span>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>" class="pagination-button flex-shrink-0 <?= ($i === $currentPage) ? 'active' : ''; ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?= $currentPage + 1 ?>" class="pagination-button flex-shrink-0">
                            <span class="material-icons-outlined">chevron_right</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="news-section">
            <h2 class="section-title">Latest News</h2>
            <div class="news-carousel">
                <?php if (empty($newsContent)): ?>
                    <p>No news content available yet.</p>
                <?php else: ?>
                    <?php foreach ($newsContent as $content): ?>
                        <a href="/learning_view?slug=<?= htmlspecialchars($content['slug']) ?>" class="news-card">
                            <img src="<?= htmlspecialchars($content['banner_image'] ?? '/assets/images/placeholder.jpg') ?>" alt="<?= htmlspecialchars($content['title']) ?>" class="news-card-image">
                            <div class="news-card-content">
                                <h3 class="news-card-title"><?= htmlspecialchars($content['title']) ?></h3>
                                <p class="news-card-description"><?= htmlspecialchars(substr(strip_tags($content['content']), 0, 100)) ?>...</p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const kebabMenuBtn = document.getElementById('kebab-menu-btn');
            const modalMenu = document.getElementById('modal-menu');

            kebabMenuBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                modalMenu.classList.toggle('visible');
            });

            document.addEventListener('click', (event) => {
                if (!modalMenu.contains(event.target) && !kebabMenuBtn.contains(event.target)) {
                    modalMenu.classList.remove('visible');
                }
            });

            // --- Theme Toggle Logic ---
            const themeToggle = document.getElementById('theme-toggle-learning');
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

            themeToggle.addEventListener('click', () => {
              const currentTheme = html.getAttribute('data-theme');
              const newTheme = currentTheme === 'light' ? 'dark' : 'light';
              applyTheme(newTheme);
              localStorage.setItem('theme', newTheme);
            });
                });
            </script>
        </body>
        </html>
