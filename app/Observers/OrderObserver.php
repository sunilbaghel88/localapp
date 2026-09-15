<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Sms\SmsSender;

class OrderObserver
{
    public function updated(Order $order): void
    {
        $sms = app(SmsSender::class);

        if ($order->wasChanged('status')) {
            $sms->notifyOrderStatusChanged($order);
        }

        if ($order->wasChanged('payment_status')) {
            $sms->notifyPaymentStatusChanged($order);
        }
    }
}
