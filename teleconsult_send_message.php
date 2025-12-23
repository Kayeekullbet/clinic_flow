<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');
session_start();

$JWT_SECRET = $JWT_SECRET ?? 'ganti_dengan_kunci_rahasia_yang_panjang';

$role    = null;
$user_id = null;

if (!empty($_COOKIE['token'])) {
    try {
        $decoded = JWT::decode($_COOKIE['token'], new Key($JWT_SECRET, 'HS256'));
        $data    = (array)$decoded->data;
        $role    = $data['role']    ?? null;
        $user_id = $data['user_id'] ?? null;
    } catch (Exception $e) {
        $role    = null;
        $user_id = null;
    }
}

if (!$role || !$user_id) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
$message        = trim($_POST['message'] ?? '');

if ($appointment_id <= 0 || $message === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO teleconsult_messages (appointment_id, sender_id, message)
    VALUES (:appointment_id, :sender_id, :message)
");
$stmt->execute([
    ':appointment_id' => $appointment_id,
    ':sender_id'      => $user_id,
    ':message'        => $message,
]);

echo json_encode([
    'status'     => 'ok',
    'id'         => $pdo->lastInsertId(),
    'created_at' => date('Y-m-d H:i:s'),
]);