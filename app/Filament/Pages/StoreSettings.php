<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\SmsTemplates;
use App\Support\Theme;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
        'store_name', 'tagline', 'phone', 'whatsapp', 'email', 'ticker', 'prepaid_order_expiry_minutes',
        'return_window_days', 'auto_approve_reviews', 'delivery_note', 'theme_primary', 'theme_dark', 'theme_sale',
        'sms_enabled', 'sms_templates', 'staff_alert_emails', 'staff_alert_sound', 'business_address', 'tin',
        'low_stock_level', 'reorder_cover_days',
    ];

    private const BOOLEANS = ['auto_approve_reviews', 'sms_enabled', 'staff_alert_sound'];

    /** @var array<string, mixed> | null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'sms_enabled' => true,
            'staff_alert_sound' => true,
            'low_stock_level' => 5,
            'reorder_cover_days' => 30,
            ...collect(Setting::allValues())->only(self::KEYS)->all(),
            ...Theme::colours(),
            'sms_templates' => SmsTemplates::all(),
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
                Section::make('Customer SMS')
                    ->description(new HtmlString('Messages customers get as their order moves along. Until an SMS account is connected they are only saved under <strong>Store › SMS outbox</strong>, not sent. You can use: {first_name} {name} {order} {total} {area} {tracking} {reason} {minutes} {store_name} {store_phone} {track_url} {return_days}'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('sms_enabled')->label('Send SMS to customers'),
                        ...collect(SmsTemplates::EVENTS)->map(fn (string $label, string $event) => Textarea::make("sms_templates.{$event}")
                            ->label($label)
                            ->rows(2)
                            ->maxLength(320)
                            ->helperText(fn (?string $state) => mb_strlen((string) $state).' characters'.(mb_strlen((string) $state) > 160 ? ' (sent as 2 SMS)' : ''))
                            ->live(onBlur: true)
                            ->required())->values()->all(),
                    ]),
                Section::make('Staff alerts')
                    ->description('How staff hear about new orders: pay-on-delivery orders when placed, upcountry orders once paid.')
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
                    ->description('The shop does not charge delivery: customers pay the rider or bus company directly. This note is shown at checkout and on order pages.')
                    ->schema([
                        Textarea::make('delivery_note')
                            ->label('Delivery note for customers')
                            ->rows(3)
                            ->maxLength(400)
                            ->required(),
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
                Section::make('Top bar messages')
                    ->description('The scrolling messages at the very top of the shop. Press Enter after each one.')
                    ->schema([
                        TagsInput::make('ticker')->hiddenLabel()->reorderable(),
                    ]),
                Section::make('Returns and reviews')
                    ->columns(2)
                    ->schema([
                        TextInput::make('return_window_days')
                            ->label('Customers can ask for a return within (days of delivery)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(90)
                            ->default(7)
                            ->required(),
                        Toggle::make('auto_approve_reviews')
                            ->label('Publish reviews immediately')
                            ->helperText('Off: you approve each review under Reviews before it shows on the shop.')
                            ->inline(false),
                    ]),
                Section::make('Mobile money orders')
                    ->schema([
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
            if ($key === 'sms_templates') {
                $value = collect(SmsTemplates::EVENTS)->keys()->mapWithKeys(fn (string $event) => [$event => trim((string) ($value[$event] ?? ''))])->all();
            }
            if ($key === 'staff_alert_emails') {
                $value = implode(', ', preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY));
            }
            if (str_starts_with($key, 'theme_')) {
                $value = strtolower((string) $value);
            }
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Notification::make()->success()->title('Settings saved')->send();
    }
}
