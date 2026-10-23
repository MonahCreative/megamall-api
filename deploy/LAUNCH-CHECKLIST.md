# Mega Mall Tanzania: going live

Everything in this `deploy` folder:

| File | What it is |
|---|---|
| `server-setup.sh` | One-time setup of a fresh Ubuntu 24.04 server |
| `env.production.example` | The API's `.env` for the live server |
| `web.env.production.example` | The shop's `.env.production` |
| `nginx-api.conf`, `nginx-shop.conf` | Web server settings for `api.megamall.co.tz` and `megamall.co.tz` |
| `megamall-web.service` | Keeps the shop running (restarts it after crashes and reboots) |
| `crontab.txt` | Scheduler (cancels unpaid orders) + nightly backup |
| `backup.sh` | Database + photos backup, keeps 14 days |
| `deploy.sh` | Puts new code live (backup, pull, migrate, rebuild, restart) |

Replace `megamall.co.tz` with the real domain everywhere if it is different.

---

## 0. Before you start (the client's accounts)

- [ ] **Domain** registered (e.g. `megamall.co.tz` from a .tz registrar).
- [ ] **Server (VPS)**: Ubuntu 24.04, at least 2 GB RAM (4 GB recommended), e.g. DigitalOcean, Hetzner, Contabo.
- [ ] **AzamPay** live credentials (after business KYC) and the API key.
- [ ] **Email** account for staff alerts (e.g. `orders@megamall.co.tz`) with its SMTP settings.
- [ ] **SMS** provider account and approved sender name (can come after launch; SMS stay in the outbox until then).
- [ ] Private **GitHub** repositories for `megamall-api` and `megamall-web`, with the latest code pushed.

## 1. Point the domain to the server

At the domain registrar, add DNS "A" records to the server's IP address:
`megamall.co.tz`, `www.megamall.co.tz`, `api.megamall.co.tz`. It can take up to a few hours to work everywhere.

## 2. Prepare the server

```bash
ssh root@SERVER_IP
# copy server-setup.sh up (or paste it into nano), check the domain/email at the top, then:
bash server-setup.sh
```

**Write down the database password it prints at the end.**

## 3. Put the code on the server

```bash
su - deploy
cd /var/www
git clone https://github.com/YOUR-ACCOUNT/megamall-api.git
git clone https://github.com/YOUR-ACCOUNT/megamall-web.git
```

(For private repos use a GitHub "deploy key" or a personal access token.)

## 4. API and admin panel

```bash
cd /var/www/megamall-api
cp deploy/env.production.example .env
nano .env                                   # fill in DB_PASSWORD, mail, AzamPay, domains
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=SettingSeeder --force
php artisan db:seed --class=RegionSeeder --force
php artisan db:seed --class=ContentPageSeeder --force
php artisan db:seed --class=HomeSectionSeeder --force
php artisan storage:link
php artisan megamall:admin                  # the owner's admin login
chmod -R ug+rwx storage bootstrap/cache && chgrp -R www-data storage bootstrap/cache
php artisan optimize && php artisan filament:optimize
```

Do **not** run the demo seeders (`CatalogSeeder`, `DemoActivitySeeder`) on the live server: the client adds real products.

## 5. Shop

```bash
cd /var/www/megamall-web
cp /var/www/megamall-api/deploy/web.env.production.example .env.production
npm ci && npm run build
exit                                         # back to root
cp /var/www/megamall-api/deploy/megamall-web.service /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now megamall-web
```

## 6. Web server and SSL

```bash
cp /var/www/megamall-api/deploy/nginx-api.conf  /etc/nginx/sites-available/megamall-api
cp /var/www/megamall-api/deploy/nginx-shop.conf /etc/nginx/sites-available/megamall-shop
ln -s /etc/nginx/sites-available/megamall-api  /etc/nginx/sites-enabled/
ln -s /etc/nginx/sites-available/megamall-shop /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
certbot --nginx -d megamall.co.tz -d www.megamall.co.tz -d api.megamall.co.tz -m it@megamall.co.tz --agree-tos --redirect
```

## 7. Scheduler and backups

```bash
crontab -u deploy -e      # paste the two lines from deploy/crontab.txt
sudo -u deploy bash /var/www/megamall-api/deploy/backup.sh     # first backup, to check it works
```

Set up the off-site copy at the bottom of `backup.sh` (Google Drive or Backblaze via rclone).
A backup that only lives on the same server does not survive the server.

## 8. Admin settings (https://api.megamall.co.tz/admin)

- [ ] **Store settings**: shop name, real phone and WhatsApp, email, business address, TIN, colours,
      delivery note, staff alert emails, SMS wording, stock alert levels, payment window.
- [ ] **Shop pages**: read Terms, Privacy and Return policy with the client (legal check).
- [ ] **Regions**: delivery days per region.
- [ ] **Products**: real products with square photos (or Excel import), cost prices, stock.
- [ ] **Banners** for the home page slider, **Product sections** for the home page rows.
- [ ] **AzamPay portal**: register the callback URL
      `https://api.megamall.co.tz/api/payments/callback/azampay?key=<AZAMPAY_CALLBACK_KEY>`.

## 9. Test before announcing

- [ ] Shop opens on phone and computer, with the padlock (https).
- [ ] Dar es Salaam order: confirm, pack, send out, delivered (cash). SMS appear in the SMS outbox.
- [ ] Upcountry order: pay a **small real amount** with M-Pesa; the order turns paid by itself.
- [ ] Staff email arrives for a new order; the admin pop-up and chime work.
- [ ] Invoice and packing slip print. Customer "Download invoice" works.
- [ ] Share a product link on WhatsApp: photo, name and price show
      (if not, paste the link into https://developers.facebook.com/tools/debug/ and click "Scrape again").
- [ ] Unpaid upcountry order is cancelled after the payment window (scheduler is running).
- [ ] `php artisan about` shows `Environment: production` and `Debug Mode: OFF`.

## 10. After launch

- **Google**: add the site in Google Search Console and submit `https://megamall.co.tz/sitemap.xml`.
- **Updates**: push code to GitHub, then on the server `bash /var/www/megamall-api/deploy/deploy.sh`.
- **Backups**: once a month, restore the latest backup on your PC to be sure it works:
  `gunzip < db-YYYY-MM-DD_HHMM.sql.gz | mysql -u root megamall_restore_test`.
- **Errors**: `storage/logs/laravel-YYYY-MM-DD.log` on the server.
