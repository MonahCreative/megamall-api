# Mega Mall Tanzania: Step 2, the admin panel

After this step you can log in at http://127.0.0.1:8000/admin and run the shop:
products with photos and videos, categories, the home page slider, regions and
delivery fees, orders, payments and store settings.

Work in `C:\Users\AIO\Projects\megamall\megamall-api` for every command below.

## 1. Save Step 1 first

```powershell
git add .
git commit -m "Step 1 done"
```

(If you haven't run `git init` yet, run it before these two lines.)

## 2. Install Filament

```powershell
composer require filament/filament:"^5.0"
php artisan filament:install --panels
```

When it asks for the panel ID, type **admin** and press Enter.
If it asks anything else, accept the default.

## 3. Copy in the Step 2 files

Extract `megamall-api-step2.zip` into the project folder and choose
**Replace the files in the destination**.
It replaces `app/Models/User.php`, `app/Models/Order.php`, the three files in `app/Enums`,
and `app/Providers/Filament/AdminPanelProvider.php`, and adds the admin pages.

## 4. Add one line to .env

```ini
FRONTEND_URL=http://localhost:3000
```

(This is where the shop itself will run in Step 3. The admin panel uses it for "View in shop" links.)

## 5. Update the database

```powershell
php artisan migrate
```

This adds an `is_admin` column to users. Your products and regions stay as they are.

## 6. Create your admin login

```powershell
php artisan megamall:admin
```

Enter your email, name and a password (at least 8 characters).
Only accounts created this way can open the admin panel. Customers can never get in.

## 7. Test and open it

```powershell
php artisan optimize:clear
php artisan test
php artisan serve
```

All tests should pass (Step 1 tests plus the new admin and order workflow tests).
Then open http://127.0.0.1:8000/admin and log in.

## 8. Save

```powershell
git add .
git commit -m "Step 2: admin panel"
```

## What's in the admin panel

**Sales**
- **Orders**: opens on the "To do" tab: new Dar orders to confirm, and orders to pack and send.
  The menu shows how many need action. Each order has buttons for the next step only:
  - Dar es Salaam (pay on delivery): Confirm → Start packing → Send out → Delivered (tick "Rider collected" to record the cash).
  - Other regions: the order waits in the "Awaiting payment" tab until mobile money arrives, then moves to "To do"
    as Confirmed → Start packing → Send out → Delivered. "Send out" is hidden until the order is paid.
  - **Record payment**: for a customer who paid to your Lipa number directly.
  - **WhatsApp customer**: opens a chat with the order number already written.
  - **Cancel order**: puts the stock back. If it was paid, you refund the customer yourself.
- **Payments**: every payment attempt, with transaction IDs.

**Catalogue**
- **Products**: name, category, price, old price (shows the discount badge), stock, photos (drag to reorder, first is main),
  video link, sizes/colours with their own stock.
- **Categories**: drag to reorder, sub-categories supported. A category with products can't be deleted.
- **Home page slider**: pick a product and the title and link fill in for you.

**Delivery**
- **Regions and fees**: change a region's fee, delivery days, or payment rule (pay on delivery / pay first),
  and add districts with their own fees.

**Store**
- **Store settings**: shop name, phone, WhatsApp number, the scrolling top-bar messages,
  and how long customers outside Dar have to pay.

## If something goes wrong

- Page shows "403 Forbidden" after login: that account isn't an admin. Run `php artisan megamall:admin` with the same email.
- Photos don't show: make sure you ran `php artisan storage:link` in Step 1, and that `APP_URL=http://127.0.0.1:8000`.
- Any red error page: copy the error title and the first lines, and send them to me.
