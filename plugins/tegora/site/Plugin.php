<?php namespace Tegora\Site;

use Event;
use Log;
use Mail;
use System\Classes\PluginBase;
use Tailor\Models\GlobalRecord;

/**
 * Site-specific behaviour that the CMS does not provide on its own:
 * emailing form requests, hiding test sites from search engines, and the
 * post-deploy tasks in routes.php and console/SeedContent.php.
 */
class Plugin extends PluginBase
{
    const LEADS_HANDLE = 'Leads\Request';

    public function pluginDetails()
    {
        return [
            'name' => 'Tegora Site',
            'description' => 'Notificări pentru formulare și sarcini de deploy.',
            'author' => 'CodeForge',
            'icon' => 'icon-cog',
        ];
    }

    public function register()
    {
        $this->registerConsoleCommand('tegora.seed', \Tegora\Site\Console\SeedContent::class);
    }

    public function boot()
    {
        Event::listen('cms.form.submit', function ($component, $model) {
            $this->emailLead($model);
        });
    }

    public function registerMarkupTags()
    {
        return [
            'functions' => [
                // Search engines stay out until SITE_NOINDEX=false is set at go-live
                'tegoraNoIndex' => fn () => (bool) env('SITE_NOINDEX', true),
            ],
        ];
    }

    /**
     * The request is already saved before this runs, so a mail failure is
     * logged and never shown to the visitor or allowed to lose the lead.
     */
    protected function emailLead($record): void
    {
        try {
            $blueprint = $record->getBlueprintDefinition();
            if ($blueprint->handle !== self::LEADS_HANDLE) {
                return;
            }

            $recipient = GlobalRecord::findForGlobal('Site\Settings')->leads_email;
            if (!$recipient) {
                Log::warning('Tegora lead received but no leads_email is set.');
                return;
            }

            $lines = [];
            foreach ($record->getContentFieldsetDefinition()->getAllFields() as $name => $field) {
                $value = $record->getAttribute($name);
                if (is_array($value)) {
                    $value = implode(', ', array_filter($value, 'is_scalar'));
                }
                if (is_scalar($value) && $value !== '') {
                    $lines[] = ($field->label ?? $name) . ': ' . $value;
                }
            }

            $replyTo = filter_var($record->email, FILTER_VALIDATE_EMAIL) ?: null;

            Mail::raw(implode("\n", $lines), function ($message) use ($recipient, $record, $replyTo) {
                $message->to($recipient)->subject('Cerere nouă de pe site: ' . $record->title);
                if ($replyTo) {
                    $message->replyTo($replyTo);
                }
            });
        }
        catch (\Throwable $ex) {
            Log::error($ex);
        }
    }
}
