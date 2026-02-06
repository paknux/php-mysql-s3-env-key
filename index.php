<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * 1. LOAD ENVIRONMENT VARIABLES (HYBRID)
 */
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

function get_config($key, $default = null) {
    $val = getenv($key);
    if ($val !== false) return $val;
    return $_ENV[$key] ?? $default;
}

// Konfigurasi Database & AWS
$host   = get_config('DB_HOST', 'localhost');
$dbName = get_config('DB_NAME');
$user   = get_config('DB_USER');
$pass   = get_config('DB_PASS');

$awsKey    = get_config('AWS_ACCESS_KEY_ID');
$awsSecret = get_config('AWS_SECRET_ACCESS_KEY');
$awsToken  = get_config('AWS_SESSION_TOKEN'); 
$awsRegion = get_config('AWS_REGION', 'us-east-1');
$awsBucket = get_config('AWS_BUCKET');

/**
 * 2. DATABASE INITIALIZATION
 */
try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4");
    $pdo->exec("USE `$dbName` ");
    $pdo->exec("CREATE TABLE IF NOT EXISTS karyawan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL,
        jabatan VARCHAR(50) NOT NULL,
        foto_url TEXT,
        s3_key TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("Koneksi DB Gagal: " . $e->getMessage());
}

/**
 * 3. AWS S3 CLIENT INITIALIZATION
 */
$s3Args = ['version' => 'latest', 'region' => $awsRegion, 'credentials' => ['key' => $awsKey, 'secret' => $awsSecret]];
if ($awsToken) { $s3Args['credentials']['token'] = $awsToken; }
$s3Client = new S3Client($s3Args);

/**
 * 4. CRUD LOGIC
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'create') {
        $nama = $_POST['nama'];
        $jabatan = $_POST['jabatan'];
        $file = $_FILES['foto'];
        if ($file['error'] == 0) {
            $key = 'karyawan/' . time() . '-' . basename($file['name']);
            try {
                $result = $s3Client->putObject([
                    'Bucket' => $awsBucket, 'Key' => $key, 'SourceFile' => $file['tmp_name'], 'ACL' => 'public-read'
                ]);
                $stmt = $pdo->prepare("INSERT INTO karyawan (nama, jabatan, foto_url, s3_key) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nama, $jabatan, $result['ObjectURL'], $key]);
            } catch (AwsException $e) {
                $error = $e->getAwsErrorMessage();
            }
        }
    }
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        try {
            $s3Client->deleteObject(['Bucket' => $awsBucket, 'Key' => $_POST['s3_key']]);
            $stmt = $pdo->prepare("DELETE FROM karyawan WHERE id = ?");
            $stmt->execute([$_POST['id']]);
        } catch (AwsException $e) {
            $error = $e->getAwsErrorMessage();
        }
    }
}

$karyawan = $pdo->query("SELECT * FROM karyawan ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S3 Manager - Dark Mode</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #0d1117; color: #e6edf3; }
        .card { background-color: #161b22; border: 1px solid #30363d; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.2); }
        .table { --bs-table-bg: transparent; color: #e6edf3; }
        .table-light { --bs-table-bg: #21262d; --bs-table-color: #f0f6fc; border-color: #30363d; }
        .img-karyawan { width: 42px; height: 42px; object-fit: cover; border-radius: 50%; border: 2px solid #30363d; }
        .form-control, .form-control:focus { background-color: #0d1117; border-color: #30363d; color: #fff; }
        .form-control::placeholder { color: #6e7681; }
        .badge-env { background-color: #238636; color: #fff; font-size: 0.75rem; }
    </style>
</head>
<body>

<nav class="navbar border-bottom border-secondary mb-5 shadow-sm">
    <div class="container">
        <span class="navbar-brand mb-0 h1 text-white"><i class="bi bi-database-fill-gear me-2"></i> S3 Cloud Manager</span>
        <span class="badge badge-env"><i class="bi bi-shield-check me-1"></i> Hybrid Env Active</span>
    </div>
</nav>

<div class="container">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger border-0 shadow-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="mb-4">
        <h3 class="fw-bold text-white">Input Data Baru</h3>
        <p class="text-muted small">Target S3 Bucket: <code class="text-info"><?= htmlspecialchars($awsBucket) ?></code></p>
    </div>

    <div class="card p-4 mb-5">
        <form action="" method="POST" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="action" value="create">
            <div class="col-md-4">
                <label class="form-label small text-secondary">Nama Karyawan</label>
                <input type="text" name="nama" class="form-control" placeholder="Nama Lengkap" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-secondary">Jabatan</label>
                <input type="text" name="jabatan" class="form-control" placeholder="Posisi" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-secondary">Upload Foto</label>
                <input type="file" name="foto" class="form-control" accept="image/*" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 fw-bold">Unggah ke S3</button>
            </div>
        </form>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0 text-white">Daftar Karyawan</h4>
    </div>

    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" width="80">Profil</th>
                        <th>Informasi Karyawan</th>
                        <th>Posisi</th>
                        <th class="text-center" width="100">Opsi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($karyawan)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">Belum ada data di database.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($karyawan as $k): ?>
                    <tr>
                        <td class="ps-4">
                            <a href="<?= $k['foto_url'] ?>" target="_blank">
                                <img src="<?= $k['foto_url'] ?>" class="img-karyawan">
                            </a>
                        </td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($k['nama']) ?></div>
                            <small class="text-muted" style="font-size: 0.7rem;">S3 Key: <?= htmlspecialchars($k['s3_key']) ?></small>
                        </td>
                        <td><span class="badge bg-dark border border-secondary"><?= htmlspecialchars($k['jabatan']) ?></span></td>
                        <td class="text-center">
                            <form action="" method="POST" onsubmit="return confirm('Hapus data dari S3 dan Database?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                <input type="hidden" name="s3_key" value="<?= $k['s3_key'] ?>">
                                <button class="btn btn-outline-danger btn-sm border-0"><i class="bi bi-trash3"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<footer class="container mt-5 py-4 border-top border-secondary text-center text-muted small">
    &copy; 2026 Cloud S3 CRUD System - Powered by AWS SDK PHP
</footer>

</body>
</html>