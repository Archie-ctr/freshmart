<?php
/**
 * AJAX image uploader for product images.
 * Returns JSON: { ok: true, url: "/uploads/products/xxx.webp" }
 */
require_once dirname(__DIR__) . '/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

// Admin only
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    echo json_encode(['ok' => false, 'error' => 'No file received']);
    exit;
}

$file  = $_FILES['image'];
$error = $file['error'];

if ($error !== UPLOAD_ERR_OK) {
    $msgs = [
        UPLOAD_ERR_INI_SIZE   => 'File too large (server limit)',
        UPLOAD_ERR_FORM_SIZE  => 'File too large (form limit)',
        UPLOAD_ERR_PARTIAL    => 'Upload was only partial',
        UPLOAD_ERR_NO_FILE    => 'No file selected',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by extension',
    ];
    echo json_encode(['ok' => false, 'error' => $msgs[$error] ?? 'Upload error ' . $error]);
    exit;
}

// Validate MIME type — only allow real images
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
$finfo   = new finfo(FILEINFO_MIME_TYPE);
$mime    = $finfo->file($file['tmp_name']);

if (!in_array($mime, $allowed)) {
    echo json_encode(['ok' => false, 'error' => 'Only JPG, PNG, GIF, WebP images are allowed (got: ' . $mime . ')']);
    exit;
}

// Size cap: 5 MB
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'Image must be under 5 MB']);
    exit;
}

// Build destination path
$uploadDir = dirname(__DIR__) . '/uploads/products/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Unique filename — keep original extension
$ext      = match($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    'image/avif' => 'avif',
    default      => 'jpg',
};
$filename = uniqid('product_', true) . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['ok' => false, 'error' => 'Failed to save file. Check folder permissions.']);
    exit;
}

// Return the public URL
$url = BASE_URL . '/uploads/products/' . $filename;
echo json_encode(['ok' => true, 'url' => $url, 'filename' => $filename]);
