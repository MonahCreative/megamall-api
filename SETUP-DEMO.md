# Mega Mall Tanzania: demo data for the client presentation

This fills the shop with 27 real-looking products in 12 categories, each with a real photo
(from Unsplash, free to use under the Unsplash License), a running "Kitchen week" sale, a home
page slider, and two weeks of pretend orders, shop sales and reviews so the admin dashboard and
charts have something to show.

**This wipes the database.** Do it on your local demo copy, not on a live shop.

## 1. Copy in the files

Stop `php artisan serve` (Ctrl+C), then extract `megamall-api-demo-data.zip` into
`C:\Users\AIO\Projects\megamall\megamall-api` and choose **Replace**.

## 2. Rebuild the database with the demo catalogue

```powershell
cd C:\Users\AIO\Projects\megamall\megamall-api
php artisan migrate:fresh --seed
```

You will see `photo saved: ...` for each product as the photos download (needs internet, about a minute).

```powershell
php artisan db:seed --class=DemoActivitySeeder
php artisan megamall:admin
php artisan optimize:clear
```

`migrate:fresh` deleted your admin account, so `megamall:admin` creates it again.

## 3. Start both parts

```powershell
# terminal 1
cd C:\Users\AIO\Projects\megamall\megamall-api
php artisan serve

# terminal 2
cd C:\Users\AIO\Projects\megamall\megamall-web
npm run dev
```

- Shop: http://localhost:3000
- Admin: http://127.0.0.1:8000/admin

## If the photos say "could not download"

PHP on your PC is missing its SSL certificate list. The shop still shows the photos (it links to
them online), but the admin product edit page won't show them. To fix it properly:

1. Download https://curl.se/ca/cacert.pem and save it as `C:\tools\php84\cacert.pem`.
2. Open `C:\tools\php84\php.ini` and set both lines (remove the `;` in front):
   ```ini
   curl.cainfo = "C:\tools\php84\cacert.pem"
   openssl.cafile = "C:\tools\php84\cacert.pem"
   ```
3. Close PowerShell, open a new one, and run step 2 again.

## Things to show the client

1. **Home page**: scrolling offer bar, hero slider, Flash deals and Best sellers rows, Kitchen week badges.
2. **Product page**: photo, sizes on clothes and shoes, Buy now, Order on WhatsApp, reviews.
3. **Checkout in Dar es Salaam**: green "Pay on delivery" box.
4. **Checkout in Mwanza**: blue "Pay before delivery", then the mobile money page turning paid by itself.
5. **Admin dashboard**: earnings, order statistics, sales chart, latest orders.
6. **Online orders**: the To do tab, Confirm → Pack → Send out → Delivered.
7. **POS**: a counter sale with change and a printed receipt.
8. **Stock** and **Stock history**: every change explained.

Before the meeting, set your real phone and WhatsApp numbers in **Store settings**.
