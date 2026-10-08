<?php

namespace App\Actions;

use App\Mail\RewardPointsGrantedMail;
use App\Models\Order;
use App\Models\UserRewardGrant;
use App\Services\Sms\SmsSender;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class GrantOrderRewardPoints
{
    public static function formSchema(): array
    {
        return [
            Forms\Components\TextInput::make('points')
                ->label('Points to grant')
                ->numeric()
                ->required()
                ->minValue(1),
            Forms\Components\Textarea::make('notes')
                ->label('Notes (optional)')
                ->rows(2),
        ];
    }

    public static function handle(Order $order, array $data): void
    {
        if (! $order->electrician_user_id) {
            Notification::make()
                ->title(__('This order has no partner associated.'))
                ->danger()
                ->send();

            return;
        }

        UserRewardGrant::create([
            'user_id' => $order->electrician_user_id,
            'order_id' => $order->id,
            'points' => $data['points'],
            'granted_by' => Auth::id(),
            'notes' => $data['notes'] ?? null,
        ]);

        $order->partnerUser->increment('reward_points', $data['points']);

        $order->loadMissing(['shop', 'user', 'items']);
        $partner = $order->partnerUser?->fresh();
        $points = (int) $data['points'];

        if ($partner) {
            app(SmsSender::class)->notifyRewardPointsGranted(
                $partner,
                $order->shop,
                $points,
                (int) ($partner->reward_points ?? 0),
            [
                'order_id' => (string) $order->id,
                'source' => 'Order #'.$order->id,
                'customer' => trim((string) ($order->user?->name ?: '-')),
                'amount' => number_format((float) $order->grand_total, 2, '.', ''),
            ],
            );
        }

        if ($partner && filled($partner->email)) {
            try {
                Mail::to($partner->email)->send(new RewardPointsGrantedMail(
                    order: $order,
                    points: $points,
                    notes: $data['notes'] ?? null,
                    newRewardBalance: (int) $partner->reward_points,
                    electricianName: $partner->name,
                    grantedByName: Auth::user()?->name,
                ));
            } catch (\Throwable $e) {
                report($e);
                Notification::make()
                    ->title('Points granted, but the notification email could not be sent.')
                    ->warning()
                    ->send();
            }
        }

        Notification::make()
            ->title('Granted '.$points.' reward points to '.($partner?->name ?: 'partner'))
            ->success()
            ->send();
    }
}
