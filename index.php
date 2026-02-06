<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// 1. Load Environment Variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Konfigurasi dari Env
$host   = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_NAME'];
$user   = $_ENV['DB_USER'];
$pass   = $_ENV['DB_PASS'];

// 2. Auto-Initialize Database & Table
try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Buat Database jika belum ada
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4");
    $pdo->exec("USE `$dbName`");

    // Buat Tabel jika belum ada
    $tableSql = "CREATE TABLE IF NOT EXISTS karyawan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL,
        jabatan VARCHAR(50) NOT NULL,
        foto_url TEXT,
        s3_key TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($tableSql);
} catch (PDOException $e) {
    die("Koneksi DB Gagal: " . $e->getMessage());
}

// 3. Inisialisasi S3 Client
$s3Client = new S3Client([
    'version'     => 'latest',
    'region'      => $_ENV['AWS_REGION'],
    'credentials' => [
        'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
        'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
        'token'  => $_ENV['AWS_SESSION_TOKEN'], // BARIS INI WAJIB UNTUK AWS ACADEMY
    ],
]);
$bucketName = $_ENV['AWS_BUCKET'];

// 4. Logika CRUD (Proses Simpan/Hapus)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'create') {
        $nama = $_POST['nama'];
        $jabatan = $_POST['jabatan'];
        $file = $_FILES['foto'];

        if ($file['error'] == 0) {
            $key = 'karyawan/' . time() . '-' . basename($file['name']);
            try {
                // Upload ke S3 dengan ACL Enabled (public-read)
                $result = $s3Client->putObject([
                    'Bucket' => $bucketName,
                    'Key'    => $key,
                    'SourceFile' => $file['tmp_name'],
                    'ACL'    => 'public-read', 
                ]);

                $fotoUrl = $result['ObjectURL'];

                $stmt = $pdo->prepare("INSERT INTO karyawan (nama, jabatan, foto_url, s3_key) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nama, $jabatan, $fotoUrl, $key]);
            } catch (AwsException $e) {
                echo "Upload S3 Gagal: " . $e->getAwsErrorMessage();
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $id = $_POST['id'];
        $s3Key = $_POST['s3_key'];

        // Hapus dari S3
        $s3Client->deleteObject(['Bucket' => $bucketName, 'Key' => $s3Key]);

        // Hapus dari Database
        $stmt = $pdo->prepare("DELETE FROM karyawan WHERE id = ?");
        $stmt->execute([$id]);
    }
}

// Ambil Data
$karyawan = $pdo->query("SELECT * FROM karyawan ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>DRAWLERGG - CRUD S3</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; }
        .card { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .img-karyawan { width: 50px; height: 50px; object-fit: cover; border-radius: 50%; }
    </style>
</head>
<body>

<div class="container mt-5">
    <h2 class="mb-4">🚀 Data Karyawan (S3 Integrated)</h2>

    <div class="card mb-4 p-4">
        <h5>Tambah Karyawan</h5>
        <form action="" method="POST" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="action" value="create">
            <div class="col-md-4">
                <input type="text" name="nama" class="form-control" placeholder="Nama Lengkap" required>
            </div>
            <div class="col-md-3">
                <input type="text" name="jabatan" class="form-control" placeholder="Jabatan" required>
            </div>
            <div class="col-md-3">
                <input type="file" name="foto" class="form-control" accept="image/*" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Simpan</button>
            </div>
        </form>
    </div>

    <div class="card p-4">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Nama</th>
                    <th>Jabatan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($karyawan as $k): ?>
                <tr>
                    <td><img src="<?= $k['foto_url'] ?>" class="img-karyawan"></td>
                    <td><?= htmlspecialchars($k['nama']) ?></td>
                    <td><?= htmlspecialchars($k['jabatan']) ?></td>
                    <td>
                        <form action="" method="POST" onsubmit="return confirm('Hapus data ini?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $k['id'] ?>">
                            <input type="hidden" name="s3_key" value="<?= $k['s3_key'] ?>">
                            <button class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>