<?php

namespace App\Filament\Resources\Reviews;

use App\Enums\ReviewStatus;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/** Customer reviews. Only buyers of delivered orders can leave one; staff publish or hide them. */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string | UnitEnum | null $navigationGroup = 'Products & Stock';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?int $navigationSort = 8;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Review::where('status', ReviewStatus::Pending)->count();

        return $pending ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'order']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Date')->date('d M Y')->sortable(),
                TextColumn::make('product.name')->label('Product')->searchable()->wrap(),
                TextColumn::make('rating')
                    ->formatStateUsing(fn (int $state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color(fn (int $state) => $state >= 4 ? 'success' : ($state === 3 ? 'warning' : 'danger'))
                    ->sortable(),
                TextColumn::make('comment')->wrap()->limit(160)->placeholder('No comment')
                    ->description(fn (Review $record) => $record->reply ? 'Your reply: '.$record->reply : null),
                TextColumn::make('customer_name')->label('Customer')->searchable()
                    ->description(fn (Review $record) => $record->order?->order_number),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ReviewStatus::class)->default(ReviewStatus::Pending->value),
                SelectFilter::make('rating')->options([5 => '5 stars', 4 => '4 stars', 3 => '3 stars', 2 => '2 stars', 1 => '1 star']),
            ])
            ->recordActions([
                Action::make('publish')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Review $record) => $record->status !== ReviewStatus::Approved)
                    ->action(fn (Review $record) => $record->update(['status' => ReviewStatus::Approved]))
                    ->successNotificationTitle('Review published'),
                Action::make('hide')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('gray')
                    ->visible(fn (Review $record) => $record->status !== ReviewStatus::Rejected)
                    ->action(fn (Review $record) => $record->update(['status' => ReviewStatus::Rejected]))
                    ->successNotificationTitle('Review hidden'),
                Action::make('reply')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->color('gray')
                    ->fillForm(fn (Review $record) => ['reply' => $record->reply])
                    ->schema([
                        Textarea::make('reply')->label('Public reply from Mega Mall')->rows(3),
                    ])
                    ->action(fn (Review $record, array $data) => $record->update(['reply' => $data['reply']]))
                    ->successNotificationTitle('Reply saved'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publishSelected')
                        ->label('Publish selected')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->action(fn (Collection $records) => $records->each->update(['status' => ReviewStatus::Approved])),
                    BulkAction::make('hideSelected')
                        ->label('Hide selected')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->action(fn (Collection $records) => $records->each->update(['status' => ReviewStatus::Rejected])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListReviews::route('/')];
    }
}
