<x-filament::page>
    <div>
        <div class="grid grid-cols-6 gap-8">
            <div class="col-span-4">
                <div class="text-sm bg-white rounded-md p-4">
                    <h2 class="text-2xl font-bold">Formulier invoer</h2>
                    <div class="space-y-4">
                        @if($record->content)
                            @foreach($record->content as $key => $value)
                                <div class="mt-4">
                                    <p class="font-bold">{{ \Illuminate\Support\Str::of($key)->replace('_', ' ')->title() }}
                                        :</p>
                                    <div>{{ $value }}</div>
                                </div>
                            @endforeach
                        @else
                            @foreach($record->formFields as $field)
                                <div>
                                    <h4 class="font-bold">{{$field->formField->name . ':'}}</h4>
                                    @if($field->isImage())
                                        @if(str($field->value)->contains(['.jpg','.jpeg','.png','.gif','.svg']))
                                            <img style="max-width: 400px;" src="/storage/{{ $field->value }}">
                                        @else
                                            <a href="{{ url('/storage/' . $field->value) }}">Bekijk bestand</a>
                                        @endif
                                        @if($field->formField->type == 'select-image')
                                            <div>{{ collect($field->formField->images)->where('image', $field->value)->first()['name'] }}</div>
                                        @endif
                                    @else
                                        <div>{!! nl2br($field->value) !!}</div>
                                    @endif
                                </div>
                            @endforeach
                        @endif
                        <div class="mt-4">
                            <p class="font-bold">Bekeken:</p>
                            <div>{{ $record->viewed ? 'Ja' : 'Nee' }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-2 bg-white rounded-md p-4">
                <div>
                    <h2 class="text-2xl font-bold mb-4">Overige informatie</h2>
                    <ul class="space-y-2">
                        <li class="my-2">IP: {{$record->ip ?? 'Niet ingevuld'}}</li>
                        <hr>
                        <li class="my-2">User agent: {{$record->user_agent ?? 'Niet ingevuld'}}</li>
                        <hr>
                        <li class="my-2">Ingevoerd vanaf: {{$record->from_url ?? 'Niet ingevuld'}}</li>
                        <hr>
                        <li class="my-2">Ingevoerd op: {{$record->created_at ?? 'Niet ingevuld'}}</li>
                        @if(count(Sites::getSites()) > 1)
                            <hr>
                            <li class="my-2">Site ID: {{$record->site_id ?? 'Niet ingevuld'}}</li>
                        @endif
                        @if(count(Locales::getLocales()) > 1)
                            <hr>
                            <li class="my-2">Locale: {{$record->locale ?? 'Niet ingevuld'}}</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-filament::page>
