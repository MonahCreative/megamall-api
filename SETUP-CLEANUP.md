# Mega Mall: clean-up (unused features removed)

This update removes the features you said Mega Mall does not use, so the admin is shorter and easier to learn.

## What was removed

| Removed | What replaces it |
|---|---|
| **POS**, **POS orders**, **Cash-up** (shop counter) | Nothing: Mega Mall sells online only. |
| **Purchases** and **Suppliers** | **Stock > Add stock** on each product: how many arrived, and (optional) the new cost price. |
| **Damages** | **Stock > Count correction**: enter what is really on the shelf and write the reason (e.g. "2 broken"). |
| **Reviews** (admin page, product page stars, review form) | Nothing. |
| **Return orders** (online return requests, "Return an item" page) | Returns are arranged on the phone or WhatsApp. On the order, **Refund customer** records the money given back (now also for delivered orders). Put the item back with **Stock > Add stock** (note: "Customer return, order MM-…"). Old links to /returns open the Return policy. |
| **Customer SMS** and **SMS outbox** (no SMS account was ever connected; nothing was sent) | Staff call customers, as they already do. Staff still get email and WhatsApp alerts. |
| **Top bar messages** setting (not shown in the shop) | Nothing. |
| **AzamPay automatic payment** (never approved) | Lipa Namba only, as today. |
| **Category image** and **Inside category** (sub-categories) | Nothing: the shop showed neither. |

Also changed:
- **Back-in-stock requests**: when a product a customer waits for is restocked, staff get a **WhatsApp** with the numbers (instead of an SMS to the customer). On the page, **WhatsApp** opens a ready message to the customer, then **Mark contacted**.
- **Reorder list**: "Last bought from" (suppliers) became **Waiting** (customers waiting for the product).
- **Sales reports**: online orders only (the "Online / Shop" filter is gone).
- Menu group **Orders & POS** is now **Orders**; **Regions and fees** is now **Regions**; **Returns & refunds** is now **Refunds**.
- **Store settings**: the SMS, top bar, reviews and AzamPay parts are gone. **Delivery** is now the note printed on invoices. **Returns** keeps the number of days (shown in the shop footer and on invoices).
- Shop texts no longer mention SMS. Starter **Shop pages** (Terms, Privacy, Return policy, Contact) are updated: free delivery in Dar, returns by phone/WhatsApp. Pages you already rewrote yourselves are left alone.

Nothing is deleted from the database except the unused settings. Old orders, payments, refunds and stock history stay.
The old tables (cash-ups, purchases, reviews, returns, SMS log) stay too, unused. Old counter (POS) sales stay in the orders table but are no longer shown; see "Later" at the end.

---

## Install on your computer

### 1. Save your work first (both projects)

```powershell
cd C:\Users\AIO\Projects\megamall\megamall-api
git status
git add .
git commit -m "Before clean-up"
git push

cd C:\Users\AIO\Projects\megamall\megamall-web
git status
git add .
git commit -m "Before clean-up"
git push
```

If `git status` shows files you changed and never pushed, tell Claude before continuing (see risk 1).

### 2. API and admin

1. Extract `megamall-api-cleanup.zip` into `C:\Users\AIO\Projects\megamall\megamall-api` and choose **Replace the files in the destination**.
2. Delete the removed files (the script lists every file it deletes, then deletes itself):

   ```powershell
   cd C:\Users\AIO\Projects\megamall\megamall-api
   powershell -ExecutionPolicy Bypass -File .\remove-unused-files.ps1
   ```
3. Update the database and caches, then run the tests:

   ```powershell
   php artisan migrate
   php artisan optimize:clear
   php artisan test
   ```
   All tests must pass. If any fails, copy the red part of the output and send it to Claude.

### 3. Shop

1. Extract `megamall-web-cleanup.zip` into `C:\Users\AIO\Projects\megamall\megamall-web` (Replace).
2. Delete the removed files and check the code:

   ```powershell
   cd C:\Users\AIO\Projects\megamall\megamall-web
   powershell -ExecutionPolicy Bypass -File .\remove-unused-files.ps1
   npx tsc --noEmit
   npm run lint
   ```

