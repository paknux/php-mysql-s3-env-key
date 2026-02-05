<?php

include 'config.php';  

// Pengaturan Koneksi RDS
//$host = "database-2.ccqnofwkwmzs.us-east-1.rds.amazonaws.com";
$host = ; // ISI DENGAN ENDPOINT RDS ANDA
$user = "admin";
$pass = "P4ssw0rd";
$db_name = "smk_cloud_db"; 

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Test Koneksi RDS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white text-center">
                    <h5 class="mb-0">Cek Koneksi RDS</h5>
                </div>
                <div class="card-body text-center">
                    <p>Mencoba menghubungkan ke:<br><code><?= $host ?></code></p>
                    <hr>

                    <?php
                    // Mematikan error reporting default agar kita bisa buat tampilan custom
                    mysqli_report(MYSQLI_REPORT_OFF);
                    
                    $conn = new mysqli($host, $user, $pass);

                    if ($conn->connect_error) {
                        // TAMPILAN GAGAL
                        echo '
                        <div class="alert alert-danger" role="alert">
                            <h4 class="alert-heading">❌ GAGAL!</h4>
                            <p class="mb-0">Koneksi ditolak atau timeout.</p>
                        </div>
                        <div class="text-start mt-3 small">
                            <strong>Saran Perbaikan:</strong>
                            <ul>
                                <li>Cek <b>Security Group</b> RDS (Inbound port 3306).</li>
                                <li>Pastikan username/password benar.</li>
                                <li>Cek apakah RDS dalam status "Available".</li>
                            </ul>
                        </div>';
                    } else {
                        // TAMPILAN BERHASIL
                        echo '
                        <div class="alert alert-success" role="alert">
                            <h4 class="alert-heading">✅ BERHASIL!</h4>
                            <p class="mb-0">EC2 berhasil terhubung ke RDS.</p>
                        </div>
                        <p class="text-muted">Versi MySQL: ' . $conn->server_info . '</p>';
                        
                        $conn->close();
                    }
                    ?>
                    
                    <a href="test-rds.php" class="btn btn-outline-primary btn-sm mt-3">Coba Lagi</a>
                    <a href="index.php" class="btn btn-link btn-sm mt-3 text-decoration-none text-muted">Kembali ke S3 Manager</a>
                </div>
            </div>
            <p class="text-center mt-3 text-muted" style="font-size: 0.8rem;">Endpoint: database-2.ccqnofwkwmzs.us-east-1.rds.amazonaws.com</p>
        </div>
    </div>
</div>
</body>
</html>

