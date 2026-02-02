#!/bin/bash

echo "🚀 Memulai perbaikan sistem APEX POS..."

# 1. Update Codebase
echo "📥 Mengambil update kode terbaru..."
cd ~/apex-pos/apex-frontend
git pull origin main

# 2. Fix Backend (Critical)
echo "🛠️ Memperbaiki Backend (Database & Role)..."
cd backend
composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan migrate --force

echo "🌱 Mendaftarkan Role Admin Produk ke Database..."
php artisan db:seed --class=RoleAndUserSeeder --force

echo "🧹 Membersihkan Cache Backend..."
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# 3. Rebuild Frontend
echo "🎨 Membangun ulang Frontend (Vue.js)..."
cd ../frontend
npm install
npm run build

# 4. Deploy Frontend
echo "📂 Menyalin file frontend ke server..."
sudo rm -rf /var/www/stokps/*
sudo cp -r dist/* /var/www/stokps/

echo "✅ Perbaikan selesai! Silakan:"
echo "1. Login ulang sebagai adminproduk"
echo "2. Jika masih gagal, edit user adminproduk -> simpan ulang role-nya"
