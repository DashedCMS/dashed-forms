<?php

namespace Qubiqx\QcommerceForms\Classes\MailingProviders;

use Exception;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Http;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceForms\Models\FormInput;

class ActiveCampaign
{
    public $name = 'ActiveCampaign';
    public $slug = 'active_campaign';

    private string $url = '';
    private string $key = '';

    public bool $connected = false;

    public function __construct(string $siteId)
    {
        $this->url = Customsetting::get('form_activecampaign_url', $siteId, '');
        $this->key = Customsetting::get('form_activecampaign_key', $siteId, '');

        if ($this->url && $this->key) {
            $this->connected = $this->testConnection();
        }
    }

    public function testConnection(): bool
    {
        try {
            return Http::withHeaders([
                    'Api-Token' => $this->key,
                    'accept' => 'application/json',
                ])
                    ->get("$this->url/api/3/accounts")
                    ->status() === 200;
        } catch (Exception $exception) {
            return false;
        }
    }

    public function getFormSchema(): array
    {
        return [
//            Select::make("external_options.{$this->slug}_list_id")
//                ->label('Kies een lijst')
//                ->options(collect($this->getLists())->pluck('name', 'stringid'))
//                ->visible(fn ($get) => $get("external_options.send_to_$this->slug")),
            Select::make("external_options.{$this->slug}_tags")
                ->label('Kies tags om toe te voegen aan het contact')
                ->options(collect($this->getTags())->pluck('tag', 'id'))
                ->multiple()
                ->preload()
                ->visible(fn ($get) => $get("external_options.send_to_$this->slug")),
        ];
    }

    public function getFormFieldSchema(): array
    {
        return [
            Select::make("external_options.{$this->slug}_contact_field")
                ->label('Kies een contact veld')
                ->options(collect($this->getContactFields())->pluck('title', 'id'))
                ->visible(fn ($get) => $get("../../external_options.send_to_$this->slug")),
        ];
    }

    public function getAccounts(): array
    {
        $response = Http::withHeaders([
            'Api-Token' => $this->key,
            'accept' => 'application/json',
        ])
            ->get("$this->url/api/3/accounts")
            ->json();

        return $response['accounts'] ?? [];
    }

    public function getLists(): array
    {
        $response = Http::withHeaders([
            'Api-Token' => $this->key,
            'accept' => 'application/json',
        ])
            ->get("$this->url/api/3/lists")
            ->json();

        return $response['lists'] ?? [];
    }

    public function getTags(): array
    {
        $response = Http::withHeaders([
            'Api-Token' => $this->key,
            'accept' => 'application/json',
        ])
            ->get("$this->url/api/3/tags")
            ->json();

        return $response['tags'] ?? [];
    }

    public function getContactFields(): array
    {
        $fields = [
            [
                'title' => 'Email',
                'id' => 'email',
            ],
            [
                'title' => 'Voornaam',
                'id' => 'firstName',
            ],
            [
                'title' => 'Achternaam',
                'id' => 'lastName',
            ],
            [
                'title' => 'Mobiele nummer',
                'id' => 'phone',
            ],
        ];

        $response = Http::withHeaders([
            'Api-Token' => $this->key,
            'accept' => 'application/json',
        ])
            ->get("$this->url/api/3/fields")
            ->json();

        $fields = array_merge($fields, $response['fields'] ?? []);

        return $fields;
    }

    public function createContactFromFormInput(FormInput $formInput)
    {
        $email = '';
        $firstName = '';
        $lastName = '';
        $phone = '';
        $fieldValues = [];

        foreach ($formInput->formFields as $formField) {
            if ($formField->formField->external_options["{$this->slug}_contact_field"] === 'email') {
                $email = $formField->value;
            } elseif ($formField->formField->external_options["{$this->slug}_contact_field"] === 'firstName') {
                $firstName = $formField->value;
            } elseif ($formField->formField->external_options["{$this->slug}_contact_field"] === 'lastName') {
                $lastName = $formField->value;
            } elseif ($formField->formField->external_options["{$this->slug}_contact_field"] === 'phone') {
                $phone = $formField->value;
            } else {
                $fieldValues[] = [
                    'field' => $formField->formField->external_options["{$this->slug}_contact_field"],
                    'value' => $formField->value,
                ];
            }
        }

        $response = Http::withHeaders([
            'Api-Token' => $this->key,
            'accept' => 'application/json',
        ])
            ->post("$this->url/api/3/contacts", [
                'contact' => [
                    'email' => $email,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'phone' => $phone,
                    'fieldValues' => $fieldValues,
                ],
            ])
            ->json();

        if ($formInput->form->external_options["{$this->slug}_tags"]) {
            foreach ($formInput->form->external_options["{$this->slug}_tags"] as $tagId) {
                $response = Http::withHeaders([
                    'Api-Token' => $this->key,
                    'accept' => 'application/json',
                ])
                    ->post("$this->url/api/3/contactTags", [
                        'contact' => $response['contact']['id'],
                        'tag' => $tagId,
                    ])
                    ->json();
            }
        }

        return $response;
    }
}
