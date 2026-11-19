<?php
// chống XSS
if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// Chỉ Admin hoặc Doctor mới được vào trang quản lý bài viết
function requireStaff(): void {
    $role = $_SESSION['role_name'] ?? '';
    if (!isset($_SESSION['user_id']) || !in_array($role, ['ADMIN', 'DOCTOR'], true)) {
        header("Location: ../auth/login.php");
        exit();
    }
}

// Admin sửa/xóa được mọi bài. Doctor chỉ sửa/xóa bài do chính mình đăng.
function canManageArticle(array $article): bool {
    $role = $_SESSION['role_name'] ?? '';
    if ($role === 'ADMIN') {
        return true;
    }
    return $role === 'DOCTOR'
        && (int)$article['author_id'] === (int)($_SESSION['user_id'] ?? 0);
}


// "Cách nhận biết đột quỵ" -> "cach-nhan-biet-dot-quy"
function slugify(string $text): string {
    $text = mb_strtolower(trim($text), 'UTF-8');

    $groups = [
        'a' => ['à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ'],
        'e' => ['è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ'],
        'i' => ['ì','í','ị','ỉ','ĩ'],
        'o' => ['ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ'],
        'u' => ['ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ'],
        'y' => ['ỳ','ý','ỵ','ỷ','ỹ'],
        'd' => ['đ'],
    ];
    $map = [];
    foreach ($groups as $plain => $chars) {
        foreach ($chars as $char) {
            $map[$char] = $plain;
        }
    }
    $text = strtr($text, $map);
    $text = preg_replace('/\p{Mn}/u', '', $text);        // bỏ dấu rời (khi gõ dạng tổ hợp)
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);    // ký tự khác -> dấu gạch ngang
    $text = trim($text, '-');
    return rtrim(substr($text, 0, 200), '-');
}

function slugExists(PDO $pdo, string $slug, int $excludeId = 0): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE slug = ? AND article_id <> ?");
    $stmt->execute([$slug, $excludeId]);
    return (int)$stmt->fetchColumn() > 0;
}

// Nếu slug đã có thì thêm -2, -3, ... cho đến khi không trùng
function makeUniqueSlug(PDO $pdo, string $base, int $excludeId = 0): string {
    $slug = $base;
    $i = 2;
    while (slugExists($pdo, $slug, $excludeId)) {
        $slug = $base . '-' . $i;
        $i++;
        if ($i > 200) {
            $slug = $base . '-' . bin2hex(random_bytes(3));
            break;
        }
    }
    return $slug;
}
// Trả về dữ liệu đã làm sạch, thông báo lỗi. Lỗi rỗng nghĩa là hợp lệ
function parseArticleForm(PDO $pdo, array $post, int $excludeId = 0): array {
    $data = [
        'title'         => trim($post['title'] ?? ''),
        'slug'          => trim($post['slug'] ?? ''),
        'category_id'   => trim($post['category_id'] ?? ''),
        'summary'       => trim($post['summary'] ?? ''),
        'content'       => trim($post['content'] ?? ''),
        'thumbnail_url' => trim($post['thumbnail_url'] ?? ''),
    ];

    if ($data['title'] === '' || mb_strlen($data['title']) > 255) {
        return [$data, "Please enter a title (up to 255 characters)."];
    }
    if ($data['content'] === '') {
        return [$data, "Please enter the article content."];
    }
    if (mb_strlen($data['summary']) > 1000) {
        return [$data, "Summary is too long (up to 1000 characters)."];
    }

    // Ảnh bìa: để trống, hoặc link https, hoặc đường dẫn tương đối tới file ảnh
    $thumb = $data['thumbnail_url'];
    if ($thumb !== '') {
        $isHttps    = preg_match('#^https://[^\s]+$#i', $thumb) === 1;
        $isRelative = preg_match('#^[A-Za-z0-9_\-./]+\.(png|jpe?g|gif|webp)$#i', $thumb) === 1
                      && strpos($thumb, '..') === false
                      && strpos($thumb, '//') === false;
        if (strlen($thumb) > 255 || (!$isHttps && !$isRelative)) {
            return [$data, "Thumbnail must be an https:// link or an image file path (png, jpg, gif, webp)."];
        }
    }

    // Danh mục: để trống = không có danh mục; nếu chọn thì phải tồn tại
    if ($data['category_id'] === '') {
        $data['category_id'] = null;
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM article_categories WHERE category_id = ?");
        $stmt->execute([(int)$data['category_id']]);
        if (!ctype_digit($data['category_id']) || (int)$stmt->fetchColumn() === 0) {
            return [$data, "The selected category does not exist."];
        }
        $data['category_id'] = (int)$data['category_id'];
    }

    // Slug: tự nhập thì kiểm tra trùng, để trống thì tự tạo từ tiêu đề
    if ($data['slug'] !== '') {
        $clean = slugify($data['slug']);
        if ($clean === '') {
            return [$data, "Slug is not valid (use letters and numbers)."];
        }
        if (slugExists($pdo, $clean, $excludeId)) {
            return [$data, "This slug is already used by another article."];
        }
        $data['slug'] = $clean;
    } else {
        $base = slugify($data['title']);
        if ($base === '') {
            $base = 'article';
        }
        $data['slug'] = makeUniqueSlug($pdo, $base, $excludeId);
    }

    return [$data, ''];
}

// Lấy số trang hiện tại từ ?page=, luôn nằm trong khoảng 1..tổng số trang
function currentPage(int $totalPages): int {
    $page = (int)($_GET['page'] ?? 1);
    return max(1, min($page, max(1, $totalPages)));
}

// Tạo thanh phân trang: « Prev 1 2 [3] 4 5 Next »
function renderPagination(int $page, int $totalPages, string $baseUrl): string {
    if ($totalPages <= 1) {
        return '';
    }
    $sep  = strpos($baseUrl, '?') === false ? '?' : '&';
    $link = function (int $p) use ($baseUrl, $sep): string {
        return e($baseUrl . $sep . 'page=' . $p);
    };

    $pages = array_unique(array_filter(
        array_merge([1, $totalPages], range($page - 2, $page + 2)),
        function ($p) use ($totalPages) { return $p >= 1 && $p <= $totalPages; }
    ));
    sort($pages);

    $html = '<div class="pagination">';
    if ($page > 1) {
        $html .= '<a href="' . $link($page - 1) . '">&laquo; Prev</a>';
    }
    $previous = 0;
    foreach ($pages as $p) {
        if ($previous && $p - $previous > 1) {
            $html .= '<span class="dots">&hellip;</span>';
        }
        $html .= $p === $page
            ? '<span class="current">' . $p . '</span>'
            : '<a href="' . $link($p) . '">' . $p . '</a>';
        $previous = $p;
    }
    if ($page < $totalPages) {
        $html .= '<a href="' . $link($page + 1) . '">Next &raquo;</a>';
    }
    return $html . '</div>';
}