# Mega Mall Tanzania: Backend, Step 1 (Windows)

This step gives you the Laravel API: database, sample products in 12 categories,
all 31 regions with delivery fees, and the checkout rule
(Dar es Salaam = pay on delivery, every other region = mobile money first).

## 1. Install the tools (once)

1. **Laravel Herd** (PHP, Composer, Node): https://herd.laravel.com/windows
   Install, open it once, finish the onboarding.
2. **DBngin** (free MySQL server): https://dbngin.com
   Open it, click **+**, choose **MySQL 8**, keep port **3306**, click **Create**, then **Start**.
   (If you already use XAMPP, you can start MySQL from XAMPP instead.)
3. **HeidiSQL** (optional, to look inside the database): https://www.heidisql.com
4. **VS Code**: https://code.visualstudio.com
5. **Git**: https://git-scm.com/download/win

Close and reopen PowerShell after installing, then check:

```powershell
php -v
composer -V
node -v
```

## 2. Create the database

In HeidiSQL (or any MySQL tool) connect to `127.0.0.1`, user `root`, empty password, and run:

```sql
CREATE DATABASE megamall CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 3. Create the Laravel project

```powershell
cd ~\Herd
laravel new megamall-api
```

When the installer asks:
- Starter kit: **None**
- Testing framework: **PHPUnit**
- Database: **MySQL**
- Run migrations: **No**
- npm install: **No**

Then add the API layer (installs Sanctum and creates routes/api.php):

```powershell
cd megamall-api
php artisan install:api
```

If it asks to run migrations, answer **No**.

## 4. Copy in the Mega Mall files

Extract `megamall-api-step1.zip` **into** `C:\Users\<you>\Herd\megamall-api`.
When Windows asks, choose **Replace the files in the destination**.

## 5. Settings (.env)

Open the project in VS Code (`code .`) and set these lines in `.env`:

```ini
APP_NAME="Mega Mall Tanzania"
APP_URL=http://megamall-api.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=megamall
DB_USERNAME=root
DB_PASSWORD=

PAYMENT_DRIVER=fake
FAKE_PAYMENT_SECONDS=6
```

Also open `config/app.php`, find the `'timezone'` line and set it to:

```php
'timezone' => 'Africa/Dar_es_Salaam',
```

## 6. Build the database and sample data

```powershell
php artisan storage:link
php artisan migrate --seed
```

## 7. Check it works

Run the tests (they use a temporary database, your data is safe):

```powershell
php artisan test
```

You should see all CheckoutTest tests pass.

Then open these in your browser (Herd serves the project automatically):

- http://megamall-api.test/api/store
- http://megamall-api.test/api/categories
- http://megamall-api.test/api/products
- http://megamall-api.test/api/products?category=fashion
- http://megamall-api.test/api/regions
- http://megamall-api.test/api/banners

## 8. Save your work with Git

```powershell
git init
git add .
git commit -m "Step 1: Mega Mall API, catalogue and checkout rules"
```

## API summary

| Method | URL | What it does |
|---|---|---|
| GET | /api/store | Store name, phone, WhatsApp, ticker text, payment channels |
| GET | /api/categories | Categories with product counts |
| GET | /api/products | Product list. Filters: `category`, `q`, `sort` (newest, price_asc, price_desc, popular, discount), `featured`, `page` |
| GET | /api/products/{slug} | Product page with images, sizes and related products |
| GET | /api/banners | Hero slider |
| GET | /api/regions | Regions, districts, delivery fees and payment rule |
| POST | /api/checkout/quote | Totals for a cart and region (saves nothing) |
| POST | /api/orders | Place an order. Response `next_step` is `done` (Dar) or `pay` (other regions) |
| POST | /api/orders/{number}/pay | Send mobile money PIN prompt (`token`, `channel`, `phone`) |
| GET | /api/orders/{number}/payment-status?token= | Checkout page polls this until paid |
| POST | /api/orders/track | Track order with `order_number` + `phone` |
| POST | /api/payments/callback/{driver} | Webhook for the payment provider |

## Testing payments locally

`PAYMENT_DRIVER=fake` means no money moves. A payment turns **paid** 6 seconds after it starts.
To test a failed payment, use a paying number ending in **999** (e.g. 0712 345 999).

Unpaid mobile money orders are cancelled after 60 minutes and their stock is released.
To run that job on your PC, keep this running in a second terminal:

```powershell
php artisan schedule:work
```

## Where the rules live

- Payment rule per region: `regions.payment_mode` (`cod` or `prepaid`). Set in `database/seeders/RegionSeeder.php`, editable in the admin panel from Step 2.
- Delivery fees: `regions.delivery_fee`, with per-district fees for Dar es Salaam.
- Checkout maths and stock: `app/Services/OrderService.php`.
- Mobile money: `app/Services/Payments/`.
