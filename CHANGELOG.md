# Changelog

All notable changes to `dashed-forms` will be documented in this file.

## v4.0.23 - 2026-05-03

### Added
- `form-components/file.blade.php` view. The `file` input type was already wired in `Forms::availableInputTypes()` and the Livewire `Form` component already supports uploads via `WithFileUploads` (`updated()` stores the upload to the `dashed` disk and writes the path back to `values`), but the matching blade was missing — rendering a form with a `file` field threw `Unable to locate a class or view for component [form-components.file]`. Added the view with label, file input wired via `wire:model`, an inline upload-progress indicator, an "uploaded" confirmation, helper text and validation error rendering.

## v4.0.22 - 2026-05-02

### Added
- `popupApiClasses` builder-key in `FormManager`. Provider-packages (`dashed-laposta`, `dashed-ternair`) registreren hier hun popup-newsletter API class zodat `dashed-popups` per popup een repeater kan opbouwen met de geregistreerde providers. Identiek patroon als `apiClasses` (forms) en `orderApiClasses` (orders).

## v4.0.21 - 2026-04-27

- `DashedFormsServiceProvider::bootingPackage()` registreert de "Formulieren" navigatiegroep via `cms()->registerNavigationGroup('Formulieren', 50)`. Vereist dashed-core v4.2.0+.
- Code-style cleanup in `2026_04_18_000001_refresh_admin_form_templates_with_submission_block` migratie.

## 1.0.0 - 202X-XX-XX

- initial release
