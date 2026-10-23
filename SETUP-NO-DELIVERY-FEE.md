# Changes: no delivery fee, Buy now, cart, website colours

## What changed
- **No delivery fee.** Order totals are the items only. Checkout, order pages, the cart and the footer say
  delivery is paid to the rider (Dar es Salaam) or the bus company (other regions) directly.
  Upcountry customers pay the items by mobile money; the bus fare is paid on collection.
- **Admin:** Regions no longer have fee fields (only payment rule, delivery days and districts).
  Store settings has a new **Delivery** box with the note customers see. Old orders that had a fee still show it.
- Also included: Buy now on product cards, Add to cart without opening the side cart, website colours.

## Install
1. Stop both servers.
2. Extract `megamall-api-update.zip` into `megamall-api` (Replace).
3. Extract `megamall-web-update.zip` into `megamall-web` (Replace).
4. In `megamall-api`:
   ```powershell
   php artisan optimize:clear
   php artisan test
   ```
   No migration or reseeding is needed. Old fee numbers in the regions table are simply ignored.
5. Start `php artisan serve` and `npm run dev` again.
