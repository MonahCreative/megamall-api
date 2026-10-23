<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use Illuminate\Database\Seeder;

/**
 * First drafts of the shop's information pages. Edit them in the admin under Store > Shop pages.
 * {store_name}, {phone}, {whatsapp}, {email} and {return_window_days} are filled in from Store settings.
 *
 * These are plain-language drafts, not legal advice: have the client (or their lawyer) check them before launch.
 */
class ContentPageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'about-us',
                'title' => 'About us',
                'body' => <<<'HTML'
<p><strong>{store_name}</strong> is an online shop for everyday needs: fashion, phones and electronics, home and kitchen, furniture, beauty, groceries and more, delivered across Tanzania.</p>
<h2>Why shop with us</h2>
<ul>
<li><strong>Pay on delivery in Dar es Salaam.</strong> Check your item first, then pay the rider in cash or by mobile money.</li>
<li><strong>Delivery to all 31 regions.</strong> Outside Dar es Salaam you pay by M-Pesa, Mixx by Yas, Airtel Money or HaloPesa, and we send your parcel by bus.</li>
<li><strong>Real people to talk to.</strong> Call or WhatsApp us on {phone} before or after you buy.</li>
<li><strong>Easy returns.</strong> If something is wrong, you can ask for a return within {return_window_days} days of delivery.</li>
</ul>
<p>Asante for shopping with us.</p>
HTML,
            ],
            [
                'slug' => 'contact-us',
                'title' => 'Contact us',
                'body' => <<<'HTML'
<p>We are happy to help with orders, deliveries, payments and returns.</p>
<ul>
<li><strong>Phone:</strong> {phone}</li>
<li><strong>WhatsApp:</strong> {whatsapp}</li>
<li><strong>Email:</strong> {email}</li>
</ul>
<p><strong>Opening hours:</strong> Monday to Saturday, 8:00 to 20:00. Sunday, 10:00 to 16:00.</p>
<p>If you are asking about an order, please have your order number ready (it starts with MM and is in your SMS).</p>
HTML,
            ],
            [
                'slug' => 'return-policy',
                'title' => 'Return policy',
                'body' => <<<'HTML'
<p>We want you to be happy with what you buy. If something is not right, you can ask for a return within <strong>{return_window_days} days</strong> of delivery.</p>
<h2>You can return an item if</h2>
<ul>
<li>it arrived damaged, broken or not working;</li>
<li>it is not what you ordered (wrong item, size or colour);</li>
<li>it is unused, in its original packaging, with all accessories, and you changed your mind.</li>
</ul>
<h2>We cannot accept returns of</h2>
<ul>
<li>food, drinks and other groceries, unless they arrived damaged or expired;</li>
<li>opened beauty and personal care products, and underwear, for hygiene reasons;</li>
<li>items damaged after delivery through misuse or accidents.</li>
</ul>
<h2>How to return</h2>
<ol>
<li>Go to <strong>Return an item</strong> on our website, enter your order number and phone number, and choose what you are returning and why. You can also call or WhatsApp us on {phone}.</li>
<li>We review your request and contact you to arrange how the item comes back to us.</li>
<li>When we receive and check the item, we give you a replacement or refund your money.</li>
</ol>
<h2>Refunds</h2>
<p>Refunds are sent by mobile money (or in cash at our shop) for the price you paid for the returned items. If the item was faulty or we sent the wrong one, we also cover the cost of sending it back. If you changed your mind, the transport cost of the return is yours.</p>
HTML,
            ],
            [
                'slug' => 'terms-and-conditions',
                'title' => 'Terms and conditions',
                'body' => <<<'HTML'
<p>These terms apply when you buy from {store_name}. By placing an order you agree to them.</p>
<h2>1. Orders</h2>
<p>When you place an order we send you an order number by SMS. We may call you to confirm it. We can cancel an order if an item is out of stock, if the price shown was clearly wrong, or if we cannot reach you to confirm. If you already paid for a cancelled order, we refund you in full.</p>
<h2>2. Prices</h2>
<p>Prices are in Tanzanian shillings (TZS) and include VAT where it applies. The price you pay is the one shown at checkout when you place the order.</p>
<h2>3. Payment</h2>
<ul>
<li><strong>Dar es Salaam:</strong> you pay when your order is delivered, in cash or by mobile money, after checking the item.</li>
<li><strong>Other regions:</strong> you pay the full price by mobile money (M-Pesa, Mixx by Yas, Airtel Money or HaloPesa) before we send the order. Orders not paid within the time shown at checkout are cancelled automatically.</li>
</ul>
<h2>4. Delivery</h2>
<p>Delivery costs are not included in our prices. In Dar es Salaam you pay the rider directly. For other regions we send your parcel by bus, and you pay the transport cost when you collect it. Delivery times are estimates and can be affected by weather, roads or the bus company.</p>
<p>Please check your item when you receive it. Once you have accepted delivery, or collected the parcel from the bus office, the item is your responsibility.</p>
<h2>5. Returns and refunds</h2>
<p>See our <strong>Return policy</strong>. Your rights under Tanzanian consumer protection law are not affected.</p>
<h2>6. Product information</h2>
<p>We describe products and show photos as accurately as we can. Small differences in colour or packaging can happen.</p>
<h2>7. Contact</h2>
<p>Questions about these terms: call {phone} or email {email}.</p>
HTML,
            ],
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy policy',
                'body' => <<<'HTML'
<p>This explains what personal information {store_name} collects, why, and how we protect it, in line with Tanzania's Personal Data Protection Act, 2022.</p>
<h2>What we collect</h2>
<ul>
<li>Your name, phone number and delivery address, so we can deliver your order and contact you about it.</li>
<li>Your email address, if you give it.</li>
<li>Your order history and payment details (the mobile money number used and the transaction reference). We never see or store your mobile money PIN.</li>
</ul>
<h2>How we use it</h2>
<ul>
<li>To process, deliver and support your orders, including SMS updates about your order.</li>
<li>To handle returns, refunds and questions.</li>
<li>To keep records required by law, such as tax records.</li>
</ul>
<p>We do not sell your information.</p>
<h2>Who we share it with</h2>
<p>Only with the people needed to complete your order: our riders and the bus company carrying your parcel (name, phone, delivery area), the mobile money and payment providers that process your payment, and the SMS provider that sends your order updates. We may also share information when the law requires it.</p>
<h2>How long we keep it</h2>
<p>We keep order records for as long as the law requires for business and tax records, and delete or anonymise information we no longer need.</p>
<h2>Your rights</h2>
<p>You can ask to see the information we hold about you, ask us to correct it, or ask us to delete it where we are not required to keep it. Contact us on {phone} or {email}.</p>
HTML,
            ],
        ];

        foreach ($pages as $order => $page) {
            // Never overwrite a page that was already edited in the admin.
            ContentPage::firstOrCreate(
                ['slug' => $page['slug']],
                ['title' => $page['title'], 'body' => $page['body'], 'is_published' => true, 'show_in_footer' => true, 'sort_order' => $order + 1],
            );
        }
    }
}
