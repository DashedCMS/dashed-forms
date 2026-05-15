<?php

namespace Dashed\DashedForms\Controllers\Frontend;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Dashed\DashedForms\Models\Form;
use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedForms\Models\FormInput;
use Dashed\DashedCore\Classes\EmailCapture;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedForms\Events\FormSubmitted;
use Dashed\DashedForms\Jobs\SyncFormInputApisJob;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedForms\Mail\FormSubmitConfirmationMail;
use Dashed\DashedForms\Mail\AdminFormSubmitConfirmationMail;

class FormController extends Controller
{
    public function store(Request $request)
    {
        $formName = $request->form_name;
        if (! $formName) {
            return redirect()->back()->with('error', Translation::get('form-name-not-provided', 'form', 'Form name not provided, please contact a administrator'))->withInput();
        }

        $configForms = cms()->builder('forms');
        foreach ($configForms as $name => $configForm) {
            if ($name == $formName) {
                $honeypotFieldName = $configForm['honeypot_field_name'] ?? '';
                if ($honeypotFieldName && $request->get($honeypotFieldName)) {
                    return redirect()->back()->with('error', Translation::get('form-name-not-provided', 'form', 'Form not found, please contact a administrator'))->withInput();
                }

                $sendToField = $configForm['send_to_field'];
                $sendToFieldValue = '';
                $validations = [];
                foreach ($configForm['fields'] as $fieldName => $field) {
                    $validations[$fieldName] = $field['rules'];
                }
                $request->validate($validations);

                $form = Form::where('name', $formName)->first();
                if (! $form) {
                    $form = new Form();
                    $form->name = $formName;
                    $form->save();
                }

                $correctContent = [];
                foreach ($configForm['fields'] as $fieldName => $field) {
                    $correctContent[$fieldName] = $request->input($fieldName);
                    if ($sendToField && $sendToField == $fieldName) {
                        $sendToFieldValue = $request->input($fieldName);
                    }
                }

                $formInput = new FormInput();
                $formInput->form_id = $form->id;
                $formInput->ip = $request->ip();
                $formInput->user_agent = $request->userAgent();
                $formInput->content = $correctContent;
                $formInput->from_url = url()->previous();
                $formInput->site_id = Sites::getActive();
                $formInput->locale = App::getLocale();
                $formInput->save();

                // Globale e-mail-captura — werkt zowel als het form
                // een email-veld heeft als wanneer een willekeurig veld
                // toevallig een geldig adres bevat.
                foreach ($correctContent as $value) {
                    if (is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
                        EmailCapture::capture($value, 'form:'.$formName);

                        break;
                    }
                }

                // FormSubmitted event lets downstream marketing listeners
                // (newsletter enrolment, lead-flow signup, etc.) react without
                // coupling form-input persistence to specific consumers.
                event(new FormSubmitted(
                    form_id: (int) $formInput->form_id,
                    form_input_id: (int) $formInput->id,
                    email: $sendToFieldValue ?: null,
                    locale: $formInput->locale ?: app()->getLocale(),
                    site_id: $formInput->site_id,
                ));

                if ($formInput->should_send_api && (int) $formInput->api_send !== 1) {
                    SyncFormInputApisJob::dispatch($formInput->id);
                }

                if ($sendToFieldValue) {
                    try {
                        Mail::to($sendToFieldValue)->send(new FormSubmitConfirmationMail($form, $formInput));
                    } catch (\Exception $e) {
                    }
                }

                try {
                    $notificationFormInputsEmails = Customsetting::get('notification_form_inputs_emails', Sites::getActive(), []);
                    if ($notificationFormInputsEmails) {
                        foreach ($notificationFormInputsEmails as $notificationFormInputsEmail) {
                            AdminNotifier::send(new AdminFormSubmitConfirmationMail($form, $formInput, $sendToFieldValue), $notificationFormInputsEmail);
                        }
                    }
                } catch (\Exception $e) {
                }

                return redirect()->back()->with('success', Translation::get('form-'.Str::slug($form->name).'-succesfully-submitted', 'form', 'The form has been submitted'));
            }
        }

        return redirect()->back()->with('error', Translation::get('form-name-not-provided', 'form', 'Form not found, please contact a administrator'))->withInput();
    }
}
