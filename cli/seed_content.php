<?php
require_once __DIR__ . '/../src/init.php';

echo "Seeding content table with mock data...\n";

// Ensure a dummy admin user exists for author_id
$stmt = $pdo_mysql->prepare("SELECT id FROM users WHERE is_admin = 1 LIMIT 1");
$stmt->execute();
$adminId = $stmt->fetchColumn();

if (!$adminId) {
    // Create a dummy admin if none exists
    $passwordHash = password_hash('adminpassword', PASSWORD_DEFAULT);
    $stmt = $pdo_mysql->prepare("INSERT INTO users (fullname, email, username, phone, referral, password, is_admin, is_verified) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");
    $stmt->execute(['Admin User', 'admin@example.com', 'admin', '1234567890', 'none', $passwordHash,]);
    $adminId = $pdo_mysql->lastInsertId();
    echo "Created a dummy admin user with ID: $adminId\n";
}

$mockData = [
    // Learning Content
    [
        'type' => 'learning',
        'title' => 'Introduction to Investing',
        'slug' => 'introduction-to-investing',
        'banner_image' => 'https://via.placeholder.com/800x400/007bff/ffffff?text=Investing+Basics',
        'content' => '<p>Investing can seem daunting at first, but it\'s a powerful way to grow your wealth over time. This guide covers the fundamental concepts.</p><p>Key takeaways:</p><ul><li>Understand your financial goals.</li><li>Diversify your portfolio.</li><li>Start early and be consistent.</li></ul><p><img src="https://via.placeholder.com/600x300/28a745/ffffff?text=Growth+Chart" alt="Growth Chart"></p><p>Remember, patience is key in investing.</p>',
        'status' => 'public',
        'difficulty' => 'beginner',
        'author_id' => $adminId,
    ],
    [
        'type' => 'learning',
        'title' => 'Understanding Asset Classes',
        'slug' => 'understanding-asset-classes',
        'banner_image' => 'https://via.placeholder.com/800x400/ffc107/333333?text=Asset+Classes',
        'content' => '<p>Different asset classes offer varying risk and return profiles. Learn about stocks, bonds, real estate, and commodities.</p><p>Stocks offer growth potential, while bonds provide stability.</p>',
        'status' => 'public',
        'difficulty' => 'intermediate',
        'author_id' => $adminId,
    ],
    [
        'type' => 'learning',
        'title' => 'Risk Management in Trading',
        'slug' => 'risk-management-in-trading',
        'banner_image' => 'https://via.placeholder.com/800x400/dc3545/ffffff?text=Risk+Management',
        'content' => '<p>Effective risk management is crucial for long-term success in trading. Never risk more than you can afford to lose.</p><ul><li>Set stop-loss orders.</li><li>Use proper position sizing.</li><li>Avoid emotional decisions.</li></ul>',
        'status' => 'public',
        'difficulty' => 'advanced',
        'author_id' => $adminId,
    ],
    [
        'type' => 'learning',
        'title' => 'The Power of Compounding',
        'slug' => 'the-power-of-compounding',
        'banner_image' => 'https://via.placeholder.com/800x400/6f42c1/ffffff?text=Compounding',
        'content' => '<p>Albert Einstein once called compound interest the eighth wonder of the world. Discover how your investments can grow exponentially.</p>',
        'status' => 'public',
        'difficulty' => 'beginner',
        'author_id' => $adminId,
    ],
    [
        'type' => 'learning',
        'title' => 'Technical Analysis Explained',
        'slug' => 'technical-analysis-explained',
        'banner_image' => 'https://via.placeholder.com/800x400/20c997/ffffff?text=Technical+Analysis',
        'content' => '<p>Technical analysis involves evaluating investments by analyzing statistical trends gathered from trading activity, such as price movement and volume.</p>',
        'status' => 'private', // Private content example
        'difficulty' => 'intermediate',
        'author_id' => $adminId,
    ],
    [
        'type' => 'learning',
        'title' => 'Fundamental Analysis for Investors',
        'slug' => 'fundamental-analysis-for-investors',
        'banner_image' => 'https://via.placeholder.com/800x400/fd7e14/ffffff?text=Fundamental+Analysis',
        'content' => '<p>Learn to evaluate a stock\'s intrinsic value by examining related economic, industry, and company-specific factors.</p>',
        'status' => 'public',
        'difficulty' => 'advanced',
        'author_id' => $adminId,
    ],
    [
        'type' => 'learning',
        'title' => 'Basics of Stock Market',
        'slug' => 'basics-of-stock-market',
        'banner_image' => 'https://via.placeholder.com/800x400/17a2b8/ffffff?text=Stock+Market+Basics',
        'content' => '<p>A guide for beginners to understand how the stock market works, common terminologies, and how to get started.</p>',
        'status' => 'public',
        'difficulty' => 'beginner',
        'author_id' => $adminId,
    ],
    [
        'type' => 'learning',
        'title' => 'Diversification Strategies',
        'slug' => 'diversification-strategies',
        'banner_image' => 'https://via.placeholder.com/800x400/6610f2/ffffff?text=Diversification',
        'content' => '<p>Spreading your investments across various assets to reduce risk is known as diversification. Learn effective strategies.</p>',
        'status' => 'public',
        'difficulty' => 'intermediate',
        'author_id' => $adminId,
    ],
    [
        'type' => 'learning',
        'title' => 'Understanding Market Cycles',
        'slug' => 'understanding-market-cycles',
        'banner_image' => 'https://via.placeholder.com/800x400/e83e8c/ffffff?text=Market+Cycles',
        'content' => '<p>Markets move in cycles. Recognizing these patterns can help investors make informed decisions.</p>',
        'status' => 'private',
        'difficulty' => 'advanced',
        'author_id' => $adminId,
    ],
    [
        'type' => 'learning',
        'title' => 'Introduction to Cryptocurrency',
        'slug' => 'introduction-to-cryptocurrency',
        'banner_image' => 'https://via.placeholder.com/800x400/6c757d/ffffff?text=Cryptocurrency',
        'content' => '<p>Explore the world of digital currencies, blockchain technology, and how they are changing the financial landscape.</p>',
        'status' => 'public',
        'difficulty' => 'beginner',
        'author_id' => $adminId,
    ],

    // News Content
    [
        'type' => 'news',
        'title' => 'Pennieshares Announces New Investment Opportunities',
        'slug' => 'pennieshares-new-opportunities',
        'banner_image' => 'https://via.placeholder.com/800x400/007bff/ffffff?text=New+Opportunities',
        'content' => '<p>We are thrilled to announce new and exciting investment opportunities now available on the Pennieshares platform. Explore them today!</p>',
        'status' => 'public',
        'difficulty' => null, // Not applicable for news
        'author_id' => $adminId,
    ],
    [
        'type' => 'news',
        'title' => 'Market Volatility Expected Next Quarter',
        'slug' => 'market-volatility-expected',
        'banner_image' => 'https://via.placeholder.com/800x400/ffc107/333333?text=Market+Volatility',
        'content' => '<p>Analysts predict increased market volatility in the upcoming quarter due to global economic factors. Investors advised to review portfolios.</p>',
        'status' => 'public',
        'difficulty' => null,
        'author_id' => $adminId,
    ],
    [
        'type' => 'news',
        'title' => 'Tech Stocks Surge Amidst Innovation Boom',
        'slug' => 'tech-stocks-surge',
        'banner_image' => 'https://via.placeholder.com/800x400/28a745/ffffff?text=Tech+Stocks',
        'content' => '<p>Leading technology companies have seen a significant rise in stock value, driven by rapid innovation and strong earnings reports.</p>',
        'status' => 'public',
        'difficulty' => null,
        'author_id' => $adminId,
    ],
    [
        'type' => 'news',
        'title' => 'Global Economy Shows Signs of Recovery',
        'slug' => 'global-economy-recovery',
        'banner_image' => 'https://via.placeholder.com/800x400/17a2b8/ffffff?text=Economy+Recovery',
        'content' => '<p>Recent economic indicators suggest a steady recovery in the global economy, boosting investor confidence worldwide.</p>',
        'status' => 'private', // Private news example
        'difficulty' => null,
        'author_id' => $adminId,
    ],
    [
        'type' => 'news',
        'title' => 'Interest Rates Hold Steady, Impact on Bonds',
        'slug' => 'interest-rates-hold',
        'banner_image' => 'https://via.placeholder.com/800x400/dc3545/ffffff?text=Interest+Rates',
        'content' => '<p>Central banks have decided to maintain current interest rates, leading to discussions about the implications for bond markets.</p>',
        'status' => 'public',
        'difficulty' => null,
        'author_id' => $adminId,
    ],
    [
        'type' => 'news',
        'title' => 'New Regulations for Digital Assets Unveiled',
        'slug' => 'new-digital-asset-regulations',
        'banner_image' => 'https://via.placeholder.com/800x400/6f42c1/ffffff?text=Digital+Assets+Regs',
        'content' => '<p>Governments introduce new regulatory frameworks to bring clarity and stability to the rapidly evolving digital asset sector.</p>',
        'status' => 'public',
        'difficulty' => null,
        'author_id' => $adminId,
    ],
    [
        'type' => 'news',
        'title' => 'Energy Sector Outlook: Renewable Investments on the Rise',
        'slug' => 'energy-sector-outlook',
        'banner_image' => 'https://via.placeholder.com/800x400/20c997/ffffff?text=Renewable+Energy',
        'content' => '<p>The energy sector is witnessing a significant shift towards renewable sources, attracting substantial investments.</p>',
        'status' => 'public',
        'difficulty' => null,
        'author_id' => $adminId,
    ],
    [
        'type' => 'news',
        'title' => 'Inflation Concerns Persist Globally',
        'slug' => 'inflation-concerns-persist',
        'banner_image' => 'https://via.placeholder.com/800x400/fd7e14/ffffff?text=Inflation',
        'content' => '<p>Inflationary pressures continue to be a key concern for economies worldwide, impacting consumer purchasing power.</p>',
        'status' => 'public',
        'difficulty' => null,
        'author_id' => $adminId,
    ],
    [
        'type' => 'news',
        'title' => 'Real Estate Market: A Deep Dive into Current Trends',
        'slug' => 'real-estate-market-trends',
        'banner_image' => 'https://via.placeholder.com/800x400/6c757d/ffffff?text=Real+Estate',
        'content' => '<p>An in-depth analysis of the current real estate market, highlighting trends in residential and commercial properties.</p>',
        'status' => 'public',
        'difficulty' => null,
        'author_id' => $adminId,
    ],
    [
        'type' => 'news',
        'title' => 'Pennieshares User Growth Reaches New Highs',
        'slug' => 'pennieshares-user-growth',
        'banner_image' => 'https://via.placeholder.com/800x400/e83e8c/ffffff?text=User+Growth',
        'content' => '<p>Thanks to our loyal community, Pennieshares has achieved record-breaking user growth this quarter. Thank you for your trust!</p>',
        'status' => 'public',
        'difficulty' => null,
        'author_id' => $adminId,
    ],
];

$stmt = $pdo_mysql->prepare("INSERT INTO content (type, title, slug, banner_image, content, status, difficulty, author_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($mockData as $data) {
    try {
        $stmt->execute([
            $data['type'],
            $data['title'],
            $data['slug'],
            $data['banner_image'],
            $data['content'],
            $data['status'],
            $data['difficulty'],
            $data['author_id']
        ]);
        echo "Successfully seeded: " . $data['title'] . " (ID: " . $pdo_mysql->lastInsertId() . ")\n";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Integrity constraint violation (e.g., duplicate slug)
            echo "Skipping duplicate entry: " . $data['title'] . "\n";
        } else {
            echo "Error seeding " . $data['title'] . ": " . $e->getMessage() . "\n";
        }
    }
}

echo "Content seeding complete.\n";

