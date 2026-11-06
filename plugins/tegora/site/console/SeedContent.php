<?php namespace Tegora\Site\Console;

use Cms\Classes\Theme;
use File;
use Illuminate\Console\Command;
use Tailor\Classes\BlueprintIndexer;
use Tailor\Models\RecordImport;
use Yaml;

/**
 * Loads the theme's starting content, once per blueprint.
 *
 * October's own theme:seed re-creates any record that has been deleted, so a
 * deploy could bring back something the client removed. Here a blueprint is
 * only seeded while it holds no real content (deleted records count as content).
 * Globals and singles create an empty record on first page view, which is why
 * a record with only blank fields does not count.
 */
class SeedContent extends Command
{
    protected $signature = 'tegora:seed {--uuid= : Development only: re-import this blueprint over its existing records.}';

    protected $description = 'Imports starting content for blueprints that have none yet.';

    public function handle()
    {
        $theme = Theme::load('tegora');
        $file = $theme->getPath() . '/seeds/data.yaml';

        if (!File::isFile($file)) {
            $this->warn('No seed file.');
            return 0;
        }

        BlueprintIndexer::instance()->setThemeContext('tegora');

        try {
            BlueprintIndexer::instance()->migrate();

            foreach (Yaml::parseFile($file) as $item) {
                $this->seedItem($theme, $item);
            }
        }
        finally {
            BlueprintIndexer::instance()->setThemeContext(null);
        }

        return 0;
    }

    protected function seedItem($theme, array $item): void
    {
        $seedFile = $theme->getPath() . '/' . $item['file'];
        if (!File::isFile($seedFile)) {
            $this->warn("- no seed file yet: {$item['name']}");
            return;
        }

        $forced = $this->option('uuid');
        if ($forced && $forced !== $item['attributes']['blueprint_uuid']) {
            return;
        }

        $import = new class extends RecordImport {
            public function existingRecords()
            {
                return $this->resolveBlueprintModel()->newQueryWithoutScopes()->get();
            }
        };
        $import->forceFill($item['attributes']);

        $records = $import->existingRecords();
        $fields = array_keys($import->getContentFieldsetDefinition()->getAllFields());

        if (!$forced) {
            foreach ($records as $record) {
                foreach ($fields as $field) {
                    if (!blank($record->getAttribute($field))) {
                        $this->line("- skipped (has content): {$item['name']}");
                        return;
                    }
                }
            }
        }

        // Fill in any blank record left by a first page view
        $import->update_existing = $records->isNotEmpty();
        $import->setSourcePrefix($theme->getPath());
        $import->importFile($seedFile, ['sessionKey' => str_random(40)]);

        $stats = $import->getResultStats();
        $this->info("- {$item['name']}: {$stats->created} created, {$stats->updated} updated");

        if ($stats->errorCount > 0) {
            $this->error(json_encode($stats->errors, JSON_UNESCAPED_UNICODE));
        }
    }
}
