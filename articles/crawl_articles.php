<?php
error_reporting(E_ALL);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Kiểm tra quyền (Chỉ ADMIN hoặc DOCTOR mới được phép chạy)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'] ?? '', ['ADMIN', 'DOCTOR'])) {
    header("Location: ../auth/login.php");
    exit();
}

$countAdded = 0;
$errorMsg   = '';
$defaultAuthorId = (int)$_SESSION['user_id'];

// Dữ liệu bài viết mẫu để nạp nhanh vào hệ thống
$sampleArticles = [
    [
        'category_id'   => 1,
        'title'         => '10 Morning Habits for a Healthy Cardiovascular System',
        'slug'          => '10-morning-habits-for-a-healthy-cardiovascular-system',
        'summary'       => 'Maintaining simple morning routines such as drinking warm water and light exercise helps stabilize blood pressure and keep the heart healthy.',
        'content'       => "Morning is the golden time to kick-start your body's metabolism.\n- Hydration: Drink a glass of warm water right after waking up to support circulation.\n- Physical Activity: Dedicate 15-20 minutes to light stretching or brisk walking.\n- Balanced Breakfast: Prioritize whole grains, fruits, and healthy fats while limiting sodium.",
        'thumbnail_url' => 'https://images.unsplash.com/photo-1505751172876-fa1923c5c528?auto=format&fit=crop&w=800&q=80'
    ],
    [
        'category_id'   => 2,
        'title'         => 'Early Recognition of Stroke Symptoms for Timely Emergency Care',
        'slug'          => 'early-recognition-of-stroke-symptoms-for-timely-emergency-care',
        'summary'       => 'Quickly identify symptoms such as facial drooping, arm weakness, and speech difficulty using the FAST rule to save stroke patients.',
        'content'       => "Stroke is a life-threatening medical emergency with high mortality if not treated within the golden window.\n- F (Face): Ask the person to smile and check if one side of the face droops.\n- A (Arms): Ask the person to raise both arms and observe if one arm drifts downward.\n- S (Speech): Listen for slurred or strange speech.\n- T (Time): Call emergency services immediately if any of these signs appear.",
        'thumbnail_url' => 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?auto=format&fit=crop&w=800&q=80'
    ],
    [
        'category_id'   => 3,
        'title'         => 'The Importance of Daily Hydration for Skin and Overall Health',
        'slug'          => 'the-importance-of-daily-hydration-for-skin-and-overall-health',
        'summary'       => 'Water makes up the majority of body weight; adequate daily intake helps flush toxins, maintain skin elasticity, and boost metabolism.',
        'content'       => "The human body requires an average of 1.5 to 2 liters of water daily depending on activity levels.\n- Renal Function: Helps kidneys filter waste products efficiently and prevents kidney stones.\n- Cognitive Focus: Even mild dehydration can impair concentration and cause fatigue.\n- Skin Barrier: Supports natural moisture retention and cellular repair.",
        'thumbnail_url' => 'https://images.unsplash.com/photo-1548839140-29a749e1cf4d?auto=format&fit=crop&w=800&q=80'
    ]
];

try {
    $stmtCheck  = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE slug = ?");
    $stmtCat    = $pdo->prepare("SELECT category_id FROM article_categories WHERE category_id = ?");
    $stmtInsert = $pdo->prepare("
        INSERT INTO articles (category_id, author_id, title, slug, summary, content, thumbnail_url)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($sampleArticles as $item) {
        // Bỏ qua nếu bài viết (slug) đã tồn tại trong database
        $stmtCheck->execute([$item['slug']]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            continue;
        }

        // Kiểm tra category_id có tồn tại không; nếu chưa có thì gán NULL để tránh lỗi khóa ngoại
        $stmtCat->execute([$item['category_id']]);
        $catId = $stmtCat->fetchColumn() ? $item['category_id'] : null;

        $stmtInsert->execute([
            $catId,
            $defaultAuthorId,
            $item['title'],
            $item['slug'],
            $item['summary'],
            $item['content'],
            $item['thumbnail_url']
        ]);
        $countAdded++;
    }
} catch (\PDOException $e) {
    error_log("Crawl articles error: " . $e->getMessage());
    $errorMsg = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crawl Data - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/article_style.css">
</head>
<body>
    <div class="article-container" style="max-width: 640px; margin-top: 60px;">
        <div class="panel-card" style="text-align: center; padding: 32px 24px;">
            <?php if ($errorMsg !== ''): ?>
                <div class="alert-box alert-error" style="text-align: left;">
                    <span><?php echo e($errorMsg); ?></span>
                </div>
            <?php else: ?>
                <h2 class="page-title" style="color: #0f766e; margin-bottom: 10px;">Data Updated Successfully!</h2>
                <p style="color: #475569; font-size: 15px; margin-bottom: 24px;">
                    Successfully added to the database: <strong><?php echo $countAdded; ?></strong> new article(s).
                    <?php if ($countAdded === 0): ?>
                        <span class="text-sub" style="margin-top: 6px;">(All sample articles already exist in the database.)</span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                <a href="news.php" class="btn-mc btn-mc-teal">View Public Articles</a>
                <a href="manage.php" class="btn-mc btn-mc-outline-gray">Manage Articles</a>
                <a href="../index.php" class="btn-mc btn-mc-outline-gray">Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>