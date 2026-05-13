<?php

namespace Dashed\DashedForms\Filament\Pages\Settings;

use BackedEnum;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedForms\Classes\MailingProviders\ActiveCampaign;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use UnitEnum;

class FormSettingsPage extends Page
{
    use HasSettingsPermission;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Formulier instellingen';

    protected static string|UnitEnum|null $navigationGroup = 'Overige';

    protected static ?string $title = 'Formulier instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];

        $sites = Sites::getSites();
        foreach ($sites as $site) {
            $formData["notification_form_inputs_emails_{$site['id']}"] = Customsetting::get('notification_form_inputs_emails', $site['id'], []);
            $formData['form_redirect_server_side'] = Customsetting::get('form_redirect_server_side', null, true);
            $formData["form_activecampaign_url_{$site['id']}"] = Customsetting::get('form_activecampaign_url', $site['id']);
            $formData["form_activecampaign_key_{$site['id']}"] = Customsetting::get('form_activecampaign_key', $site['id']);
            $formData["google_recaptcha_site_key_{$site['id']}"] = Customsetting::get('google_recaptcha_site_key', $site['id']);
            $formData["google_recaptcha_secret_key_{$site['id']}"] = Customsetting::get('google_recaptcha_secret_key', $site['id']);
            $formData["captcha_provider_{$site['id']}"] = Customsetting::get('captcha_provider', $site['id'], 'google_recaptcha');
            $formData["mcaptcha_instance_url_{$site['id']}"] = Customsetting::get('mcaptcha_instance_url', $site['id']);
            $formData["mcaptcha_site_key_{$site['id']}"] = Customsetting::get('mcaptcha_site_key', $site['id']);
            $formData["mcaptcha_secret_{$site['id']}"] = Customsetting::get('mcaptcha_secret', $site['id']);
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $sites = Sites::getSites();
        $tabGroups = [];

        $tabs = [];
        foreach ($sites as $site) {
            $activeCampaign = new ActiveCampaign($site['id']);

            $newSchema = [
                TextEntry::make("Formulier instellingen voor {$site['name']}")
                    ->state('Stel extra opties in voor de formulieren.'),
                TagsInput::make("notification_form_inputs_emails_{$site['id']}")
                    ->suggestions(User::where('role', 'admin')->pluck('email')->toArray())
                    ->label('Emails om de bevestigingsmail van een formulier aanvraag naar te sturen')
                    ->placeholder('Voer een email in')
                    ->reactive(),
                Select::make("captcha_provider_{$site['id']}")
                    ->label('Captcha provider')
                    ->helperText('Kies welke captcha er voor de formulieren wordt gebruikt.')
                    ->options([
                        'none' => 'Geen',
                        'google_recaptcha' => 'Google reCAPTCHA',
                        'mcaptcha' => 'mCaptcha (self-hosted)',
                    ])
                    ->default('google_recaptcha')
                    ->reactive()
                    ->required(),
                TextInput::make("google_recaptcha_site_key_{$site['id']}")
                    ->label('Google Recaptcha site key')
                    ->helperText(new HtmlString('Maak een key en secret aan via <a href="https://www.google.com/recaptcha/admin/create" target="_blank" class="underline"><u>Google Recaptcha</u></a>.'))
                    ->visible(fn (Get $get) => $get("captcha_provider_{$site['id']}") === 'google_recaptcha')
                    ->reactive()
                    ->maxLength(255),
                TextInput::make("google_recaptcha_secret_key_{$site['id']}")
                    ->label('Google Recaptcha secret key')
                    ->visible(fn (Get $get) => $get("captcha_provider_{$site['id']}") === 'google_recaptcha')
                    ->required(fn (Get $get) => $get("google_recaptcha_site_key_{$site['id']}"))
                    ->maxLength(255),
                TextInput::make("mcaptcha_instance_url_{$site['id']}")
                    ->label('mCaptcha instance URL')
                    ->helperText('De basis URL van de zelf-gehoste mCaptcha, bv. https://captcha.example.com')
                    ->placeholder('https://captcha.example.com')
                    ->visible(fn (Get $get) => $get("captcha_provider_{$site['id']}") === 'mcaptcha')
                    ->required(fn (Get $get) => $get("captcha_provider_{$site['id']}") === 'mcaptcha')
                    ->url()
                    ->maxLength(255),
                TextInput::make("mcaptcha_site_key_{$site['id']}")
                    ->label('mCaptcha sitekey')
                    ->visible(fn (Get $get) => $get("captcha_provider_{$site['id']}") === 'mcaptcha')
                    ->required(fn (Get $get) => $get("captcha_provider_{$site['id']}") === 'mcaptcha')
                    ->maxLength(255),
                TextInput::make("mcaptcha_secret_{$site['id']}")
                    ->label('mCaptcha secret')
                    ->helperText('Wordt alleen server-side gebruikt en niet in de HTML geladen.')
                    ->visible(fn (Get $get) => $get("captcha_provider_{$site['id']}") === 'mcaptcha')
                    ->required(fn (Get $get) => $get("captcha_provider_{$site['id']}") === 'mcaptcha')
                    ->password()
                    ->revealable()
                    ->maxLength(255),
                TextInput::make("form_activecampaign_url_{$site['id']}")
                    ->label('ActiveCampaign API url')
                    ->helperText('ActiveCampaign actief: '.($activeCampaign->connected ? 'Ja' : 'Nee'))
                    ->reactive(),
                TextInput::make("form_activecampaign_key_{$site['id']}")
                    ->label('ActiveCampaign API key')
                    ->reactive(),
            ];

            $tabs[] = Tab::make($site['id'])
                ->label(ucfirst($site['name']))
                ->schema($newSchema)
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ]);
        }
        $tabGroups[] = Tabs::make('Sites')
            ->tabs($tabs);

        return $schema->schema(array_merge($tabGroups, [
            Section::make('Algemene formulier instellingen')->columnSpanFull()
                ->schema([
                    Toggle::make('form_redirect_server_side')
                        ->label('Doe de redirects server side'),
                ]),
        ]))
            ->statePath('data');
    }

    public function submit()
    {
        $sites = Sites::getSites();
        $state = $this->form->getState();
        $formState = $state;

        foreach ($sites as $site) {
            $sid = $site['id'];

            $emails = (array) ($state["notification_form_inputs_emails_{$sid}"] ?? []);
            foreach ($emails as $key => $email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    unset($emails[$key]);
                }
            }
            Customsetting::set('notification_form_inputs_emails', $emails, $sid);
            $formState["notification_form_inputs_emails_{$sid}"] = $emails;

            Customsetting::set('captcha_provider', $state["captcha_provider_{$sid}"] ?? 'google_recaptcha', $sid);
            Customsetting::set('google_recaptcha_site_key', $state["google_recaptcha_site_key_{$sid}"] ?? null, $sid);
            Customsetting::set('google_recaptcha_secret_key', $state["google_recaptcha_secret_key_{$sid}"] ?? null, $sid);
            Customsetting::set('mcaptcha_instance_url', $state["mcaptcha_instance_url_{$sid}"] ?? null, $sid);
            Customsetting::set('mcaptcha_site_key', $state["mcaptcha_site_key_{$sid}"] ?? null, $sid);
            Customsetting::set('mcaptcha_secret', $state["mcaptcha_secret_{$sid}"] ?? null, $sid);
            Customsetting::set('form_activecampaign_url', $state["form_activecampaign_url_{$sid}"] ?? null, $sid);
            Customsetting::set('form_activecampaign_key', $state["form_activecampaign_key_{$sid}"] ?? null, $sid);
            Customsetting::set('form_redirect_server_side', $state['form_redirect_server_side'] ?? true, $sid);
        }

        $this->form->fill($formState);

        Notification::make()
            ->title('De formulier instellingen zijn opgeslagen')
            ->success()
            ->send();
    }
}
