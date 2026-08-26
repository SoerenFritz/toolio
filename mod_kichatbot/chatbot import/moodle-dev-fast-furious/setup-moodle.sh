#!/bin/bash
# ===============================
# Fast & Furious Moodle 5.1 Setup
# ===============================



echo "🚀 Starte Container..."
docker compose up -d

echo "⏳ Warte kurz bis Container bereit sind..."
sleep 5

echo "📦 Installiere Moodle im Container..."

docker exec -it moodle-dev-server bash -c "

apt update && apt install -y git unzip curl locales

# Deutsch Locale
locale-gen de_DE.UTF-8

# Moodle Core nur klonen wenn leer
if [ ! -d /var/www/moodle/lib ]; then
  echo '📥 Lade Moodle Core...'
  cd /var/www
  git clone -b MOODLE_501_STABLE https://github.com/moodle/moodle.git moodle_temp
  cp -a moodle_temp/. /var/www/moodle/
  rm -rf moodle_temp
fi

# Composer installieren
if ! command -v composer >/dev/null 2>&1; then
  echo '📦 Installiere Composer...'
  php -r \"copy('https://getcomposer.org/installer','composer-setup.php');\"
  php composer-setup.php
  mv composer.phar /usr/local/bin/composer
  rm composer-setup.php
fi

cd /var/www/moodle

echo '📦 Installiere Composer Dependencies...'
composer install --no-dev --classmap-authoritative

mkdir -p /var/www/moodledata

echo '⚙️ Starte Moodle CLI Installation...'

php /var/www/moodle/admin/cli/install.php \
--non-interactive \
--agree-license \
--lang=de \
--wwwroot=http://localhost:8080 \
--dataroot=/var/www/moodledata \
--dbtype=mariadb \
--dbhost=db \
--dbname=moodle \
--dbuser=moodle \
--dbpass=moodle \
--fullname='Moodle Dev' \
--shortname='MoodleDev' \
--adminuser=admin \
--adminpass='Admin123!' \
--adminemail='admin@example.com'

echo '🔧 Apache konfigurieren...'

sed -i 's|APACHE_DOCUMENT_ROOT=.*|APACHE_DOCUMENT_ROOT=/var/www/moodle/public|' /etc/apache2/envvars
echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# Apache neu laden (kein harter restart → verhindert Script-Abbruch)
apachectl -k graceful || true

echo '🔐 Setze Dateirechte...'
chown -R www-data:www-data /var/www/moodle /var/www/moodledata
chmod -R 775 /var/www/moodle /var/www/moodledata
chmod 755 /var/www/moodle/public
chmod 644 /var/www/moodle/config.php

echo ""
echo "✅ Moodle 5.1 Installation abgeschlossen!"
echo ""
echo "🌍 URL:"
echo "http://localhost:8080"
echo ""
echo "👤 Login:"
echo "admin / Admin123!"
echo ""
"




echo ""
echo "✅ Moodle 5.1 Installation abgeschlossen!"
echo ""
echo "🌍 URL:"
echo "http://localhost:8080/my"
echo ""
echo "👤 Login:"
echo "admin / Admin123!"
echo ""