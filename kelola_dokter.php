<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// ====== AUTH JWT: HANYA ADMIN ======
$role = null;

if (!empty($_COOKIE['token'])) {
    try {
        $decoded = JWT::decode($_COOKIE['token'], new Key($JWT_SECRET, 'HS256'));
        $data    = (array) $decoded->data;
        $role    = $data['role'] ?? null;
    } catch (Exception $e) {
        $role = null;
    }
}

if ($role !== 'admin') {
    header('Location: index.php');
    exit;
}

// ====== PROSES FORM (PDO) ======

// tambah dokter
if (isset($_POST['simpan_dokter'])) {
    $nama      = trim($_POST['nama_dokter'] ?? '');
    $spesialis = trim($_POST['spesialis'] ?? '');

    if ($nama !== '' && $spesialis !== '') {
        $sql = "INSERT INTO dokter (user_id, nama, spesialis)
                VALUES (0, :nama, :spesialis)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nama'      => $nama,
            ':spesialis' => $spesialis,
        ]);
    }
}

// ambil data dokter
$stmt = $pdo->query("SELECT * FROM dokter ORDER BY id ASC");
$dokter_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// tambah jadwal
if (isset($_POST['simpan_jadwal'])) {
    $dokter_id   = (int) ($_POST['dokter_id'] ?? 0);
    $tanggal     = trim($_POST['tanggal'] ?? '');
    $jam_mulai   = trim($_POST['jam_mulai'] ?? '');
    $jam_selesai = trim($_POST['jam_selesai'] ?? '');
    $kuota       = (int) ($_POST['kuota'] ?? 1);

    if ($dokter_id > 0 && $tanggal !== '' && $jam_mulai !== '' && $jam_selesai !== '' && $kuota > 0) {
        $sql = "INSERT INTO jadwal_dokter (dokter_id, tanggal, jam_mulai, jam_selesai, kuota)
                VALUES (:dokter_id, :tanggal, :jam_mulai, :jam_selesai, :kuota)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':dokter_id'   => $dokter_id,
            ':tanggal'     => $tanggal,
            ':jam_mulai'   => $jam_mulai,
            ':jam_selesai' => $jam_selesai,
            ':kuota'       => $kuota,
        ]);
    }
}

// ambil jadwal dokter
$sql = "SELECT j.*, d.nama, d.spesialis
        FROM jadwal_dokter j
        JOIN dokter d ON j.dokter_id = d.id
        ORDER BY j.tanggal, j.jam_mulai";
$stmt   = $pdo->query($sql);
$jadwal = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'header.php'; ?>

<style>
    .page-header {
        margin-top: 1.5rem;
        margin-bottom: 1.25rem;
    }
    .page-header h2 {
        margin-bottom: .25rem;
    }
    .badge-soft-primary {
        background: rgba(37,99,235,0.08);
        color: #1d4ed8;
        border-radius: 999px;
        padding: .25rem .7rem;
        font-size: .75rem;
    }
    .card-elevated {
        border-radius: 1rem;
        border: none;
        box-shadow: 0 0.6rem 1.4rem rgba(15,23,42,0.08);
    }
    .table thead th {
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        border-bottom-width: 1px;
    }
</style>

<div class="container mt-3 mb-4">

    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1">Kelola Dokter & Jadwal</h2>
            <span class="badge-soft-primary">Role: Admin</span>
        </div>
        <a href="dashboard_admin.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>

    <div class="row g-3">
        <!-- Form Dokter -->
        <div class="col-lg-4">
            <div class="card card-elevated h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h5 class="mb-1">Tambah Dokter</h5>
                    <small class="text-muted">Input data dokter baru klinik.</small>
                </div>
                <div class="card-body">
                    <form method="post" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label class="form-label">Nama Dokter</label>
                            <input type="text" name="nama_dokter" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Spesialis</label>
                            <input type="text" name="spesialis" class="form-control" required>
                        </div>
                        <button type="submit" name="simpan_dokter" class="btn btn-primary w-100">
                            Simpan Dokter
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tabel Dokter -->
        <div class="col-lg-8">
            <div class="card card-elevated mb-3">
                <div class="card-header bg-white border-0 pb-0">
                    <h5 class="mb-1">Daftar Dokter</h5>
                    <small class="text-muted">Ringkasan seluruh dokter yang terdaftar.</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Spesialis</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dokter_list)) : ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Belum ada data dokter.</td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($dokter_list as $d) : ?>
                                        <tr>
                                            <td><?php echo str_pad($d['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                            <td><?php echo htmlspecialchars($d['nama']); ?></td>
                                            <td><?php echo htmlspecialchars($d['spesialis']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Form Jadwal + Tabel Jadwal -->
            <div class="card card-elevated">
                <div class="card-header bg-white border-0 pb-0">
                    <h5 class="mb-1">Kelola Jadwal Dokter</h5>
                    <small class="text-muted">Atur jadwal praktik dan kuota pasien.</small>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Dokter</label>
                            <select name="dokter_id" class="form-select" required>
                                <option value="">- Pilih Dokter -</option>
                                <?php foreach ($dokter_list as $d) : ?>
                                    <option value="<?php echo $d['id']; ?>">
                                        ID <?php echo str_pad($d['id'], 4, '0', STR_PAD_LEFT); ?>
                                        - <?php echo htmlspecialchars($d['nama']); ?> (<?php echo htmlspecialchars($d['spesialis']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal</label>
                            <input type="date" name="tanggal" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Jam Mulai</label>
                            <input type="time" name="jam_mulai" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Jam Selesai</label>
                            <input type="time" name="jam_selesai" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Kuota</label>
                            <input type="number" name="kuota" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" name="simpan_jadwal" class="btn btn-success w-100">
                                Simpan Jadwal
                            </button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Dokter</th>
                                    <th>Tanggal</th>
                                    <th>Jam</th>
                                    <th>Kuota</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($jadwal)) : ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Belum ada jadwal dokter.</td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($jadwal as $j) : ?>
                                        <tr>
                                            <td><?php echo $j['id']; ?></td>
                                            <td><?php echo htmlspecialchars($j['nama']) . ' (' . htmlspecialchars($j['spesialis']) . ')'; ?></td>
                                            <td><?php echo $j['tanggal']; ?></td>
                                            <td><?php echo $j['jam_mulai'] . ' - ' . $j['jam_selesai']; ?></td>
                                            <td><?php echo $j['kuota']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>