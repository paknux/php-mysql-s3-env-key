<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Aktifkan laporan error untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * 1. LOAD ENVIRONMENT VARIABLES
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

// Configs
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
 * 2. DB & S3 INITIALIZATION
 */
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi DB Gagal: " . $e->getMessage());
}

$s3Args = [
    'version' => 'latest', 
    'region' => $awsRegion, 
    'credentials' => ['key' => $awsKey, 'secret' => $awsSecret]
];
if ($awsToken) { $s3Args['credentials']['token'] = $awsToken; }
$s3Client = new S3Client($s3Args);

/**
 * 3. CRUD LOGIC
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action == 'create' || $action == 'update') {
            $nama = $_POST['nama'];
            $jabatan = $_POST['jabatan'];
            $id = $_POST['id'] ?? null;
            $old_s3_key = $_POST['old_s3_key'] ?? null;
            
            // Default: gunakan data lama
            $foto_url = $_POST['current_foto_url'] ?? '';
            $s3_key = $old_s3_key;

            // Jika ada upload file baru
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $new_key = 'karyawan/' . time() . '-' . basename($_FILES['foto']['name']);
                
                // Upload ke S3
                $result = $s3Client->putObject([
                    'Bucket' => $awsBucket, 
                    'Key' => $new_key, 
                    'SourceFile' => $_FILES['foto']['tmp_name'], 
                    'ACL' => 'public-read'
                ]);
                
                // Hapus file lama di S3 jika ini proses update
                if ($action == 'update' && !empty($old_s3_key)) {
                    $s3Client->deleteObject(['Bucket' => $awsBucket, 'Key' => $old_s3_key]);
                }

                $s3_key = $new_key;
                $foto_url = $result['ObjectURL'];
            }

            if ($action == 'create') {
                $stmt = $pdo->prepare("INSERT INTO karyawan (nama, jabatan, foto_url, s3_key) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nama, $jabatan, $foto_url, $s3_key]);
            } else {
                $stmt = $pdo->prepare("UPDATE karyawan SET nama=?, jabatan=?, foto_url=?, s3_key=? WHERE id=?");
                $stmt->execute([$nama, $jabatan, $foto_url, $s3_key, $id]);
            }
        }

        if ($action == 'delete') {
            if (!empty($_POST['s3_key'])) {
                $s3Client->deleteObject(['Bucket' => $awsBucket, 'Key' => $_POST['s3_key']]);
            }
            $pdo->prepare("DELETE FROM karyawan WHERE id = ?")->execute([$_POST['id']]);
        }
    } catch (Exception $e) {
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}

$karyawan = $pdo->query("SELECT * FROM karyawan ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>S3 Manager - Fixed Edit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #0d1117; color: #e6edf3; }
        .card { background-color: #161b22; border: 1px solid #30363d; }
        .img-karyawan { width: 45px; height: 45px; object-fit: cover; border-radius: 50%; border: 2px solid #444; }
        .form-control { background-color: #0d1117; border-color: #30363d; color: #fff; }
    </style>
</head>
<body>

<div class="container mt-5">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <h2 class="mb-4 text-white"><i class="bi bi-people-fill me-2"></i>Aplikasi Database Karyawan</h2>

    <div class="card p-4 mb-4 shadow-sm">
        <h5 class="mb-3 text-white">Tambah Karyawan Baru</h5>
        <form action="" method="POST" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="action" value="create">
            <div class="col-md-4"><input type="text" name="nama" class="form-control" placeholder="Nama" required></div>
            <div class="col-md-3"><input type="text" name="jabatan" class="form-control" placeholder="Jabatan" required></div>
            <div class="col-md-3"><input type="file" name="foto" class="form-control" accept="image/*" required></div>
            <div class="col-md-2"><button type="submit" class="btn btn-success w-100 fw-bold">Simpan</button></div>
        </form>
    </div>

    <div class="card overflow-hidden shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th class="ps-4">Profil</th>
                    <th>Nama & Jabatan</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($karyawan as $k): ?>
                <tr>
                    <td class="ps-4"><img src="<?= $k['foto_url'] ?>" class="img-karyawan"></td>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($k['nama']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($k['jabatan']) ?></small>
                    </td>
                    <td class="text-center">
                        <div class="d-flex justify-content-center gap-2">
                            <button class="btn btn-outline-warning btn-sm border-0" 
                                    onclick='openEditModal(<?= json_encode($k) ?>)'>
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            
                            <form action="" method="POST" onsubmit="return confirm('Hapus data ini?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                <input type="hidden" name="s3_key" value="<?= $k['s3_key'] ?>">
                                <button class="btn btn-outline-danger btn-sm border-0"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" enctype="multipart/form-data" class="modal-content card border-0">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">Edit Data Karyawan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="old_s3_key" id="edit_old_s3_key">
                <input type="hidden" name="current_foto_url" id="edit_current_foto_url">
                
                <div class="col-12">
                    <label class="form-label small text-secondary">Nama Lengkap</label>
                    <input type="text" name="nama" id="edit_nama" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label small text-secondary">Jabatan</label>
                    <input type="text" name="jabatan" id="edit_jabatan" class="form-control" required>
                </div>
                <div class="col-12 text-center my-2">
                    <img id="preview_foto" src="" class="img-karyawan" style="width: 70px; height: 70px;">
                </div>
                <div class="col-12">
                    <label class="form-label small text-secondary">Ganti Foto (Opsional)</label>
                    <input type="file" name="foto" class="form-control" accept="image/*">
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-link text-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-warning fw-bold">Update Sekarang</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const modalEditElement = new bootstrap.Modal(document.getElementById('modalEdit'));

    function openEditModal(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_nama').value = data.nama;
        document.getElementById('edit_jabatan').value = data.jabatan;
        document.getElementById('edit_old_s3_key').value = data.s3_key;
        document.getElementById('edit_current_foto_url').value = data.foto_url;
        document.getElementById('preview_foto').src = data.foto_url;
        modalEditElement.show();
    }
</script>

</body>
</html>