<?php

namespace App\Console\Commands;

use App\Models\PostMedia;
use Illuminate\Console\Command;

class DiagnoseMediaPath extends Command
{
    protected $signature   = 'media:diagnose {--limit=5}';
    protected $description = 'Show resolved filesystem paths for recent PostMedia records';

    public function handle(): int
    {
        $items = PostMedia::query()->latest()->limit((int) $this->option('limit'))->get();

        if ($items->isEmpty()) {
            $this->warn('No PostMedia records found.');

            return self::SUCCESS;
        }

        foreach ($items as $m) {
            $urlPath  = parse_url($m->url, PHP_URL_PATH) ?? '';
            $relative = ltrim((string) $urlPath, '/');

            if (str_starts_with($relative, 'storage/')) {
                $rel  = substr($relative, strlen('storage/'));
                $path1 = storage_path('app/public/'.str_replace('/', DIRECTORY_SEPARATOR, $rel));
                $path2 = null;
            } else {
                $path1 = null;
                $path2 = public_path(str_replace('/', DIRECTORY_SEPARATOR, $relative));
            }

            $this->line('─────────────────────────────────────────────');
            $this->line('ID        : '.$m->id);
            $this->line('URL       : '.$m->url);
            $this->line('URL path  : '.$urlPath);

            if ($path1 !== null) {
                $flag = file_exists($path1) ? '<info>✅ EXISTS</info>' : '<error>❌ NOT FOUND</error>';
                $this->line("storage/  : {$path1} → ");
                $this->line('  '.$flag);
            }

            if ($path2 !== null) {
                $flag = file_exists($path2) ? '<info>✅ EXISTS</info>' : '<error>❌ NOT FOUND</error>';
                $this->line("public/   : {$path2} → ");
                $this->line('  '.$flag);
            }
        }

        $this->line('─────────────────────────────────────────────');
        $this->line('public_path()        : '.public_path());
        $this->line('storage_path(app/public): '.storage_path('app/public'));
        $this->line('storage symlink      : '.(file_exists(public_path('storage')) ? '<info>✅ present</info>' : '<error>❌ MISSING — run: php artisan storage:link</error>'));

        return self::SUCCESS;
    }
}
