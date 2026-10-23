# Mega Mall Tanzania: store modules (POS, stock, returns, promotions, reviews, sections)

Run everything in `C:\Users\AIO\Projects\megamall\megamall-api`.

## Install

1. Stop `php artisan serve` (Ctrl+C) and save your work:

   ```powershell
   git add .
   git commit -m "Before store modules"
   ```

2. Extract `megamall-api-modules.zip` into the project folder and choose **Replace the files in the destination**.

3. Run these one at a time:

   ```powershell
   php artisan migrate
   php artisan db:seed --class=SettingSeeder
   php artisan db:seed --class=HomeSectionSeeder
   php artisan megamall:opening-stock
   php artisan optimize:clear
   php artisan test
   ```

   - `migrate` adds the new tables. Your products, orders and settings stay.
   - The two seeders add the new settings and four starter home page sections. They never overwrite what you already changed.
   - `megamall:opening-stock` writes today's stock levels into the new stock history, so every number is explained from now on.

4. Start the server again with `php artisan serve` and open http://127.0.0.1:8000/admin.

## The new menu

**Orders & POS**
- **POS**: the shop counter. Tap products (or search by name/SKU), set a discount, choose cash or mobile money, enter cash received to see the change, and complete the sale. A receipt opens ready to print (sized for 80mm receipt printers).
- **POS orders**: every counter sale, with reprint and "Start a return".
- **Online orders**: the shop orders from before (Dar pay-on-delivery / other regions pay first).
- **Return orders**: customer return requests. Approve → Items received (tick to put them back in stock) → Refund. Or Reject with a reason.
- **Returns & refunds**: every refund given, for returns and for paid orders that were cancelled.
- **Payments**: every payment.

**Products & Stock**
- **Products**, **Categories** as before. Stock is now set only when you create a product (opening stock). After that it changes through purchases, sales, damages, returns or a count correction, so it always adds up.
- **Purchases**: record goods received from a supplier. Stock goes up and the product's cost price updates.
- **Suppliers**: who you buy from, with totals.
- **Damages**: broken, expired, lost or stolen items. Stock goes down and the loss at cost is shown.
- **Stock**: every product's stock and value at cost, low-stock filter, **Count correction** after a shelf count, and **History** per product.
- **Stock history**: every stock change ever, with who and why.
- **Reviews**: customers who received an order can rate the product. You publish, hide or reply. The menu shows how many are waiting.

**Promo**
- **Promotions**: percentage or amount off, for chosen products, whole categories or everything, between two dates. Shop prices, the checkout and the POS all use the promotion price automatically while it runs, and the product shows the badge and the old price crossed out.
- **Product sections**: the rows on the home page (Flash deals, Best sellers, New arrivals, a category, or products you pick). Drag to reorder.
- **Home page slider**: as before.

**Store settings** now also has the return window (default 7 days) and whether reviews publish immediately.

## New shop API (used by the storefront in the next step)

| Method | URL | What it does |
|---|---|---|
| GET | /api/sections | Home page sections with their products |
| GET | /api/products/{slug} | Now includes `reviews` (summary + latest) and promotion prices |
| POST | /api/products/{slug}/reviews | `order_number`, `phone`, `rating` 1-5, `comment` |
| GET | /api/returns/reasons | Options for the return form |
| POST | /api/returns | `order_number`, `phone`, `reason`, `details`, `items: [{order_item_id, quantity}]` |

Product lists now include `rating`, `review_count` and `promotion`.
