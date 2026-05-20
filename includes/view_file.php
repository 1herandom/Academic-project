<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

// Log early
$rootDir = dirname(__DIR__);
$logFile = $rootDir . '/scratch/view_file_debug.log';
if (!is_dir($rootDir . '/scratch')) mkdir($rootDir . '/scratch', 0777, true);
file_put_contents($logFile, date('Y-m-d H:i:s') . " | Script Started | Query: " . ($_SERVER['QUERY_STRING'] ?? '') . "\n", FILE_APPEND);

require_login();

file_put_contents($logFile, date('Y-m-d H:i:s') . " | Auth Passed\n", FILE_APPEND);

$file = $_GET['file'] ?? '';
if (empty($file)) die('Missing file parameter.');

$filePath = $rootDir . '/' . ltrim($file, '/');
$debugMsg = "FullPath: $filePath | ";

if (!file_exists($filePath)) {
    file_put_contents($logFile, $debugMsg . "ERROR: File not exists\n", FILE_APPEND);
    die('File not found.');
}

$storageDir = realpath($rootDir . '/storage/uploads');
$actualPath = realpath($filePath);

if (!$actualPath || strpos($actualPath, $storageDir) !== 0) {
    file_put_contents($logFile, $debugMsg . "ERROR: Unauthorized path | Actual: $actualPath | Storage: $storageDir\n", FILE_APPEND);
    die('Unauthorized path.');
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimes = [
    'pdf'  => 'application/pdf',
    'mp4'  => 'video/mp4',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg'
];
$mimeType = $mimes[$ext] ?? 'application/octet-stream';

file_put_contents($logFile, $debugMsg . "SUCCESS: Serving $mimeType\n", FILE_APPEND);

header("Content-Type: $mimeType");
header("Content-Disposition: inline");
header("Content-Length: " . filesize($filePath));
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

readfile($filePath);
exit;
