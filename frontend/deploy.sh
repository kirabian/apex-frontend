#!/bin/bash

# Kasih tau user kalau proses mulai
echo "🚀 Memulai proses update APEX POS..."

# 1. Masuk ke folder frontend
cd ~/apex-pos/apex-frontend/frontend || exit

# 2. Tarik kode terbaru dari GitHub
echo "📥 Menarik kode terbaru dari GitHub..."
git pull origin main

# 3. Rakit (Build) project
echo "🛠️ Sedang merakit (Build) project Vue..."
npm run build

# 4. Jika build sukses, baru pindahkan file
if [ $? -eq 0 ]; then
    echo "✅ Build sukses! Memindahkan ke folder server..."
    sudo rm -rf /var/www/stokps/*
    sudo cp -r dist/* /var/www/stokps/
    echo "🎉 Update SELESAI! Web sudah live dengan versi terbaru."
else
    echo "❌ Build GAGAL. Silakan cek error di atas."
    exit 1
fi
