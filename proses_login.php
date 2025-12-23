<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

session_start();

$JWT_SECRET = $JWT_SECRET ?? 'ganti_dengan_kunci_rahasia_yang_panjang';

// ============= AMBIL INPUT LOGIN =============
$email    = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// validasi sederhana
if ($email === '' || $password === '') {
    header('Location: index.php?error=empty');
    exit;
}

// ============= CARI USER BERDASARKAN EMAIL =============
$stmt = $pdo->prepare("
    SELECT id, nama, email, password, role
    FROM users
    WHERE email = :email
    LIMIT 1
");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// kalau user tidak ada atau password salah
if (!$user || !password_verify($password, $user['password'])) {
    header('Location: index.php?error=invalid');
    exit;
}

// ============= BERSIHKAN SESSION & COOKIE LAMA =============
session_regenerate_id(true);

// hapus token lama kalau masih ada
if (!empty($_COOKIE['token'])) {
    setcookie('token', '', time() - 3600, '/');
}

// ============= BUAT PAYLOAD JWT BARU =============
$payload = [
    'iss'  => 'clinicflow-app',
    'aud'  => 'clinicflow-user',
    'iat'  => time(),
    'exp'  => time() + 60 * 60 * 4, // 4 jam
    'data' => [
        'user_id' => $user['id'],
        'email'   => $user['email'],
        'nama'    => $user['nama'],
        'role'    => $user['role'],   // admin / dokter / pasien
    ],
];

$token = JWT::encode($payload, $JWT_SECRET, 'HS256');

// simpan token di cookie baru
setcookie('token', $token, [
    'expires'  => time() + 60 * 60 * 4,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);

// ============= REDIRECT SESUAI ROLE =============
if ($user['role'] === 'admin') {
    header('Location: dashboard_admin.php');
} elseif ($user['role'] === 'dokter') {
    header('Location: dashboard_dokter.php');
} else {
    header('Location: dashboard_pasien.php');
}
exit;