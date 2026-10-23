# Sharing previews, saved items, back-in-stock SMS, reorder list, AzamPay, launch kit

## Install

1. Stop both servers (Ctrl+C).
2. Extract `megamall-api-extras.zip` into `C:\Users\AIO\Projects\megamall\megamall-api` (Replace).
3. Extract `megamall-web-extras.zip` into `C:\Users\AIO\Projects\megamall\megamall-web` (Replace).
4. In `megamall-api`:

```powershell
php artisan migrate
php artisan db:seed --class=SettingSeeder
php artisan optimize:clear
php artisan test
```

`php artisan test` should show **70 passed**.

5. In `megamall-web\.env.local` add (or check) the shop's own address:
   `NEXT_PUBLIC_SITE_URL=http://localhost:3000`
6. Start `php artisan serve` and `npm run dev` again.

### Important: the shop now runs on Tanzania time

Until now Laravel used UTC (3 hours behind), so order times, receipts and "today" in reports were off.
This update includes `config/app.php` set to `Africa/Dar_es_Salaam`. Orders already in your local database
were saved in UTC and will now look 3 hours early. Easiest fix for the demo: rebuild it:

```powershell
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoActivitySeeder
php artisan megamall:admin
```

## What's new where

| What | Where |
|---|---|
| Heart (save for later) on every product; **Saved items** page | Shop: heart in the header, or the menu on small phones |
| Recently viewed row | Shop: home page and product pages |
| "SMS me when it's back" | Shop: product page of a sold-out product or size |
| Back-in-stock requests | Admin: Products & Stock › Back-in-stock requests |
| Reorder soon | Admin: Dashboard |
| Reorder list + Excel | Admin: Reports › Reorder list |
| Reorder levels | Admin: Store settings › Stock alerts, or on each product |
| WhatsApp/Facebook previews, Google sitemap | Automatic: `/sitemap.xml`, `/robots.txt` |
| Going live | `megamall-api\deploy\LAUNCH-CHECKLIST.md` |

## Testing AzamPay (sandbox) on your PC

1. Create a sandbox app at https://developers.azampay.co.tz and copy its details into `megamall-api\.env`:

```
PAYMENT_DRIVER=azampay
AZAMPAY_ENVIRONMENT=sandbox
AZAMPAY_APP_NAME=...
AZAMPAY_CLIENT_ID=...
AZAMPAY_CLIENT_SECRET=...
AZAMPAY_API_KEY=...
AZAMPAY_CALLBACK_KEY=any-long-random-text
```

2. `php artisan optimize:clear`, then check the credentials work:
   `php artisan payments:azampay-check 0712345678 --amount=1000 --channel=mpesa`
3. Place an upcountry order in the shop and press **Send payment request**.
4. AzamPay reports the result by calling your server, which it cannot reach on a PC. Play that call yourself:
   `php artisan payments:simulate-callback` (add `--fail` to test a declined payment).
   The shop's payment page turns paid by itself.

To go back to the pretend payments: `PAYMENT_DRIVER=fake`.
