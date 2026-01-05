
<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// kunci rahasia JWT (pastikan sama dengan di config.php)
$JWT_SECRET = $JWT_SECRET ?? 'ganti_dengan_kunci_rahasia_yang_panjang';

// cek token JWT dari cookie (kalau sudah login)
$role = null;

if (!empty($_COOKIE['token'])) {
    try {
        $decoded = JWT::decode($_COOKIE['token'], new Key($JWT_SECRET, 'HS256'));
        $data    = (array) $decoded->data;
        $role    = $data['role'] ?? null;
    } catch (Exception $e) {
        // token invalid / expired → anggap belum login
        $role = null;
    }
}

// kalau sudah login, arahkan ke dashboard sesuai role
if ($role === 'admin') {
    header('Location: dashboard_admin.php');
    exit;
} elseif ($role === 'dokter') {
    header('Location: dashboard_dokter.php');
    exit;
} elseif ($role === 'pasien') {
    header('Location: dashboard_pasien.php');
    exit;
}

// tampung pesan error dari proses_login.php (pakai query string)
$error = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'empty') {
        $error = 'Email dan password wajib diisi.';
    } elseif ($_GET['error'] === 'invalid') {
        $error = 'Email atau password salah.';
    }
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <title>Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #1a1a2e;
            padding: env(safe-area-inset-top, 20px) env(safe-area-inset-right, 15px) env(safe-area-inset-bottom, 15px) env(safe-area-inset-left, 15px);
            color: #fff;
            overflow-x: hidden;
            position: relative;
        }

        /* Main Auth Container - Glowing Border */
        .auth-wrapper {
            position: relative;
            width: 100%;
            max-width: 800px;
            min-height: 520px;
            display: flex;
            border: 2px solid #00d4ff;
            box-shadow: 0 0 25px rgba(0, 212, 255, 0.5);
            overflow: hidden;
            margin: 20px auto;
            background: #1a1a2e;
        }

        /* Left: Login Form - Dark Panel */
        .credentials-panel {
            width: 50%;
            padding: 40px;
            display: flex;
            flex-direction: column;
            background: #121222;
            z-index: 2;
        }

        .credentials-panel h2 {
            font-size: 30px;
            text-align: left;
            margin-bottom: 16px;
            font-weight: 600;
            color: #fff;
        }

        .credentials-panel p {
            text-align: left;
            font-size: 14px;
            margin-bottom: 24px;
            color: #aaa;
        }

        .field-wrapper {
            position: relative;
            width: 100%;
            margin-top: 20px;
        }

        .field-wrapper input {
            width: 100%;
            padding: 12px 0;
            background: transparent;
            border: none;
            border-bottom: 2px solid #444;
            outline: none;
            font-size: 16px;
            color: #fff;
            font-weight: 500;
        }

        .field-wrapper input:focus,
        .field-wrapper input:not(:placeholder-shown) {
            border-bottom-color: #00d4ff;
        }

        .field-wrapper label {
            position: absolute;
            top: 12px;
            left: 0;
            font-size: 16px;
            color: #bbb;
            pointer-events: none;
            transition: 0.3s ease;
        }

        .field-wrapper input:focus ~ label,
        .field-wrapper input:not(:placeholder-shown) ~ label {
            top: -10px;
            font-size: 13px;
            color: #00d4ff;
        }

        .field-wrapper .toggle-password {
            position: absolute;
            right: 0;
            top: 12px;
            background: transparent;
            border: none;
            color: #bbb;
            cursor: pointer;
            font-size: 18px;
        }

        .field-wrapper input:focus ~ .toggle-password,
        .field-wrapper input:not(:placeholder-shown) ~ .toggle-password {
            color: #00d4ff;
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            font-size: 13px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        .remember-me input {
            accent-color: #00d4ff;
        }

        .forgot-password {
            color: #00d4ff;
            text-decoration: none;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

       .submit-button {
            position: relative;
            width: 100%;
            height: 45px;
            background: transparent;
            border-radius: 40px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            border: 2px solid #00d4ff;
            overflow: hidden;
            margin-top: 24px;
            color: #fff;
        }

        .submit-button::before {
            content: "";
            position: absolute;
            height: 300%;
            width: 100%;
            background: linear-gradient(#1a1a2e, #00d4ff, #1a1a2e, #00d4ff);
            top: -100%;
            left: 0;
            z-index: -1;
            transition: 0.5s;
        }

        .submit-button:hover::before {
            top: 0;
        }
        .switch-link {
            text-align: left;
            margin-top: 20px;
            font-size: 14px;
        }

        .switch-link a {
            color: #00d4ff;
            text-decoration: none;
            font-weight: 600;
        }

        .switch-link a:hover {
            text-decoration: underline;
        }

        /* Right: Welcome Panel - Blue Gradient */
        .welcome-section {
            width: 50%;
            padding: 40px 50px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: linear-gradient(135deg, #0d6efd, #20c997); /* Blue to Teal */
            color: #fff;
        }

        .welcome-section h2 {
            text-transform: uppercase;
            font-size: 28px;
            line-height: 1.3;
            margin-bottom: 14px;
            font-weight: 700;
        }

        .welcome-section p {
            font-size: 15px;
            opacity: 0.95;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .badge-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .badge-pill-soft {
            background: rgba(255, 255, 255, 0.15);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .quote {
            font-style: italic;
            font-size: 14px;
            opacity: 0.9;
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Alert */
        .alert {
            background: rgba(255, 50, 50, 0.2);
            border: 1px solid #ff3232;
            color: #ff9999;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .auth-wrapper {
                flex-direction: column;
                min-height: auto;
                max-width: 100%;
                margin: 20px auto;
            }

            .credentials-panel,
            .welcome-section {
                width: 100%;
                padding: 30px 25px;
            }

            .welcome-section {
                padding: 30px 25px 80px 25px;
                background: linear-gradient(135deg, #0d6efd, #20c997);
            }

            .welcome-section h2,
            .welcome-section p,
            .badge-list,
            .quote {
                text-align: center;
            }

            .welcome-section h2 {
                font-size: 24px;
            }

            .badge-list {
                justify-content: center;
            }

            .credentials-panel h2,
            .credentials-panel p,
            .switch-link {
                text-align: left;
            }
        }

        @media (max-width: 480px) {
            .credentials-panel,
            .welcome-section {
                padding: 24px 20px;
            }

            .credentials-panel h2,
            .welcome-section h2 {
                font-size: 22px;
            }

            .field-wrapper input,
            .field-wrapper label {
                font-size: 14px;
            }

            .field-wrapper label {
                top: 10px;
            }

            .submit-button {
                height: 42px;
                font-size: 15px;
            }

            .switch-link {
                font-size: 13px;
            }

            .auth-wrapper {
                margin: 15px auto;
            }
        }

        .footer {
            margin-top: auto;
            text-align: center;
            padding: 15px;
            font-size: 13px;
            color: #ccc;
        }

        .footer a {
            color: #00d4ff;
            text-decoration: none;
            font-weight: 600;
        }

        .footer a:hover {
            text-decoration: underline;
            color: #00b8d4;
        }
    </style>
</head>
<body>

<!-- Main Auth Container -->
<div class="auth-wrapper">
    <!-- Left: Login Form -->
    <div class="credentials-panel">
        <h2>Login</h2>
        <p>Masuk untuk mengelola layanan Anda.</p>

        <?php if (!empty($error)) : ?>
            <div class="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="proses_login.php">
            <div class="field-wrapper">
                <input type="text" name="username" id="email" required autofocus placeholder=" ">
                <label for="email">Email</label>
            </div>

            <div class="field-wrapper">
                <input type="password" name="password" id="password" required placeholder=" ">
                <label for="password">Password</label>
                <button type="button" class="toggle-password" id="togglePassword">👁</button>
            </div>

            <div class="form-footer">
                <label class="remember-me" for="rememberMe">
                    <input type="checkbox" id="rememberMe" name="remember">
                    Ingat saya
                </label>
                <a href="forgot_password.php" class="forgot-password">Lupa password?</a>
            </div>

            <button type="submit" class="submit-button">Login</button>
        </form>

        <div class="switch-link">
            Belum punya akun? <a href="register.php">Daftar</a>
        </div>
    </div>

    <!-- Right: Welcome / Info Panel -->
    <div class="welcome-section">
        <div>
            <h2>Sistem Manajemen Modern</h2>
            <p>Kelola jadwal, booking, dan antrian secara real time dalam satu sistem terintegrasi.</p>

            <div class="badge-list">
                <span class="badge-pill-soft">Jadwal Terupdate</span>
                <span class="badge-pill-soft">Booking Online</span>
                <span class="badge-pill-soft">Manajemen Antrian</span>
            </div>
        </div>
        <div class="quote">
            “Sistem yang mengalir” – pastikan data selalu aman dan terkelola dengan baik.
        </div>
    </div>
</div>

<script>
// Password toggle
document.getElementById('togglePassword').addEventListener('click', function () {
    const pwdInput = document.getElementById('password');
    const type = pwdInput.type === 'password' ? 'text' : 'password';
    pwdInput.type = type;
    this.textContent = type === 'password' ? '👁' : '🙈';
});
</script>
</body>
</html>

<?php include 'footer.php'; ?>