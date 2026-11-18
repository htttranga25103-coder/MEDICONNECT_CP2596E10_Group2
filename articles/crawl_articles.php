<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/database.php'; 

// dong lai test kiểm tra quyền (Chỉ Admin hoặc Doctor mới được phép crawl dữ liệu)
// if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'] ?? '', ['ADMIN', 'DOCTOR'])) {
//     header("Location: ../auth/login.php");
//     exit();
// } 

$defaultAuthorId = 1; 

$url = "https://suckhoedoisong.vn/";
$countAdded = 0;
$defaultCategoryId = null; //dung null de test
$defaultAuthorId = $_SESSION['user_id'];
//test bai viet
if ($countAdded == 0) {
    $sampleArticles = [
        [
            'title' => '10 thói quen buổi sáng giúp bảo vệ hệ tim mạch khỏe mạnh',
            'slug' => '10-thoi-quen-buoi-sang-giup-bao-ve-he-tim-mach',
            'summary' => 'Duy trì những thói quen đơn giản mỗi sáng như uống nước ấm, tập thể dục nhẹ nhàng giúp huyết áp ổn định và tim mạch khỏe mạnh.',
            'content' => 'Buổi sáng là thời điểm vàng để khởi động cơ thể...Các chuyên gia y tế khuyến cáo nên dành 15-20 phút vận động nhẹ nhàng kết hợp chế độ ăn uống đầy đủ dinh dưỡng.'
        ],
        [
            'title' => 'Cách nhận biết sớm các dấu hiệu đột quỵ để cấp cứu kịp thời',
            'slug' => 'cach-nhan-biet-som-cac-dau-hieu-dot-quu',
            'summary' => 'Nhận biết nhanh các triệu chứng như méo miệng, liệt yếu tay chân, nói khó theo quy tắc FAST để cứu sống bệnh nhân đột quỵ.',
            'content' => 'Đột quỵ là căn bệnh nguy hiểm có tỷ lệ tử vong cao nếu không được cấp cứu trong "khung giờ vàng". Hãy ghi nhớ quy tắc FAST: Face (Mặt), Arm (Tay), Speech (Lời nói), Time (Thời gian cấp cứu).'
        ],
        [
            'title' => 'Tầm quan trọng của việc uống đủ nước đối với làn da và sức khỏe',
            'slug' => 'tam-quan-trong-cua-viec-uong-du-nuoc',
            'summary' => 'Nước chiếm phần lớn trọng lượng cơ thể, việc bổ sung đủ nước mỗi ngày giúp thanh lọc độc tố, làm đẹp da và tăng cường trao đổi chất.',
            'content' => 'Cơ thể chúng ta cần trung bình từ 1.5 đến 2 lít nước mỗi ngày...Thiếu nước có thể dẫn đến tình trạng mệt mỏi, khô da, giảm tập trung và ảnh hưởng xấu đến thận.'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Crawl Data - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/article_style.css">
</head>
<body>
    <div class="main-container" style="max-width: 600px; margin: 50px auto; text-align: center;">
        <h2 style="color: #00b074;">Data updated successfully!</h2>
        <p>Successfully added to th database: <strong><?php echo $countAdded; ?></strong> bài viết.</p>
        <div style="margin-top: 20px;">
            <a href="news.php" class="btn-auth" style="background: #00b074; text-decoration: none;">Xem danh sách tin</a>
            <a href="../index.php" class="btn-auth" style="background: #6c757d; text-decoration: none; margin-left: 10px;">Về trang chủ</a>
        </div>
    </div>
</body>
</html>