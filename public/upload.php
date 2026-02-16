<?php

declare(strict_types=1);

/**
 * public/upload.php — Handle drag-and-drop YAML uploads
 *
 * Returns JSON: { workflow: "<token>" } on success
 *               { error: "..." }        on failure
 *
 * The token is used as ?workflow=<token> in index.php and sse.php.
 * sse.php resolves the token → actual file path via a .map file.
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$fileError = $_FILES['workflow_file']['error'] ?? UPLOAD_ERR_NO_FILE;
if ($fileError !== UPLOAD_ERR_OK) {
    $msgs = [
        UPLOAD_ERR_INI_SIZE   => 'File too large (server limit).',
        UPLOAD_ERR_FORM_SIZE  => 'File too large (form limit).',
        UPLOAD_ERR_PARTIAL    => 'Upload was interrupted.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temp directory missing.',
        UPLOAD_ERR_CANT_WRITE => 'Cannot write to server disk.',
    ];
    http_response_code(400);
    echo json_encode(['error' => $msgs[$fileError] ?? "Upload error ($fileError)"]);
    exit;
}

$file = $_FILES['workflow_file'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, ['yaml', 'yml'], strict: true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Only .yaml or .yml files are accepted.']);
    exit;
}

if ($file['size'] > 512 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large. Maximum 512 KB.']);
    exit;
}

// Ensure upload dir exists
$uploadDir = sys_get_temp_dir() . '/flux-uploads';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0700, recursive: true)) {
    http_response_code(500);
    echo json_encode(['error' => 'Cannot create upload directory.']);
    exit;
}

// Save the file with a sanitized name
$baseName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
$savedFile = $uploadDir . '/' . $baseName . '-' . uniqid() . '.yaml';

if (!move_uploaded_file($file['tmp_name'], $savedFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file on server.']);
    exit;
}

// Write a token → path map so sse.php can find the file
// Token: hex-only so it passes the sse.php sanitization regex
$token   = bin2hex(random_bytes(16));
$mapFile = $uploadDir . '/' . $token . '.map';
file_put_contents($mapFile, $savedFile);

// Prune old uploads (> 2 hours) to avoid disk bloat
foreach (glob($uploadDir . '/*.map') as $m) {
    if (filemtime($m) < time() - 7200) {
        $old = trim((string) @file_get_contents($m));
        @unlink($old);
        @unlink($m);
    }
}

echo json_encode(['workflow' => $token, 'original' => $file['name']]);
