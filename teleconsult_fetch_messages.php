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
    echo json_encode([]);
    exit;
}

$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;

if ($appointment_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, appointment_id, sender_id, message, created_at
    FROM teleconsult_messages
    WHERE appointment_id = :appointment_id
    ORDER BY created_at ASC, id ASC
");
$stmt->execute([':appointment_id' => $appointment_id]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));