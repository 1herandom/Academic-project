<?php
require_once __DIR__ . '/includes/auth.php';
require_role('Academic Admin');
require_once __DIR__ . '/config.php';

try {
    $pdo = db();
    echo "Database connection successful.<br><br>";

    // Check if materials table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'materials'");
    $tableExists = $stmt->fetchColumn();

    if ($tableExists) {
        echo "<strong>✅ Materials table exists.</strong><br><br>";

        // Check table structure
        $stmt = $pdo->query("DESCRIBE materials");
        echo "<strong>Table structure:</strong><br><pre>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . $row['Null'] . ' - ' . $row['Key'] . "\n";
        }
        echo "</pre><br>";

        // Check counts
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM courses");
        $courses = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Total courses: " . $courses['count'] . "<br>";

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM teachers");
        $teachers = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Total teachers: " . $teachers['count'] . "<br>";

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM materials");
        $materials = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Total materials: " . $materials['count'] . "<br><br>";

        if ($courses['count'] > 0 && $teachers['count'] > 0) {
            echo "<strong>✅ Database appears to be properly set up.</strong><br>";
        } else {
            echo "<strong>⚠️ Database may need seeding.</strong><br>";
        }

    } else {
        echo "<strong>❌ Materials table does NOT exist!</strong><br>";
        echo "You need to run the database seeding script.<br><br>";
        echo "<a href='" . APP_BASE_URL . "/seed.php' target='_blank'>Click here to seed the database</a><br>";
    }

} catch (Exception $e) {
    echo "<strong>❌ Database error:</strong> " . $e->getMessage() . "<br>";
}
?>