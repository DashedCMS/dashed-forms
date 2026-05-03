@php
    $value = $values[$field->fieldName] ?? null;
    $hasUpload = is_string($value) && $value !== '';
    $accept = $field->accept ?? null;
@endphp

<div class="flex flex-col gap-2">
    <label for="{{ $field->fieldName }}" class="font-semibold">
        {{ $field->labelName }}
        @if($field->required)<span class="text-red-500">*</span>@endif
    </label>

    <input
        type="file"
        id="{{ $field->fieldName }}"
        name="{{ $field->fieldName }}"
        wire:model="values.{{ $field->fieldName }}"
        @if($accept) accept="{{ $accept }}" @endif
        class="custom-form-input file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary-700 cursor-pointer"
    >

    <div wire:loading wire:target="values.{{ $field->fieldName }}" class="text-sm text-neutral">
        {{ \Dashed\DashedTranslations\Models\Translation::get('form-file-uploading', 'forms', 'Bestand wordt geüpload...') }}
    </div>

    @if($hasUpload)
        <div wire:loading.remove wire:target="values.{{ $field->fieldName }}" class="flex items-center gap-2 text-sm text-green-700">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
            <span>{{ \Dashed\DashedTranslations\Models\Translation::get('form-file-uploaded', 'forms', 'Bestand geüpload') }}</span>
        </div>
    @endif

    @if($field->helper_text)
        <span class="text-neutral text-sm">{{ $field->helper_text }}</span>
    @endif

    @error('values.' . $field->fieldName) <span class="text-red-500 font-bold">{{ $message }}</span> @enderror
</div>
