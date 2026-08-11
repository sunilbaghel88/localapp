<?php

namespace App\Filament\Pages;

use App\Models\AppBranding;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AppBrandingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'App Branding';

    protected static ?string $title = 'App Branding';

    protected static ?string $slug = 'app-branding';

    protected static ?int $navigationSort = 80;

    protected static string $view = 'filament.pages.app-branding-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $branding = AppBranding::current();

        $this->form->fill([
            'app_name' => $branding->app_name,
            'tagline' => $branding->tagline,
            'logo_path' => $branding->logo_path,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Mobile app branding')
                    ->description('Logo and labels shown on the mobile login and register screens.')
                    ->schema([
                        Forms\Components\TextInput::make('app_name')
                            ->label('App name')
                            ->maxLength(100)
                            ->placeholder('LocalApp'),
                        Forms\Components\TextInput::make('tagline')
                            ->label('Tagline')
                            ->maxLength(150)
                            ->placeholder('LOYALTY PROGRAM'),
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('App logo')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(5120)
                            ->helperText('PNG or JPG recommended. Shown on login/register. Leave empty to use the mobile sample logo.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $branding = AppBranding::current();

        $newLogo = $data['logo_path'] ?? null;
        if (is_array($newLogo)) {
            $newLogo = $newLogo[0] ?? null;
        }

        $oldLogo = $branding->logo_path;
        if ($oldLogo && $oldLogo !== $newLogo) {
            $branding->deleteLogoFile();
        }

        $branding->update([
            'app_name' => $data['app_name'] ?? null,
            'tagline' => $data['tagline'] ?? null,
            'logo_path' => $newLogo,
        ]);

        Notification::make()
            ->title('App branding saved')
            ->success()
            ->send();
    }
}
