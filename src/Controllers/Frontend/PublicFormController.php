<?php

namespace Dashed\DashedForms\Controllers\Frontend;

use Dashed\DashedForms\Models\Form;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;

/**
 * Renders the standalone public page of a form (/aanmelden/{slug}).
 *
 * The site theme can override the page with `{theme}.forms.public-page`;
 * otherwise a minimal page from this package is used.
 */
class PublicFormController extends Controller
{
    public function __invoke(string $slug)
    {
        $form = Form::public()->where('slug', $slug)->firstOrFail();

        $title = $form->public_title ?: $form->name;

        seo()->metaData('metaTitle', $title);
        if ($description = str(strip_tags((string) $form->public_intro))->squish()->limit(160)->toString()) {
            seo()->metaData('metaDescription', $description);
        }
        if ($form->public_image) {
            // Stored by a Filament FileUpload, so a path on the Filament disk.
            seo()->metaData('metaImage', Storage::disk(config('filament.default_filesystem_disk', 'public'))->url($form->public_image));
        }

        View::share('model', $form);
        View::share('breadcrumbs', [
            ['name' => 'Home', 'url' => url('/')],
            ['name' => $title, 'url' => $form->publicUrl()],
        ]);

        $themeView = config('dashed-core.site_theme', 'dashed') . '.forms.public-page';

        return view(View::exists($themeView) ? $themeView : 'dashed-forms::frontend.public-page', [
            'form' => $form,
            'title' => $title,
        ]);
    }
}
