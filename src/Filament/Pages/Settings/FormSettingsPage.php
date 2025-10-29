<?php

namespace Dashed\DashedForms\Filament\Pages\Settings;

use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;
use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedForms\Classes\MailingProviders\ActiveCampaign;

class FormSettingsPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bell';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Formulier instellingen';
    protected static string | UnitEnum | null $navigationGroup = 'Overige';
    protected static ?string $title = 'Formulier instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';
    public array $data = [];

    public function mount(): void
    {
        $formData = [];

        $sites = Sites::getSites();
        foreach ($sites as $site) {
            $formData["notification_form_inputs_emails_{$site['id']}"] = Customsetting::get('notification_form_inputs_emails', $site['id'], []);
            $formData["form_redirect_server_side"] = Customsetting::get('form_redirect_server_side', null, true);
            $formData["form_activecampaign_url_{$site['id']}"] = Customsetting::get('form_activecampaign_url', $site['id']);
            $formData["form_activecampaign_key_{$site['id']}"] = Customsetting::get('form_activecampaign_key', $site['id']);
            $formData["google_recaptcha_site_key_{$site['id']}"] = Customsetting::get('google_recaptcha_site_key', $site['id']);
            $formData["google_recaptcha_secret_key_{$site['id']}"] = Customsetting::get('google_recaptcha_secret_key', $site['id']);
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
                    ->helperText(new HtmlString('Dit moet specifiek ingebouwd worden in de formulieren. Maak een key en secret aan via <a href="https://www.google.com/recaptcha/admin/create" target="_blank" class="underline"><u>Google Recaptcha</u></a>.'))
                    ->placeholder('Voer een email in')
                    ->reactive(),
                TextInput::make("google_recaptcha_site_key_{$site['id']}")
                    ->label('Google Recaptcha site key')
                    ->reactive()
                    ->maxLength(255),
                TextInput::make("google_recaptcha_secret_key_{$site['id']}")
                    ->label('Google Recaptcha secret key')
                    ->required(fn (Get $get) => $get("google_recaptcha_site_key_{$site['id']}"))
                    ->maxLength(255),
                TextInput::make("form_activecampaign_url_{$site['id']}")
                    ->label('ActiveCampaign API url')
                    ->helperText('ActiveCampaign actief: ' . ($activeCampaign->connected ? 'Ja' : 'Nee'))
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
                    Toggle::make("form_redirect_server_side")
                        ->label('Doe de redirects server side'),
                ]),
        ]))
            ->statePath('data');
    }

    public function submit()
    {
        $sites = Sites::getSites();
        $formState = $this->form->getState();

        foreach ($sites as $site) {
            $emails = $this->form->getState()["notification_form_inputs_emails_{$site['id']}"];
            foreach ($emails as $key => $email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    unset($emails[$key]);
                }
            }
            Customsetting::set('notification_form_inputs_emails', $emails, $site['id']);
            $formState["notification_form_inputs_emails_{$site['id']}"] = $emails;

            Customsetting::set('google_recaptcha_site_key', $this->form->getState()["google_recaptcha_site_key_{$site['id']}"], $site['id']);
            Customsetting::set('google_recaptcha_secret_key', $this->form->getState()["google_recaptcha_secret_key_{$site['id']}"], $site['id']);
            Customsetting::set('form_activecampaign_url', $this->form->getState()["form_activecampaign_url_{$site['id']}"], $site['id']);
            Customsetting::set('form_activecampaign_key', $this->form->getState()["form_activecampaign_key_{$site['id']}"], $site['id']);
            Customsetting::set('form_redirect_server_side', $this->form->getState()["form_redirect_server_side"], $site['id']);
        }

        $this->form->fill($formState);

        Notification::make()
            ->title('De formulier instellingen zijn opgeslagen')
            ->success()
            ->send();
    }
}
