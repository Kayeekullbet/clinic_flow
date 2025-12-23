<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;


session_start();

$JWT_SECRET = $JWT_SECRET ?? 'ganti_dengan_kunci_rahasia_yang_panjang';

// ================= CEK TOKEN & ROLE =================
$role       = null;   // 'pasien' atau 'dokter'
$user_id    = null;
$nama_login = '';

if (!empty($_COOKIE['token'])) {
    try {
        $decoded = JWT::decode($_COOKIE['token'], new Key($JWT_SECRET, 'HS256'));
        $data    = (array)$decoded->data;

        $role       = $data['role']    ?? null;
        $user_id    = $data['user_id'] ?? null;
        $nama_login = $data['nama']    ?? '';
    } catch (Exception $e) {
        $role    = null;
        $user_id = null;
    }
}

// kalau belum login, tendang ke halaman depan
if (!$role || !$user_id) {
    header('Location: index.php');
    exit;
}

// ================= AMBIL DATA APPOINTMENT =================
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($appointment_id <= 0) {
    echo "ID ruang tidak valid.";
    exit;
}

$stmt = $pdo->prepare("
    SELECT a.*,
           d.nama AS nama_dokter,
           p.nama AS nama_pasien
    FROM teleconsult_appointments a
    JOIN users d ON d.id = a.dokter_id
    JOIN users p ON p.id = a.pasien_id
    WHERE a.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $appointment_id]);
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    echo "Ruang tidak ditemukan.";
    exit;
}

// ================= CEK HAK AKSES RUANG =================
if ($role === 'dokter' && (int)$appointment['dokter_id'] !== (int)$user_id) {
    echo "Anda tidak berhak membuka ruang ini.";
    exit;
}

if ($role === 'pasien' && (int)$appointment['pasien_id'] !== (int)$user_id) {
    echo "Anda tidak berhak membuka ruang ini.";
    exit;
}

