<?php
// register_pasien.php (PDO + JWT + Bootstrap 5)

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$error = '';

// pastikan sudah ada $pdo dan $JWT_SECRET di config.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $alamat   = trim($_POST['alamat'] ?? '');
    $no_hp    = trim($_POST['no_hp'] ?? '');

    if ($nama === '' || $email === '' || $password === '') {
        $error = 'Nama, email, dan password wajib diisi.';
    } else {
        try {
            // cek email sudah ada atau belum
            $cek = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $cek->execute([':email' => $email]);
            if ($cek->fetch()) {
                $error = 'Email sudah terdaftar, silakan pakai email lain.';
            } else {
                $pdo->beginTransaction();

                // simpan ke users (role pasien)
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $insUser = $pdo->prepare("
                    INSERT INTO users (nama, email, password, role)
                    VALUES (:nama, :email, :password, 'pasien')
                ");
                $insUser->execute([
                    ':nama'     => $nama,
                    ':email'    => $email,
                    ':password' => $hash,
                ]);
                $user_id = (int)$pdo->lastInsertId();

                // simpan ke pasien
                $insPasien = $pdo->prepare("
                    INSERT INTO pasien (user_id, nama, alamat, no_hp)
                    VALUES (:user_id, :nama, :alamat, :no_hp)
                ");
                $insPasien->execute([
                    ':user_id' => $user_id,
                    ':nama'    => $nama,
                    ':alamat'  => $alamat,
                    ':no_hp'   => $no_hp,
                ]);

                $pdo->commit();

                // === buat JWT dan simpan di cookie ===
                $payload = [
                    'iss'  => 'clinicflow',
                    'iat'  => time(),
                    'exp'  => time() + 60 * 60 * 4, // 4 jam
                    'data' => [
                        'user_id' => $user_id,
                        'role'    => 'pasien',
                        'nama'    => $nama,
                    ],
                ];
                $jwt = JWT::encode($payload, $JWT_SECRET, 'HS256');

                setcookie('token', $jwt, time() + 60 * 60 * 4, '/', '', false, true);

                header('Location: dashboard_pasien.php');
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Terjadi kesalahan saat registrasi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Register Pasien - ClinicFlow</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body {
            background: radial-gradient(circle at top, #38bdf8, #0f172a);
            min-height: 100vh;
        }
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-card {
            max-width: 420px;
            width: 100%;
            border-radius: 1rem;
            border: none;
            box-shadow: 0 .7rem 2rem rgba(15,23,42,.45);
            overflow: hidden;
        }
        .auth-header {
            padding: 1.3rem 1.6rem;
            background: linear-gradient(135deg,#0ea5e9,#6366f1);
            color: #eff6ff;
        }
        .auth-body {
            padding: 1.4rem 1.6rem 1.6rem;
            background: #f9fafb;
        }
        .form-control, .form-control:focus {
            border-radius: .7rem;
        }
        .btn-auth {
            border-radius: .7rem;
        }
    </style>
</head>
<body>
<div class="auth-wrapper">
    <div class="card auth-card">
        <div class="auth-header">
            <h4 class="mb-1">
                <i class="bi bi-heart-pulse-fill me-1"></i>ClinicFlow
            </h4>
            <p class="mb-0 small">Daftar sebagai pasien untuk mulai booking jadwal dokter.</p>
        </div>
        <div class="auth-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <div class="mb-3">
                    <label class="form-label small">Nama lengkap</label>
                    <input type="text" name="nama" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Alamat</label>
                    <input type="text" name="alamat" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label small">No HP</label>
                    <input type="text" name="no_hp" class="form-control">
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-auth mb-2">
                    Daftar Pasien
                </button>
                <div class="text-center small">
                    Sudah punya akun?
                    <a href="index.php">Login</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>