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

            $replyTo = filter_var($record->email, FILTER_VALIDATE_EMAIL) ?: null;

            Mail::send('tegora.site::mail.lead', $this->leadMailData($record, $blueprint, $replyTo), function ($message) use ($recipient, $replyTo) {
                $message->to($recipient);
                if ($replyTo) {
                    $message->replyTo($replyTo);
                }
            });
        }
        catch (\Throwable $ex) {
            Log::error($ex);
        }
    }

    /**
     * Turns the saved request into rows for views/mail/lead.htm: option labels
     * instead of stored keys, and the free-text fields kept for their own blocks.
     */
    protected function leadMailData($record, $blueprint, ?string $replyTo): array
    {
        $fields = $record->getContentFieldsetDefinition()->getAllFields();
        $label = function ($name) use ($record, $fields) {
            $value = $record->getAttribute($name);
            $options = $fields[$name]->options ?? null;
            if (is_array($options) && is_scalar($value) && isset($options[$value])) {
                return $options[$value];
            }
            if (is_array($value)) {
                $value = implode(', ', array_filter($value, 'is_scalar'));
            }
            return is_scalar($value) ? trim((string) $value) : '';
        };

        // Shown elsewhere in the email, or always set (consent is required)
        $separate = ['sursa', 'mesaj', 'detalii_configurator', 'gdpr'];

        $rows = [];
        foreach ($fields as $name => $field) {
            if (in_array($name, $separate) || ($value = $label($name)) === '') {
                continue;
            }
            $href = null;
            if ($name === 'telefon') {
                $href = 'tel:' . preg_replace('/[^\d+]/', '', $value);
            }
            elseif ($name === 'email' && $replyTo) {
                $href = 'mailto:' . $replyTo;
            }
            $rows[] = ['label' => $field->label ?? $name, 'value' => $value, 'href' => $href];
        }

        // The configurator sends one "Step: choice" line per step
        $details = [];
        foreach (preg_split('/\R/', $label('detalii_configurator')) as $line) {
            if (($line = trim($line)) !== '') {
                [$step, $choice] = array_pad(explode(': ', $line, 2), 2, '');
                $details[] = ['label' => $step, 'value' => $choice];
            }
        }

        return [
            'title' => $record->title,
            'source' => $label('sursa') ?: 'de pe site',
            'received' => ($record->created_at ?: now())->copy()
                ->setTimezone(config('cms.timezone', 'Europe/Bucharest'))
                ->format('d.m.Y, H:i'),
            'rows' => $rows,
            'details' => $details,
            'message' => $label('mesaj'),
            'replyTo' => $replyTo,
            'backendUrl' => \Backend::url('tailor/entries/' . $blueprint->handleSlug . '/' . $record->getKey()),
        ];
    }
}
