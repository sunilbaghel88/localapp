<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasSafePageShield;
use App\Models\SmsSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class SmsSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use HasSafePageShield;
    
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'SMS Settings';

    protected static ?string $title = 'SMS Settings';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.sms-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $setting = SmsSetting::current();

        $this->form->fill([
            'is_enabled' => $setting->is_enabled,
            'order_sms_enabled' => $setting->order_sms_enabled,
            'endpoint' => $setting->endpoint,
            'http_method' => $setting->http_method,
            'payload_params' => $setting->payload_params ?? [],
            'message_template' => $setting->message_template,
            'order_message_template' => $setting->order_message_template,
            'otp_ttl_minutes' => $setting->otp_ttl_minutes,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Provider')
                    ->description('Configure the global SMS gateway used for OTP and order alerts. Use placeholders {{mobile}} and {{message}} in payload values. OTP also supports {{otp}}.')
                    ->schema([
                        Forms\Components\Toggle::make('is_enabled')
                            ->label('Enable SMS sending')
                            ->helperText('Master switch. When disabled, neither OTP nor order SMS will be sent.'),
                        Forms\Components\Toggle::make('order_sms_enabled')
                            ->label('Send SMS when an order is created')
                            ->helperText('Customer receives a confirmation if they have a mobile number. Order create still succeeds if SMS fails.'),
                        Forms\Components\TextInput::make('endpoint')
                            ->label('Endpoint URL')
                            ->url()
                            ->required()
                            ->maxLength(500)
                            ->placeholder('http://sms.endmile.in/WebServiceSMS.aspx'),
                        Forms\Components\Select::make('http_method')
                            ->label('HTTP Method')
                            ->options([
                                'GET' => 'GET (query parameters)',
                                'POST' => 'POST (form body)',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\KeyValue::make('payload_params')
                            ->label('Payload parameters')
                            ->keyLabel('Parameter')
                            ->valueLabel('Value')
                            ->reorderable()
                            ->addActionLabel('Add parameter')
                            ->helperText('Example: mobilenumber={{mobile}}, message={{message}}, User=your_user, passwd=your_password, sid=MDEVAP, mtype=N'),
                        Forms\Components\TextInput::make('message_template')
                            ->label('OTP message template')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Use {{otp}} for the code. Example: Your OTP is {{otp}}.')
                            ->default('Your OTP is {{otp}}.'),
                        Forms\Components\Textarea::make('order_message_template')
                            ->label('Order confirmation template')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Placeholders: {{order_id}}, {{shop}}, {{total}}, {{customer}}, {{items_count}}, {{mobile}}.')
                            ->default('Your order #{{order_id}} at {{shop}} is placed. Amount Rs {{total}}. Thank you.'),
                        Forms\Components\TextInput::make('otp_ttl_minutes')
                            ->label('OTP expiry (minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->required()
                            ->default(10),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $setting = SmsSetting::current();

        $setting->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'order_sms_enabled' => (bool) ($data['order_sms_enabled'] ?? true),
            'endpoint' => $data['endpoint'] ?? null,
            'http_method' => strtoupper((string) ($data['http_method'] ?? 'GET')),
            'payload_params' => $data['payload_params'] ?? [],
            'message_template' => $data['message_template'] ?? 'Your OTP is {{otp}}.',
            'order_message_template' => $data['order_message_template'] ?? 'Your order #{{order_id}} at {{shop}} is placed. Amount Rs {{total}}. Thank you.',
            'otp_ttl_minutes' => (int) ($data['otp_ttl_minutes'] ?? 10),
        ]);

        Notification::make()
            ->title('SMS settings saved')
            ->success()
            ->send();
    }
}
