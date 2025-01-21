<div class="flex flex-col gap-2 flex-1 w-full">
{{--    <label for="{{ $field->fieldName }}" class="font-semibold">{{ $field->labelName }}</label>--}}
    <input
        class="form-input flex-1"
        name="{{ $field->fieldName }}"
        id="{{ $field->fieldName }}" type="{{ $field->input_type }}"
        wire:model.live="values.{{ $field->fieldName }}"
        placeholder="{{ $field->placeholderName }}">
    @if($field->helper_text)
        <span class="text-neutral text-sm">{{ $field->helper_text }}</span>
    @endif
    @error('values.' . $field->fieldName) <span class="text-red-500 font-bold">{{ $message }}</span> @enderror
</div>
