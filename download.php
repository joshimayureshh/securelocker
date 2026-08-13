<?php
ob_start(); // MUST be the very first thing after <?php

require_once 'config.php';
requireLogin();

$file_id = intval($_GET['id'] ?? 0);
if (!$file_id) die("No file specified.");

$db = getDB();
$user_id = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT file_name, file_type, file_path, encryption_key, iv FROM files WHERE id = ? AND user_id = ?");
$stmt->execute([$file_id, $user_id]);
$file = $stmt->fetch();

if (!$file || !file_exists($file['file_path'])) {
    die("File not found.");
}

$encrypted = file_get_contents($file['file_path']);

if (!empty($file['encryption_key']) && !empty($file['iv'])) {
    $key = hex2bin($file['encryption_key']);
    $iv = hex2bin($file['iv']);
    $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        die("Decryption failed.");
    }
} else {
    $decrypted = $encrypted;
}

ob_clean();
flush();

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . addslashes($file['file_name']) . '"');
header('Content-Length: ' . strlen($decrypted));
header('Cache-Control: no-cache');

echo $decrypted;
exit;