$backUrl = ($role === 'pasien') ? 'dashboard_pasien.php' : 'dashboard_dokter.php';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Ruang Telekonsultasi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        :root {
            --bg: #0f172a;
            --bg-soft: #111827;
            --accent: #38bdf8;
            --accent-soft: rgba(56,189,248,0.15);
            --danger: #f97373;
            --text: #e5e7eb;
            --muted: #9ca3af;
            --border: #1f2937;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
            background: radial-gradient(circle at top, #1f2937, #020617);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            justify-content: center;
            padding: 16px;
        }

        .shell {
            max-width: 1200px;
            width: 100%;
            background: linear-gradient(135deg, rgba(15,23,42,0.96), rgba(15,23,42,0.98));
            border-radius: 18px;
            border: 1px solid rgba(148,163,184,0.25);
            box-shadow: 0 24px 60px rgba(15,23,42,0.8), 0 0 0 1px rgba(15,23,42,0.9);
            display: flex;
            overflow: hidden;
            min-width: 0;
        }

        .sidebar {
            width: 280px;
            border-right: 1px solid var(--border);
            background: radial-gradient(circle at top, #020617, #020617);
            padding: 20px 18px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            flex-shrink: 0;
        }

        .badge-role {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
        }

        .badge-role span.icon {
            width: 18px;
            height: 18px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(56,189,248,0.15);
            font-size: 11px;
        }

        .room-title { font-size: 18px; font-weight: 600; letter-spacing: .02em; margin-bottom: 4px; }
        .room-subtitle { font-size: 12px; color: var(--muted); }

        .info-card {
            border-radius: 14px;
            border: 1px solid var(--border);
            background: linear-gradient(135deg, rgba(15,23,42,0.9), rgba(15,23,42,0.96));
            padding: 14px 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .info-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
        }

        .info-value { font-size: 14px; font-weight: 500; }

        .pill {
            font-size: 11px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 9px;
            border-radius: 999px;
            border: 1px solid rgba(148,163,184,0.35);
            color: var(--muted);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #22c55e;
        }

        .sidebar-footer {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: var(--muted);
        }

        .sidebar-footer a {
            color: var(--muted);
            text-decoration: none;
        }

        .sidebar-footer a:hover { color: var(--accent); }

        /* CHAT AREA */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 18px 18px 14px 18px;
            min-width: 0;
        }

        .chat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 12px;
        }

        .header-left { display: flex; flex-direction: column; gap: 3px; }
        .header-title { font-size: 16px; font-weight: 600; }
        .header-sub { font-size: 12px; color: var(--muted); }

        .header-right { display: flex; align-items: center; gap: 8px; }

        .button-ghost {
            border-radius: 999px;
            border: 1px solid var(--border);
            background: rgba(15,23,42,0.7);
            color: var(--muted);
            font-size: 12px;
            padding: 6px 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        .button-ghost:hover {
            border-color: rgba(148,163,184,0.7);
            color: var(--text);
        }

        .button-primary {
            border-radius: 999px;
            border: none;
            background: linear-gradient(135deg, #38bdf8, #0ea5e9);
            color: #0b1120;
            font-size: 12px;
            font-weight: 600;
            padding: 7px 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(56,189,248,0.35);
        }

        .button-primary:hover { filter: brightness(1.03); }

        .chat-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 10px 0;
        }

        .messages {
            flex: 1;
            overflow-y: auto;
            padding-right: 4px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .messages::-webkit-scrollbar { width: 6px; }
        .messages::-webkit-scrollbar-thumb {
            background: rgba(148,163,184,0.5);
            border-radius: 999px;
        }

        .bubble-row {
            display: flex;
            flex-direction: column;
            max-width: 80%;
        }

        .bubble-row.me { margin-left: auto; align-items: flex-end; }

        .bubble-meta {
            font-size: 10px;
            color: var(--muted);
            margin-bottom: 2px;
        }

        .bubble {
            padding: 8px 11px;
            border-radius: 14px;
            font-size: 13px;
            line-height: 1.4;
            background: #111827;
            border: 1px solid rgba(31,41,55,0.9);
        }

        .bubble.me {
            background: linear-gradient(135deg, #0ea5e9, #22c55e);
            border-color: transparent;
            color: #0b1120;
        }

        .chat-input {
            margin-top: 6px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: #020617;
            padding: 8px 10px 8px 12px;
            display: flex;
            align-items: flex-end;
            gap: 10px;
        }

        .chat-input textarea {
            flex: 1;
            background: transparent;
            border: none;
            resize: none;
            color: var(--text);
            font-size: 13px;
            max-height: 80px;
        }

        .chat-input textarea:focus { outline: none; }

        .input-actions { display: flex; flex-direction: column; gap: 6px; }

        .input-icons {
            display: flex;
            justify-content: flex-end;
            gap: 5px;
        }

        .input-icon-btn {
            width: 24px;
            height: 24px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: #020617;
            color: var(--muted);
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .input-icon-btn:hover {
            color: var(--accent);
            border-color: rgba(148,163,184,0.7);
        }

        .input-send { display: flex; justify-content: flex-end; }

        .input-send button {
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 999px;
            border: none;
            background: linear-gradient(135deg, #38bdf8, #0ea5e9);
            color: #0b1120;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(56,189,248,0.35);
        }

        .input-send button:hover { filter: brightness(1.05); }

        @media (max-width: 900px) {
            .shell { flex-direction: column; }
            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }
        }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div>
            <div class="badge-role">
                <span class="icon">👤</span>
                <span><?= htmlspecialchars(ucfirst($role)) ?> login</span>
            </div>
            <div style="margin-top:10px;">
                <div class="room-title">Ruang Telekonsultasi</div>
                <div class="room-subtitle">ID Ruang #<?= htmlspecialchars($appointment_id) ?></div>
            </div>
        </div>

        <div class="info-card">
            <div>
                <div class="info-label">Pasien</div>
                <div class="info-value"><?= htmlspecialchars($appointment['nama_pasien']) ?></div>
            </div>
            <div>
                <div class="info-label">Dokter</div>
                <div class="info-value"><?= htmlspecialchars($appointment['nama_dokter']) ?></div>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div class="pill">
                    <span class="status-dot"></span>
                    <span>Sesi aktif</span>
                </div>
                <div class="pill">
                    🕒
                    <span><?= htmlspecialchars(date('d M Y H:i', strtotime($appointment['jadwal_waktu'] ?? 'now'))) ?></span>
                </div>
            </div>
        </div>

        <div class="info-card">
            <div class="info-label">Anda login sebagai</div>
            <div class="info-value"><?= htmlspecialchars($nama_login) ?></div>
            <div style="font-size:11px; color:var(--muted);">
                Gunakan ruang ini untuk konsultasi medis secara aman dan real‑time.
            </div>
        </div>

        <div class="sidebar-footer">
            <a href="<?= htmlspecialchars($backUrl) ?>">← Kembali</a>
            <span>ClinicFlow Teleconsult</span>
        </div>
    </aside>

    <section class="chat-area">
        <header class="chat-header">
            <div class="header-left">
                <div class="header-title">Chat konsultasi</div>
                <div class="header-sub">
                    Terhubung sebagai <?= htmlspecialchars($nama_login) ?> ·
                    <?= htmlspecialchars(ucfirst($role)) ?>
                </div>
            </div>
            <div class="header-right">
                <button type="button" class="button-ghost">⏺ Rekam</button>
                <button type="button" class="button-primary">⤴ Kirim ringkasan</button>
            </div>
        </header>

        <main class="chat-body">
            <div id="messages" class="messages"></div>

            <form id="chat-form" class="chat-input" autocomplete="off">
                <textarea id="chat-input-text" rows="1"
                          placeholder="Tulis pesan untuk dikirim ke lawan bicara..."></textarea>

                <div class="input-actions">
                    <div class="input-icons">
                        <button type="button" class="input-icon-btn" title="Lampirkan file">📎</button>
                        <button type="button" class="input-icon-btn" title="Kamera">📷</button>
                    </div>
                    <div class="input-send">
                        <button type="submit">Kirim</button>
                    </div>
                </div>
            </form>
        </main>
    </section>
</div>

<script>
    const APPOINTMENT_ID = <?= (int)$appointment_id ?>;
    const CURRENT_USER_ID = <?= (int)$user_id ?>;
</script>

<script>
    const form = document.getElementById('chat-form');
    const textarea = document.getElementById('chat-input-text');
    const messages = document.getElementById('messages');

    function renderMessages(list) {
        messages.innerHTML = '';
        list.forEach(row => {
            const isMe = parseInt(row.sender_id, 10) === CURRENT_USER_ID;

            const wrap = document.createElement('div');
            wrap.className = 'bubble-row' + (isMe ? ' me' : '');

            const meta = document.createElement('div');
            meta.className = 'bubble-meta';
            const time = new Date(row.created_at.replace(' ', 'T'));
            const timeText = time.toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'});
            meta.textContent = (isMe ? 'Anda' : 'Lawan bicara') + ' · ' + timeText;

            const bubble = document.createElement('div');
            bubble.className = 'bubble' + (isMe ? ' me' : '');
            bubble.textContent = row.message;

            wrap.appendChild(meta);
            wrap.appendChild(bubble);
            messages.appendChild(wrap);
        });

        messages.scrollTop = messages.scrollHeight;
    }

    function fetchMessages() {
        fetch('teleconsult_fetch_messages.php?appointment_id=' + APPOINTMENT_ID, {
            cache: 'no-store'
        })
            .then(res => res.json())
            .then(data => {
                renderMessages(data);
            })
            .catch(() => {});
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const text = textarea.value.trim();
        if (!text) return;

        const formData = new FormData();
        formData.append('appointment_id', APPOINTMENT_ID);
        formData.append('message', text);

        fetch('teleconsult_send_message.php', {
            method: 'POST',
            body: formData,
            cache: 'no-store'
        }).then(res => res.json())
          .then(data => {
              if (data.status === 'ok') {
                  textarea.value = '';
                  fetchMessages();
              } else {
                  alert('Gagal mengirim pesan');
              }
          }).catch(() => {
              alert('Terjadi kesalahan jaringan');
          });
    });

    // muat awal + polling
    fetchMessages();
    setInterval(fetchMessages, 2000);
</script>
</body>
</html>