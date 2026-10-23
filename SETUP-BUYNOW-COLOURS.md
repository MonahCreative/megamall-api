# Buy now on product cards + website colours from the admin

## What changed
- **Shop:** every product card has a **Buy now** button that goes straight to checkout with just that item.
  The bag icon next to it still adds to the cart. Buy now never touches the cart, so anything already in
  the cart stays there for later. Items with sizes/colours still say **Choose an option** (a size is needed first).
  The **Buy now** on the product page now works the same way.
- **Admin:** Store settings has a new **Website colours** section: main colour, dark background and sale colour,
  plus ready-made sets (orange, green, blue, red, purple, teal). The admin panel follows the main colour too.
  Colours too light for white button text are refused.

## Install
1. Stop both servers (Ctrl+C in each terminal).
2. Extract `megamall-api-buynow-colours.zip` into `C:\Users\AIO\Projects\megamall\megamall-api` (Replace).
3. Extract `megamall-web-buynow-colours.zip` into `C:\Users\AIO\Projects\megamall\megamall-web` (Replace).
4. In `megamall-api`:
   ```powershell
   php artisan optimize:clear
   php artisan test
   ```
   No migration or reseeding is needed: until you save colours, the shop uses the original orange.
5. Start `php artisan serve` (api) and `npm run dev` (web) again.

## Try it
- Shop: click **Buy now** on a card, change the quantity at checkout, place the order. Your cart is unchanged.
- Admin: **Store settings > Website colours**, pick "Fresh green", **Save settings**, then reload the shop.
