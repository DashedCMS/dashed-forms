<?php

namespace Qubiqx\QcommerceForms\Controllers\Frontend;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Qubiqx\QcommerceCore\Models\Form;
use Qubiqx\QcommerceCore\Classes\Sites;
use Qubiqx\QcommerceCore\Models\FormInput;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceCore\Mail\FormSubmitConfirmationMail;
use Qubiqx\QcommerceCore\Mail\AdminFormSubmitConfirmationMail;
use Qubiqx\QcommerceCore\Controllers\Frontend\FrontendController;

class FormController extends FrontendController
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

                if ($sendToFieldValue) {
                    try {
                        Mail::to($sendToFieldValue)->send(new FormSubmitConfirmationMail($form, $formInput));
                    } catch (\Exception $e) {
                        dd($e->getMessage());
                    }
                }

                if (env('APP_ENV') == 'local') {
                    try {
                        Mail::to('robin@qubiqx.com')->send(new AdminFormSubmitConfirmationMail($form, $formInput, $sendToFieldValue));
                    } catch (\Exception $e) {
                        dd($e->getMessage());
                    }
                } else {
                    try {
                        $notificationFormInputsEmails = Customsetting::get('notification_form_inputs_emails', Sites::getActive(), '[]');
                        if ($notificationFormInputsEmails) {
                            foreach (json_decode($notificationFormInputsEmails) as $notificationFormInputsEmail) {
                                Mail::to($notificationFormInputsEmail)->send(new AdminFormSubmitConfirmationMail($form, $formInput, $sendToFieldValue));
                            }
                        }
                    } catch (\Exception $e) {
                        dd($e->getMessage());
                    }
                }

                return redirect()->back()->with('success', Translation::get('form-' . Str::slug($form->name) . '-succesfully-submitted', 'form', 'The form has been submitted'));
            }
        }

        return redirect()->back()->with('error', Translation::get('form-name-not-provided', 'form', 'Form not found, please contact a administrator'))->withInput();
    }
}
