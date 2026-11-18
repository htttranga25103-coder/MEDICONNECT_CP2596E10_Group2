<?php
session_start();
require_once '../config/database.php';
$articleId = $_GET['id'] ?? 0;
if($_SESSION['REQUEST_METHOD']==='POST' && isset($_POST['submit_comment'])) {
    if(!isset($_SESSION['user_id'])){
        header("Location:../auth/login.php");
        exit();
    }

    $commentText = trim($_POST['comment_text']);
    $userId = $_SESSION['user_id'];

    if(!empty($commentText)){
        $stmtComment = $pdo->prepare("INSERT INTO articles_comment(article_id, user_id, comment_text) VALUES (?, ?, ?)");
        $stmtComment->exec([$articleId, $userId, $commentText]);
        header("Location: detail.php ? id=". $articleId);
        exit();
    }
}

$stmtArt = $pdo-prepare("SELECT a.*, u.full_name as author_name FROM articles a JOIN user u
ON a.author_id = u.user_id WHERE a.article_id = ?");
$stmtArt->exec([$articleId]);
$article = $atmtArt->fetch();
if(!$article){
    die("Article does not exist!");
}

$stmsCom = $pdo->prepage("SELECT ac.*, u.full_name FROM articles_comments ac 
JOIN users u ON ac.user_id = u.user_id WHERE ac.article_id = ? ORDER BY ac.create_at DESC");
$stmsCom->exec([$articlesId]);
$comments = $stmsCom->fetchAll(); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($article['title']);?></html>>MediConnect</title>
    <link rel = "stylesheet" href = "../assets/css/style.css">
</head>
<body>
    
</body>
</html>