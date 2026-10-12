<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class StoreSettings extends Page
{
    protected static string | UnitEnum | null $navigationGroup = 'Store';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Store settings';

    protected static ?string $title = 'Store settings';

    /** Keys from the settings table that this page edits. */
    private const KEYS = ['store_name', 'tagline', 'phone', 'whatsapp', 'email', 'ticker', 'prepaid_order_expiry_minutes'];

    /** @var array<string, mixed> | null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(collect(Setting::allValues())->only(self::KEYS)->all());
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
                    ]),
                Section::make('Top bar messages')
                    ->description('The scrolling messages at the very top of the shop. Press Enter after each one.')
                    ->schema([
                        TagsInput::make('ticker')->hiddenLabel()->reorderable(),
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
            if ($key === 'prepaid_order_expiry_minutes') {
                $value = (int) $value;
            }
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Notification::make()->success()->title('Settings saved')->send();
    }
}
