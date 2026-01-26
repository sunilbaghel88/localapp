<?php

namespace App\Filament\Owner\Resources\OrderResource\Pages;

use App\Filament\Owner\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('update_status')
                ->label('Update Status')
                ->icon('heroicon-m-arrow-path')
                ->form([
                    \Filament\Forms\Components\Select::make('status')
                        ->label('Order Status')
                        ->options([
                            'pending' => 'Pending',
                            'processing' => 'Processing',
                            'shipped' => 'Shipped',
                            'delivered' => 'Delivered',
                            'cancelled' => 'Cancelled',
                        ])
                        ->required()
                        ->default(fn ($record) => $record->status),
                    \Filament\Forms\Components\Select::make('payment_status')
                        ->label('Payment Status')
                        ->options([
                            'pending' => 'Pending',
                            'paid' => 'Paid',
                            'failed' => 'Failed',
                            'refunded' => 'Refunded',
                        ])
                        ->required()
                        ->default(fn ($record) => $record->payment_status),
                ])
                ->action(function (array $data, $record) {
                    $record->update([
                        'status' => $data['status'],
                        'payment_status' => $data['payment_status'],
                    ]);
                    \Filament\Notifications\Notification::make()
                        ->title('Order status updated')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Order Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('id')
                                    ->label('Order #')
                                    ->size('lg')
                                    ->weight('bold'),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'shipped' => 'success',
                                        'delivered' => 'success',
                                        'cancelled' => 'danger',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('payment_status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'paid' => 'success',
                                        'failed' => 'danger',
                                        'refunded' => 'gray',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Order Date')
                                    ->dateTime(),
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ]),
                Infolists\Components\Section::make('Customer Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('user.name')
                                    ->label('Customer Name'),
                                Infolists\Components\TextEntry::make('user.email')
                                    ->label('Email'),
                                Infolists\Components\TextEntry::make('user.phone')
                                    ->label('Phone'),
                            ]),
                    ]),
                Infolists\Components\Section::make('Shipping Address')
                    ->schema([
                        Infolists\Components\TextEntry::make('address.name')
                            ->label('Name'),
                        Infolists\Components\TextEntry::make('address.phone')
                            ->label('Phone'),
                        Infolists\Components\TextEntry::make('address.address_line1')
                            ->label('Address Line 1'),
                        Infolists\Components\TextEntry::make('address.address_line2')
                            ->label('Address Line 2'),
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('address.city')
                                    ->label('City'),
                                Infolists\Components\TextEntry::make('address.state')
                                    ->label('State'),
                                Infolists\Components\TextEntry::make('address.postal_code')
                                    ->label('Postal Code'),
                            ]),
                        Infolists\Components\TextEntry::make('address.country')
                            ->label('Country'),
                    ])
                    ->visible(fn ($record) => $record->address),
                Infolists\Components\Section::make('Order Items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->schema([
                                Infolists\Components\Grid::make(6)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name')
                                            ->label('Product')
                                            ->columnSpan(2),
                                        Infolists\Components\TextEntry::make('quantity')
                                            ->label('Qty'),
                                        Infolists\Components\TextEntry::make('price')
                                            ->label('Price')
                                            ->money('inr', divideBy: 1),
                                        Infolists\Components\TextEntry::make('discount')
                                            ->label('Discount')
                                            ->money('inr', divideBy: 1)
                                            ->default(0),
                                        Infolists\Components\TextEntry::make('total')
                                            ->label('Total')
                                            ->money('inr', divideBy: 1)
                                            ->weight('bold'),
                                    ]),
                            ])
                            ->columns(1),
                    ]),
                Infolists\Components\Section::make('Order Totals')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money('inr', divideBy: 1),
                                Infolists\Components\TextEntry::make('discount_total')
                                    ->label('Discount')
                                    ->money('inr', divideBy: 1)
                                    ->default(0),
                                Infolists\Components\TextEntry::make('shipping_total')
                                    ->label('Shipping')
                                    ->money('inr', divideBy: 1)
                                    ->default(0),
                                Infolists\Components\TextEntry::make('tax_total')
                                    ->label('Tax')
                                    ->money('inr', divideBy: 1)
                                    ->default(0),
                                Infolists\Components\TextEntry::make('grand_total')
                                    ->label('Grand Total')
                                    ->money('inr', divideBy: 1)
                                    ->size('lg')
                                    ->weight('bold')
                                    ->color('primary'),
                            ]),
                    ]),
                Infolists\Components\Section::make('Payment Information')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('payments')
                            ->schema([
                                Infolists\Components\Grid::make(4)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('provider')
                                            ->label('Provider'),
                                        Infolists\Components\TextEntry::make('amount')
                                            ->label('Amount')
                                            ->money('inr', divideBy: 1),
                                        Infolists\Components\TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'pending' => 'warning',
                                                'completed' => 'success',
                                                'failed' => 'danger',
                                                default => 'gray',
                                            }),
                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Date')
                                            ->dateTime(),
                                    ]),
                            ])
                            ->columns(1),
                    ])
                    ->visible(fn ($record) => $record->payments->isNotEmpty()),
            ]);
    }
}
