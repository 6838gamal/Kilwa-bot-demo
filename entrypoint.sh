#!/bin/bash
set -e

# ============================================================
# Entrypoint لـ Render / Railway / Fly.io / أي reverse proxy
# ============================================================

# ===== 1. تحديد البورت =====
# Render تعطي PORT ديناميكي (عادة 10000)، محلياً 80
PORT_TO_USE=${PORT:-80}

echo "=================================================="
echo "🚀 Kilwa Bot - Starting up"
echo "=================================================="
echo "📡 Port: ${PORT_TO_USE}"
echo "🔧 PHP version: $(php -v | head -n1)"
echo "🌐 Apache version: $(apache2 -v | head -n1)"
echo "=================================================="

# ===== 2. تعديل Apache ليستمع على البورت الديناميكي =====
if [ "$PORT_TO_USE" != "80" ]; then
    sed -i "s/^Listen 80$/Listen ${PORT_TO_USE}/g" /etc/apache2/ports.conf
    sed -i "s/:80>/:${PORT_TO_USE}>/g" /etc/apache2/sites-available/000-default.conf
fi

# ===== 3. إعداد Apache خلف reverse proxy =====
# هذا مهم جداً ليكتشف PHP أن الاتصال HTTPS
cat > /etc/apache2/conf-enabled/render-proxy.conf << 'EOF'
# ===== اكتشاف HTTPS خلف reverse proxy =====
<IfModule mod_setenvif.c>
    SetEnvIf X-Forwarded-Proto "https" HTTPS=on
    SetEnvIf X-Forwarded-Proto "https" HTTP_X_FORWARDED_PROTO=https
</IfModule>

# ===== تمرير IP الحقيقي للعميل =====
<IfModule mod_remoteip.c>
    RemoteIPHeader X-Forwarded-For
    RemoteIPInternalProxy 10.0.0.0/8
    RemoteIPInternalProxy 172.16.0.0/12
    RemoteIPInternalProxy 192.168.0.0/16
    RemoteIPInternalProxy 100.64.0.0/10
</IfModule>

# ===== ضمان قراءة الهيدرز =====
<IfModule mod_headers.c>
    RequestHeader set X-Forwarded-Proto "https" env=HTTPS
</IfModule>
EOF

# ===== 4. تفعيل الموديلات المطلوبة =====
a2enmod rewrite 2>/dev/null || true
a2enmod headers 2>/dev/null || true
a2enmod setenvif 2>/dev/null || true
a2enmod remoteip 2>/dev/null || true
a2enmod expires 2>/dev/null || true

# ===== 5. إعداد مجلد البيانات الدائم =====
DATA_DIR=${BOT_DATA_DIR:-/var/www/html/data}

echo "📁 Creating data directories at: ${DATA_DIR}"
mkdir -p "$DATA_DIR"
mkdir -p "$DATA_DIR/kilwa"
mkdir -p "$DATA_DIR/kilwa/backups"
mkdir -p "$DATA_DIR/verify_users"
mkdir -p "$DATA_DIR/edid"
mkdir -p "$DATA_DIR/T_"

# صلاحيات
chown -R www-data:www-data "$DATA_DIR" 2>/dev/null || true
chmod -R 775 "$DATA_DIR" 2>/dev/null || true

# ===== 6. إعدادات PHP =====
cat > /usr/local/etc/php/conf.d/99-render.ini << 'EOF'
; ===== إعدادات Render =====
display_errors = On
display_startup_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_NOTICE

; ===== رفع الملفات =====
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_execution_time = 120
max_input_time = 120

; ===== الجلسات =====
session.save_path = "/tmp"
session.gc_maxlifetime = 3600

; ===== المنطقة الزمنية =====
date.timezone = Asia/Baghdad

; ===== السماح بالاتصال الخارجي =====
allow_url_fopen = On

; ===== cURL =====
curl.cainfo = "/etc/ssl/certs/ca-certificates.crt"
EOF

# ===== 7. صلاحيات مجلد المشروع =====
chown -R www-data:www-data /var/www/html 2>/dev/null || true
chmod -R 775 /var/www/html 2>/dev/null || true

# ===== 8. تأكد أن bot-2.php موجود =====
if [ ! -f /var/www/html/bot-2.php ]; then
    echo "⚠️  WARNING: bot-2.php not found in /var/www/html/"
    echo "📂 Available files:"
    ls -la /var/www/html/
fi

# ===== 9. عرض معلومات تشخيصية =====
echo "=================================================="
echo "📋 Diagnostics"
echo "=================================================="
echo "✅ PHP Extensions:"
php -m | grep -E "pdo_sqlite|curl|mbstring|json|openssl" | sed 's/^/   - /'
echo ""
echo "✅ Data directory: ${DATA_DIR}"
ls -la "$DATA_DIR" 2>/dev/null || echo "   (empty)"
echo ""
echo "✅ Web root:"
ls -la /var/www/html/ | head -10
echo ""
echo "✅ Apache modules:"
apache2ctl -M 2>/dev/null | grep -E "rewrite|headers|remoteip|setenvif" | sed 's/^/   - /'
echo ""
echo "=================================================="
echo "🌐 Starting Apache on port ${PORT_TO_USE}"
echo "=================================================="

# ===== 10. تشغيل Apache في المقدمة =====
exec "$@"
