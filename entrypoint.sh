#!/bin/bash
set -e

# Render تعطي PORT ديناميكي (عادة 10000)
PORT_TO_USE=${PORT:-80}

# نعدّل Apache ليستمع على هذا البورت
sed -i "s/Listen 80/Listen ${PORT_TO_USE}/g" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT_TO_USE}>/g" /etc/apache2/sites-available/000-default.conf

# إنشاء مجلد البيانات الدائم (سيُربط بـ Disk في Render)
DATA_DIR=${BOT_DATA_DIR:-/var/www/html/data}
mkdir -p "$DATA_DIR"
mkdir -p "$DATA_DIR/kilwa"
mkdir -p "$DATA_DIR/kilwa/backups"
mkdir -p "$DATA_DIR/verify_users"
mkdir -p "$DATA_DIR/edid"
chown -R www-data:www-data "$DATA_DIR"

echo "🚀 Starting Apache on port ${PORT_TO_USE}"
echo "📁 Data directory: ${DATA_DIR}"

# تشغيل Apache
exec "$@"
