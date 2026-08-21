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
            'endpoint' => $setting->endpoint,
            'http_method' => $setting->http_method,
            'payload_params' => $setting->payload_params ?? [],
            'message_template' => $setting->message_template,
            'otp_ttl_minutes' => $setting->otp_ttl_minutes,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Provider')
                    ->description('Configure the global SMS gateway used for OTP login and registration. Use placeholders {{mobile}}, {{message}}, and {{otp}} in payload values.')
                    ->schema([
                        Forms\Components\Toggle::make('is_enabled')
                            ->label('Enable SMS sending')
                            ->helperText('When disabled, OTP SMS will not be sent.'),
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
            'endpoint' => $data['endpoint'] ?? null,
            'http_method' => strtoupper((string) ($data['http_method'] ?? 'GET')),
            'payload_params' => $data['payload_params'] ?? [],
            'message_template' => $data['message_template'] ?? 'Your OTP is {{otp}}.',
            'otp_ttl_minutes' => (int) ($data['otp_ttl_minutes'] ?? 10),
        ]);

        Notification::make()
            ->title('SMS settings saved')
            ->success()
            ->send();
    }
}
