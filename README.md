# Aplikasi Data Karyawan CRUD PHP-MySQL-S3 Dengan Environmet Variable dan S3 Key 
---
## Menggunakan **EC2 Ubuntu 24.04** di lingkungan **AWS Academy**.
---

### Project ini mendokumentasikan langkah-langkah mendeploy aplikasi PHP yang menyimpan file (asset) di **AWS S3** menggunakan **EC2 Ubuntu 24.04** di lingkungan **AWS Academy**.
---


## I. Persiapan Infrastruktur AWS

Buat dulu SG yang sesuai, ijinkan inbound rule port 22, 80 (web server), dan 3306 (MySQL/Aurora) dari anywhere-IPv4 (0.0.0.0/0).

### A. Buat RDS

1. Buka Aurora and RDS
2. Klik create database
3. Choose a database creation method : Full Configuration
4. Engine type : MySQL
5. Templates : Sandbox
6. Availability and durability : otomatis terpilih Single-AZ DB instance deployment (1 instance)
7. DB instance identifier : database-1
8. Master username : (admin) boleh diganti
9. Credentials management : Self managed
10. Master password : (P4ssw0rd) boleh diganti
Confirm master password : (P4ssw0rd) boleh diganti


11. Public access : No, kalau butuh diakses dari luar buat jadi Yes
12. VPC security group (firewall) : Choose existing, pilih yang sudah dibuat tadi
13. Klik create database
14. Tunggu sampai mendapatkan End Point


### B. Membuat Instance EC2

1. Login ke AWS Academy Learner Lab.
2. Launch Instance dengan spesifikasi:
   - **Nama:** `php-mysql-s3`
   - **AMI:** Ubuntu 24.04 LTS.
   - **Instance Type:** t2.micro (Free Tier).
   - **Key Pair:** Pilih atau buat baru.
   - **Security Group:** Izinkan **HTTP (80)** dan **SSH (22)**.
   - Pastikan ubah IAM Role menjadi LabInstanceProfile dari menu Action > Security > Modify IAM Role
3. Hubungkan ke instance via SSH.

### C. Membuat dan Konfigurasi S3 Bucket
S3 Bucket dapat dibuat dengan Web GUI Management Console seperti biasa, 


## Buat S3
1. Buka Amazon S3 (cari S3)
2. Klik Create bucket 
   - Bucket Type : General purpose
   - Bucket Name : nug-php-mysql-s3-env-key
   - Object Ownership
        - pilih : <h2> ACLs enabled </h2>
   - Block Public Access settings for this bucket
   - pastikan Block all public access TIDAK DICENTANG
   - jangan lupa CENTANG acknowledge that the current settings
3. klik Create bucket



#### Membuka Public Access Block
Jika belum dibuka public access, dapat dilakukan dengan Clodshell seperti berikut ini :
```bash
aws s3api put-public-access-block --bucket nugwebphps3 --public-access-block-configuration "BlockPublicAcls=false,IgnorePublicAcls=false,BlockPublicPolicy=false,RestrictPublicBuckets=false"
```



#### Mengatur Policy agar file bisa diakses publik (Read Only)
Jika menghendaki file dapat diakses public tanpa ACL (ACL disabled) dengan bucket policy, maka dapat public access dengan Clodshell seperti berikut ini :
```bash
aws s3api put-bucket-policy --bucket nugwebphps3 --policy '{
    "Version":"2012-10-17",
    "Statement":[{"Sid":"PublicReadGetObject","Effect":"Allow","Principal":"*","Action":"s3:GetObject","Resource":"arn:aws:s3:::nugwebphps3/*"}]
}'
```


## II. Deploy App ke EC2

## Langkah 1: Persiapan dan Instalasi Server
Jalankan perintah berikut pada terminal EC2 Ubuntu 24.04 untuk menginstal Apache, PHP, dan dependensi lainnya:

```bash
# Update sistem
sudo apt update

# Install Apache, PHP, dan ekstensi yang diperlukan
sudo apt install -y apache2 php-mysql php php-cli php-curl php-xml php-mbstring libapache2-mod-php unzip


# Install Composer secara global
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install AWS SDK for PHP di direktori project
cd /var/www/html
composer require aws/aws-sdk-php vlucas/phpdotenv

# Atur izin folder agar web server bisa menulis file
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 777 /var/www/html

# Hapus index.html
sudo rm /var/www/html/index.html
```


## Langkah 2: Deploy Aplikasi
```

cd ~

git clone https://github.com/paknux/php-mysql-s3-env-key.git

cd php-mysql-s3-env-key
cp * /var/www/html
```


## Envirom,emt Variable .env
Environment variable dapat berupa file .env

Isi dari Environment memuat hal berikut ini:

````
DB_HOST=database-1.ccqnofwkwmzs.us-east-1.rds.amazonaws.com
DB_PORT=3306
DB_NAME=db_karyawan
DB_USER=admin
DB_PASS=P4ssw0rd


AWS_REGION=us-east-1
AWS_BUCKET=nug-php-mysql-s3-env-key

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_SESSION_TOKEN=
````


## Pengujian
##### Gunakan browser
