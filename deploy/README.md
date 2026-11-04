# STAR VPS Deployment

Target: Ubuntu 22.04/24.04, PHP 8.4, MySQL 8, Redis, Nginx, Reverb, systemd.
Domain: `https://star.alk-tech.my.id`

## 1. Packages

```bash
sudo apt update
sudo apt install -y nginx mysql-server redis-server supervisor unzip git \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-redis php8.4-mbstring php8.4-xml \
  php8.4-curl php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath \
  nodejs npm
```

`nodejs` must be Node 20+ for Vite. Install NodeSource if needed.

## 2. App directory

```bash
sudo mkdir -p /var/www/star
sudo chown -R www-data:www-data /var/www/star
cd /var/www/star
git clone <repo-url> current
cd current
cp deploy/env.production.example .env
```

Edit `.env`: DB, Redis, `APP_KEY`, Reverb keys, mail, etc.

```bash
php8.4 artisan key:generate
```

## 3. Database

```bash
sudo mysql -e "CREATE DATABASE star CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'star'@'127.0.0.1' IDENTIFIED BY 'change-me';"
sudo mysql -e "GRANT ALL PRIVILEGES ON star.* TO 'star'@'127.0.0.1'; FLUSH PRIVILEGES;"
```

Then:

```bash
php8.4 artisan migrate --force --seed
```

## 4. Storage

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

## 5. Nginx + SSL

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/star
sudo ln -s /etc/nginx/sites-available/star /etc/nginx/sites-enabled/star
sudo nginx -t
sudo systemctl reload nginx
```

SSL certificate paths in `nginx.conf` assume Let's Encrypt:

```bash
sudo certbot --nginx -d star.alk-tech.my.id
```

## 6. Services

```bash
sudo cp deploy/star-queue.service /etc/systemd/system/
sudo cp deploy/star-reverb.service /etc/systemd/system/
sudo cp deploy/star-scheduler.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now star-queue star-reverb star-scheduler
```

Check:

```bash
sudo systemctl status star-queue star-reverb star-scheduler
```

## 7. Build

```bash
npm ci
npm run build
php8.4 artisan optimize
```

## 8. Deploy updates

```bash
chmod +x deploy/deploy.sh
bash deploy/deploy.sh
```

## 9. Backup

```bash
# /etc/cron.d/star-backup
0 2 * * * www-data mysqldump --single-transaction star | gzip > /var/backups/star-$(date +\%F).sql.gz
30 2 * * * www-data rsync -a /var/www/star/current/storage/app/private/ /var/backups/star-storage/
```

Store backups off-server (S3/Wasabi/rclone). Test restore.

## 10. Monitoring

Watch:
- disk space (`/var/www/star/current/storage/app/private`)
- queue length / failed jobs
- Reverb service
- PHP-FPM/nginx errors
- backup success
