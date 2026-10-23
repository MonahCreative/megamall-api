# Reports, cash-up, notifications, shop pages, Excel import, printing

No new packages are needed (the Excel library already comes with Filament).

## Install

1. Stop both servers (Ctrl+C).
2. Extract `megamall-api-reports.zip` into `C:\Users\AIO\Projects\megamall\megamall-api` and choose **Replace**.
3. Extract `megamall-web-reports.zip` into `C:\Users\AIO\Projects\megamall\megamall-web` and choose **Replace**.
4. In `megamall-api`:

```powershell
php artisan migrate
php artisan db:seed --class=SettingSeeder
php artisan db:seed --class=ContentPageSeeder
php artisan optimize:clear
php artisan test
```

- `migrate` adds the new tables and fills in the cost of items already sold (from today's cost prices), so profit works for old orders too.
- The two seeders only ADD what is missing: they never overwrite settings or pages you already changed.
- `php artisan test` should show **58 passed**.

5. Start `php artisan serve` and `npm run dev` again.

Optional, for the client demo: rebuild the demo data so the SMS outbox, reports and cash-ups all have content:

```powershell
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoActivitySeeder
php artisan megamall:admin
```

## Where to find everything in the admin

| What | Where |
|---|---|
| Sales reports + Export to Excel | **Reports > Sales reports** |
| End-of-day cash-up | **Orders & POS > Cash-up** (also the "Cash up a day" button on the POS screen) |
| SMS wording, on/off | **Store settings > Customer SMS** |
| Staff email alerts, pop-up and chime | **Store settings > Staff alerts** |
| Messages sent / saved | **Store > SMS outbox** |
| Terms, Privacy, Return policy, About, Contact | **Store > Shop pages** |
| Import / export products | **Products > Excel** button |
| Invoice, packing slip | Open an online order > **Invoice** / **Packing slip** buttons |
| Labels for many orders | **Online orders** > tick orders > **Print packing slips** |
| Address and TIN on invoices | **Store settings > Shop details** |

## Good to know

- **SMS are not sent to phones yet.** With `SMS_DRIVER=log` (the default) each message is saved in the SMS outbox
  so you can read exactly what customers will get. When the client has an SMS account (Beem, NextSMS...),
  we add one small connector file and set `SMS_DRIVER` in `.env`.
- **Staff emails** go to `storage\logs\laravel.log` while `.env` has `MAIL_MAILER=log`. Add real mail settings
  (e.g. the client's email provider) to send them for real.
- **Chime:** browsers only allow sound after you click the admin page once.
- **Printing:** documents open in a new tab. Use **Print / Save as PDF** for a PDF. Labels are 100 x 150 mm
  (standard parcel label printers); on A4 paper they print one per page. If the browser blocks the
  "Print packing slips" tab, allow pop-ups for the admin site.
- **Invoices are not TRA EFD receipts.** If the shop is VAT-registered, it still needs its EFD/VFD receipts.
- **Shop pages are drafts**: have the client (or their lawyer) read Terms, Privacy and Return policy before launch.
