<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Rules\TanzanianPhone;
use App\Services\StaffWhatsApp;
use App\Support\LipaNamba;
use App\Support\Phone;
use App\Support\Theme;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class StoreSettings extends Page
{
    protected static string | UnitEnum | null $navigationGroup = 'Store';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Store settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Store settings';

    /** Keys from the settings table that this page edits. */
    private const KEYS = [
        'store_name', 'tagline', 'phone', 'whatsapp', 'email', 'prepaid_order_expiry_minutes',
        'return_window_days', 'delivery_note', 'theme_primary', 'theme_dark', 'theme_sale',
        'staff_alert_emails', 'staff_alert_sound', 'staff_whatsapp', 'business_address', 'tin',
        'low_stock_level', 'reorder_cover_days', 'lipa_numbers', 'lipa_note',
    ];

    private const BOOLEANS = ['staff_alert_sound'];

    /** @var array<string, mixed> | null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'staff_alert_sound' => true,
            'low_stock_level' => 5,
            'reorder_cover_days' => 30,
            'lipa_note' => LipaNamba::DEFAULT_NOTE,
            ...collect(Setting::allValues())->only(self::KEYS)->all(),
            ...Theme::colours(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Shop details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('store_name')->required()->maxLength(80),
                        TextInput::make('tagline')->maxLength(150),
                        TextInput::make('phone')
                            ->label('Customer care phone (as shown on the site)')
                            ->placeholder('+255 712 345 678')
                            ->required(),
                        TextInput::make('whatsapp')
                            ->label('WhatsApp number')
                            ->helperText('International format without + or spaces, e.g. 255712345678')
                            ->regex('/^255[67]\d{8}$/')
                            ->required(),
                        TextInput::make('email')->email(),
                        TextInput::make('business_address')
                            ->label('Business address (shown on invoices)')
                            ->placeholder('Plot 12, Kariakoo, Dar es Salaam')
                            ->maxLength(200),
                        TextInput::make('tin')
                            ->label('TIN (shown on invoices, optional)')
                            ->placeholder('123-456-789')
                            ->maxLength(30),
                    ]),
                Section::make('Staff alerts')
                    ->description('How staff hear about new orders and Lipa Namba payments to check.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('staff_alert_emails')
                            ->label('Email these people')
                            ->placeholder('orders@megamall.co.tz, manager@gmail.com')
                            ->helperText('Separate several addresses with commas. Leave empty for no emails.')
                            ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail) {
                                foreach (preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) as $email) {
                                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                        $fail("\"{$email}\" is not a valid email address.");
                                    }
                                }
                            }]),
                        Toggle::make('staff_alert_sound')
                            ->label('Pop-up and chime in the admin')
                            ->helperText('Checked every 20 seconds on any open admin page. Click the page once after opening it so the browser allows sound.')
                            ->inline(false),
                        Repeater::make('staff_whatsapp')
                            ->label('WhatsApp these phones')
                            ->helperText(new HtmlString('Each phone activates itself once: save the CallMeBot number shown on <a href="https://www.callmebot.com/blog/free-api-whatsapp-messages/" target="_blank" rel="noopener" class="underline">callmebot.com</a> in its contacts, send it <strong>I allow callmebot to send me messages</strong> on WhatsApp, and copy the key it replies with. Save, then use <strong>Send test WhatsApp</strong> at the top of this page.'))
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('name')->label('Who')->placeholder('Shop manager')->maxLength(40),
                                TextInput::make('phone')->label('WhatsApp number')->placeholder('0712 345 678')->tel()->required()->rule(new TanzanianPhone),
                                TextInput::make('apikey')->label('CallMeBot key')->placeholder('123456')->required()->maxLength(40)->password()->revealable(),
                            ])
                            ->columns(3)
                            ->maxItems(5)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->addActionLabel('Add a WhatsApp number'),
                    ]),
                Section::make('Stock alerts')
                    ->description('Used by the dashboard "Reorder soon" list and Reports › Reorder list.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('low_stock_level')
                            ->label('Reorder when stock reaches')
                            ->helperText('For every product, unless the product has its own level.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->required(),
                        TextInput::make('reorder_cover_days')
                            ->label('Buy enough stock for (days)')
                            ->helperText('The suggested order quantity covers this many days of sales.')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->required(),
                    ]),
                Section::make('Delivery')
                    ->description('Printed at the bottom of every invoice. Leave empty to print nothing.')
                    ->schema([
                        Textarea::make('delivery_note')
                            ->label('Delivery note on invoices')
                            ->placeholder('Delivery is free in Dar es Salaam. Other regions: the bus fare is paid when you collect the parcel.')
                            ->rows(3)
                            ->maxLength(400),
                    ]),
                Section::make('Website colours')
                    ->description('Colours of the online shop. The admin panel follows the main colour too. Customers see the new colours the next time a page loads.')
                    ->columns(3)
                    ->schema([
                        Select::make('theme_preset')
                            ->label('Start from a ready-made set (optional)')
                            ->options(collect(Theme::PRESETS)->map(fn (array $preset) => $preset['label']))
                            ->placeholder('Keep my colours')
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                foreach (array_keys(Theme::DEFAULTS) as $key) {
                                    if ($state && isset(Theme::PRESETS[$state][$key])) {
                                        $set($key, Theme::PRESETS[$state][$key]);
                                    }
                                }
                            })
                            ->columnSpanFull(),
                        ColorPicker::make('theme_primary')
                            ->label('Main colour')
                            ->helperText('Buttons, prices, links and the top bar. Buttons have white text, so pick a strong, darker shade.')
                            ->hex()
                            ->required()
                            ->regex('/^#[0-9a-fA-F]{6}$/')
                            ->rules([self::readableWithWhiteText()]),
                        ColorPicker::make('theme_dark')
                            ->label('Dark background')
                            ->helperText('Home page slider and footer.')
                            ->hex()
                            ->required()
                            ->regex('/^#[0-9a-fA-F]{6}$/')
                            ->rules([self::readableWithWhiteText()]),
                        ColorPicker::make('theme_sale')
                            ->label('Sale colour')
                            ->helperText('Discount badges such as -20%.')
                            ->hex()
                            ->required()
                            ->regex('/^#[0-9a-fA-F]{6}$/')
                            ->rules([self::readableWithWhiteText()]),
                    ]),
                Section::make('Returns')
                    ->schema([
                        TextInput::make('return_window_days')
                            ->label('Returns accepted within (days of delivery)')
                            ->helperText('Shown in the shop footer and on invoices. Customers arrange returns on WhatsApp; staff refund from the order page.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(90)
                            ->default(7)
                            ->required(),
                    ]),
                Section::make('Mobile money orders')
                    ->description('Customers outside Dar es Salaam pay before their order is sent.')
                    ->schema([
                        Repeater::make('lipa_numbers')
                            ->label('Your Lipa Namba')
                            ->helperText('Shown to customers exactly as entered. Add one for each network you have a till number on. Until you add one, customers are asked to call you to pay.')
                            ->schema([
                                Select::make('channel')
                                    ->label('Network')
                                    ->options(config('payments.channels'))
                                    ->required(),
                                TextInput::make('number')
                                    ->label('Lipa Namba')
                                    ->placeholder('5123456')
                                    ->regex('/^\d{5,12}$/')
                                    ->validationMessages(['regex' => 'Digits only, as given by the network, e.g. 5123456.'])
                                    ->required(),
                                TextInput::make('name')
                                    ->label('Name customers see when paying')
                                    ->placeholder('MEGA MALL TANZANIA')
                                    ->maxLength(60)
                                    ->required(),
                            ])
                            ->columns(3)
                            ->maxItems(4)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->addActionLabel('Add a Lipa Namba'),
                        Textarea::make('lipa_note')
                            ->label('Note shown under the Lipa Namba')
                            ->rows(2)
                            ->maxLength(300)
                            ->required(),
                        TextInput::make('prepaid_order_expiry_minutes')
                            ->label('Cancel unpaid orders after (minutes)')
                            ->helperText('Customers outside Dar es Salaam must pay within this time, or the order is cancelled and the stock released.')
                            ->numeric()
                            ->minValue(10)
                            ->maxValue(1440)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    /** Rejects colours so light that white text on them can't be read. */
    private static function readableWithWhiteText(): Closure
    {
        return fn (): Closure => function (string $attribute, mixed $value, Closure $fail) {
            if (Theme::isHex($value) && Theme::contrastWithWhite($value) < 3) {
                $fail('This colour is too light: white text on it would be hard to read. Choose a darker shade.');
            }
        };
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save settings')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testWhatsApp')
                ->label('Send test WhatsApp')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Send a test WhatsApp to staff?')
                ->modalDescription('Goes to every number saved under Staff alerts. Save your changes first.')
                ->action(function () {
                    $whatsapp = app(StaffWhatsApp::class);
                    if ($whatsapp->recipients() === []) {
                        Notification::make()->warning()->title('No WhatsApp numbers saved yet')->send();

                        return;
                    }

                    foreach ($whatsapp->sendToAll('Mega Mall: test alert. New orders will arrive here like this.') as $phone => $problem) {
                        $problem === null
                            ? Notification::make()->success()->title('Sent to '.Phone::display($phone))->send()
                            : Notification::make()->danger()->title('Not sent to '.Phone::display($phone))->body($problem)->persistent()->send();
                    }
                }),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (self::KEYS as $key) {
            $value = $data[$key] ?? null;
            if (in_array($key, ['prepaid_order_expiry_minutes', 'return_window_days', 'low_stock_level', 'reorder_cover_days'], true)) {
                $value = (int) $value;
            }
            if (in_array($key, self::BOOLEANS, true)) {
                $value = (bool) $value;
            }
            if ($key === 'staff_whatsapp') {
                $value = collect($value ?? [])->map(fn (array $row) => [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'phone' => Phone::normalize($row['phone'] ?? null),
                    'apikey' => trim((string) ($row['apikey'] ?? '')),
                ])->values()->all();
            }
            if ($key === 'staff_alert_emails') {
                $value = implode(', ', preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY));
            }
            if ($key === 'lipa_numbers') {
                $value = collect($value ?? [])->map(fn (array $row) => [
                    'channel' => $row['channel'] ?? null,
                    'number' => preg_replace('/\D/', '', (string) ($row['number'] ?? '')),
                    'name' => trim((string) ($row['name'] ?? '')),
                ])->values()->all();
            }
            if (str_starts_with($key, 'theme_')) {
                $value = strtolower((string) $value);
            }
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Notification::make()->success()->title('Settings saved')->send();
    }
}
