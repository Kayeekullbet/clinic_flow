<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$JWT_SECRET = $JWT_SECRET ?? 'ganti_dengan_kunci_rahasia_yang_panjang';

$error   = '';
$message = '';
$userId  = null;

// ambil token dari URL
$token = $_GET['token'] ?? '';

if (!$token) {
    $error = 'Token tidak ditemukan.';
} else {
    try {
        $decoded = JWT::decode($token, new Key($JWT_SECRET, 'HS256'));
        $data    = (array) $decoded->data;

        // cek token di tabel reset_passwords dan belum digunakan
        $stmt = $pdo->prepare("
            SELECT id, used, expired_at 
            FROM reset_passwords 
            WHERE token = :token LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || $row['used']) {
            $error = 'Token reset sudah tidak berlaku.';
        } elseif (strtotime($row['expired_at']) < time()) {
            $error = 'Token reset sudah kedaluwarsa.';
        } else {
            $userId = $data['user_id'] ?? null;
        }
    } catch (Exception $e) {
        $error = 'Token tidak valid.';
    }
}

// kalau form submit dan tidak ada error token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId) {
    $password        = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($password === '' || $passwordConfirm === '') {
        $error = 'Password wajib diisi.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Konfirmasi password tidak sama.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } else {
        // update password user (gunakan password_hash)
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $pdo->beginTransaction();

        // update tabel users
        $stmtUser = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
        $stmtUser->execute([
            ':password' => $hash,
            ':id'       => $userId,
        ]);

        // tandai token sebagai used
        $stmtUsed = $pdo->prepare("UPDATE reset_passwords SET used = 1 WHERE token = :token");
        $stmtUsed->execute([':token' => $token]);

        $pdo->commit();

        $message = 'Password berhasil direset. Silakan login dengan password baru.';
        $userId  = null; // supaya form tidak muncul lagi
    }
}

include 'header.php';
?>

<style>
    body {
        min-height: 100vh;
        background: radial-gradient(circle at top left, #0d6efd 0, #20c997 35%, #f8f9fa 80%);
    }
    .login-wrapper {
        min-height: calc(100vh - 80px);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .login-card {
        border: none;
        border-radius: 1.2rem;
        overflow: hidden;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(18px);
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.35);
    }
    .login-form-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(25px);
    }
</style>

<div class="login-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card login-card">
                    <div class="login-form-card p-4 p-md-5">
                        <h4 class="mb-3 text-center">Reset Password</h4>

                        <?php if ($error): ?>
                            <div class="alert alert-danger py-2">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($message): ?>
                            <div class="alert alert-success py-2">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                            <div class="text-center mt-2">
                                <a href="index.php">Kembali ke Login</a>
                            </div>
                        <?php elseif ($userId): ?>
                            <form method="post">
                                <div class="mb-3">
                                    <label class="form-label">Password Baru</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Ulangi Password Baru</label>
                                    <input type="password" name="password_confirm" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 mb-2">
                                    Simpan Password Baru
                                </button>
                                <div class="text-center mt-2">
                                    <a href="index.php">Kembali ke Login</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="text-center mt-2">
                                <a href="forgot_password.php">Kembali ke Lupa Password</a>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>