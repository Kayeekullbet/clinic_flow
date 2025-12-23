<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// kunci rahasia JWT (sama dengan yang dipakai untuk login)
$JWT_SECRET = $JWT_SECRET ?? 'ganti_dengan_kunci_rahasia_yang_panjang';

// koneksi PDO dari config.php → variabelnya misal $pdo
// pastikan di config.php sudah ada:
// $pdo = new PDO($dsn, $user, $pass, [...]);

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Email wajib diisi.';
    } else {
        // cek apakah email ada di tabel user
        $stmt = $pdo->prepare('SELECT id, nama, role FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // demi keamanan: jangan sebutkan "email tidak terdaftar"
            $message = 'Jika email terdaftar, link reset akan dikirim.';
        } else {
            // buat JWT khusus reset password dengan expiry (misal 30 menit)
            $payload = [
                'iss'  => 'clinicflow-app',
                'aud'  => 'clinicflow-user',
                'iat'  => time(),
                'exp'  => time() + 1800, // 30 menit
                'data' => [
                    'user_id' => $user['id'],
                    'email'   => $email,
                    'role'    => $user['role'],
                    'action'  => 'reset_password'
                ]
            ];

            $token = JWT::encode($payload, $JWT_SECRET, 'HS256');

            // simpan token (opsional tapi bagus) ke tabel reset_passwords
            // supaya bisa diblacklist kalau sudah dipakai
            $stmtInsert = $pdo->prepare("
                INSERT INTO reset_passwords (user_id, token, expired_at, used)
                VALUES (:user_id, :token, :expired_at, 0)
            ");
            $stmtInsert->execute([
                ':user_id'   => $user['id'],
                ':token'     => $token,
                ':expired_at'=> date('Y-m-d H:i:s', $payload['exp'])
            ]);

            // link reset
            $resetLink = 'http://localhost/clinicflow/reset_password.php?token=' . urlencode($token);

            // kirim email pakai PHPMailer (konfigurasi sama seperti yang sudah berhasil)
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'clinicflow.test@gmail.com';
                $mail->Password   = 'mqjkyrttbgjclpgu';
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;
                $mail->SMTPAutoTLS = true;
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ],
                ];

                $mail->setFrom('clinicflow.test@gmail.com', 'ClinicFlow');
                $mail->addAddress($email, $user['nama'] ?? '');

                $mail->isHTML(true);
                $mail->Subject = 'Reset Password ClinicFlow';
                $mail->Body    = '
                    Hai ' . htmlspecialchars($user['nama'] ?? 'Pengguna') . ',<br><br>
                    Kami menerima permintaan untuk reset password akun ClinicFlow Anda.<br>
                    Silakan klik link berikut untuk membuat password baru:<br><br>
                    <a href="' . $resetLink . '">Reset Password</a><br><br>
                    Link ini hanya berlaku selama 30 menit.<br><br>
                    Jika Anda tidak merasa meminta reset password, abaikan email ini.
                ';

                $mail->send();
                $message = 'Jika email terdaftar, link reset sudah dikirim. Silakan cek inbox / spam.';
            } catch (Exception $e) {
                $error = 'Gagal mengirim email reset: ' . $mail->ErrorInfo;
            }
        }
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
                        <h4 class="mb-3 text-center">Lupa Password</h4>
                        <p class="text-muted text-center mb-4">
                            Masukkan email yang terdaftar, kami akan mengirim link untuk reset password.
                        </p>

                        <?php if ($error) : ?>
                            <div class="alert alert-danger py-2">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($message) : ?>
                            <div class="alert alert-success py-2">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    required
                                    autofocus
                                    placeholder="contoh@clinicflow.com"
                                >
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                Kirim Link Reset
                            </button>

                            <div class="text-center mt-2">
                                <a href="index.php">Kembali ke Login</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>