<?php
require_once 'database.php';

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM roles");
    $count = $stmt->fetchColumn();
    echo "<h3 style = 'color: green;'>Database connected successfully!</h3>";
    echo "<p>Found<strong> record in the table roles </p>";
}catch (\Exception $e){
    echo "<h3 style='color: green;'>Database connected successfully! </h3>";
    echo "<p>Error:". $e->getMessage() . "</p>";
}