### 4. Try it (php artisan serve and npm run dev running)

- Admin menu: no POS, Cash-up, Purchases, Suppliers, Damages, Reviews, Return orders, SMS outbox.
- **Stock**: open a product, **Add stock** 2 with a cost price. The stock goes up and **Stock history** shows "Stock received".
- **Store settings**: save once. Check the **Delivery** note (it is printed on invoices) and your **Lipa Namba**.
- Shop: a product page (no reviews section), checkout to an upcountry region (Lipa Namba), **Track order**, the footer and the phone menu (no "Return an item"; "Return policy" instead).
- **Shop pages** in the admin: read Terms and Return policy once.

### 5. Commit and put it live

```powershell
cd C:\Users\AIO\Projects\megamall\megamall-api
git add -A
git commit -m "Remove unused features: POS, purchases, damages, reviews, returns, SMS, AzamPay"
git push

cd C:\Users\AIO\Projects\megamall\megamall-web
git add -A
git commit -m "Remove reviews, online returns and automatic payment from the shop"
git push
```

On the server, at a quiet time (e.g. after 21:00), **update the shop first, then the API**:

```bash
cd /var/www/megamall-web && git pull --ff-only && npm ci && npm run build && sudo systemctl restart megamall-web
bash /var/www/megamall-api/deploy/deploy.sh
```

`deploy.sh` makes a database backup, updates the API (including `migrate`), then rebuilds the shop again (no harm).

Optional, on the server: the `.env` lines `PAYMENT_DRIVER`, `AZAMPAY_…` and `SMS_…` are no longer read. You can delete them.

---

## Risks and how to avoid them

1. **Overwriting changes that are not on GitHub.** The zips are made from the code on GitHub. If your computer has newer, unpushed changes in the same files, extracting replaces them.
   *Avoid:* step 1 (commit and push first). After extracting, `git diff` shows exactly what changed; `git checkout -- <file>` undoes one file.

2. **Shop and API out of step for a few minutes.** The old shop expects reviews and returns from the API; with the new API it would show an error on product and Track pages until the new shop is built.
   *Avoid:* update the **shop first**, then the API, as in step 5. The new shop works with both the old and new API.

3. **Staff habits.** Things now done differently:
   - New goods: **Stock > Add stock** (not Purchases). Without a cost price, profit reports use the old cost.
   - Broken or lost items: **Stock > Count correction** with the reason.
   - Returns: agree with the customer on WhatsApp, then **Refund customer** on the order and **Add stock** for the item.
   - Lipa Namba: after **Confirm payment** or **Payment not found**, staff **call the customer** (no SMS goes out).
   - Back-in-stock: staff must have their WhatsApp in **Store settings > Staff alerts** to be told; otherwise check **Back-in-stock requests** daily.
   *Avoid:* show staff this list once; keep this file in the project.

4. **Shop pages you edited yourselves** are not changed by the update. If your Terms still say the customer pays the rider in Dar, or mention SMS, edit them under **Shop pages**.

5. **Reports look different.** Sales reports now count online orders only. If old demo data with counter (POS) sales is still in the live database, those sales disappear from the reports (they were not real).

6. **A test fails on your computer.** The update was checked line by line, but the full test suite needs PHP 8.4, which was not available where it was prepared.
   *Avoid:* run `php artisan test` before pushing (step 2) and send any failure to Claude.

7. **Something breaks after going live.**
   *Undo:* on your computer `git revert HEAD` in each project, push, and run the same server commands. The database change is small (it only deletes unused settings and rewords starter pages), and `deploy.sh` saved a backup before it ran.

## Later (optional, after a month without problems)

The unused tables can be dropped to tidy the database: `cash_ups`, `purchases`, `purchase_items`, `suppliers`, `damages`, `reviews`, `return_requests`, `return_items`, `sms_messages`. Ask Claude for that migration when you are ready. It is not needed for the shop to work.
