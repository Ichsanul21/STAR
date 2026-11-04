<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DevReset extends Command
{
    protected $signature = 'dev:reset {--demo : Reseed the full demo dataset} {--empty : Reset schema and seed only the developer account}';

    protected $description = 'Reset the STAR database for developer testing (demo reseed or empty reset).';

    public function handle(): int
    {
        if (! $this->option('demo') && ! $this->option('empty')) {
            $this->error('Pilih --demo atau --empty.');

            return self::FAILURE;
        }

        $this->warn('Menjalankan migrate:fresh. Semua data akan dihapus.');
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->line(trim(Artisan::output()));

        if ($this->option('demo')) {
            Artisan::call('db:seed', ['--force' => true]);
            $this->line(trim(Artisan::output()));
            $this->info('Demo dataset berhasil di-reseed.');

            return self::SUCCESS;
        }

        Artisan::call('db:seed', ['--class' => \Database\Seeders\DevSeeder::class, '--force' => true]);
        $this->line(trim(Artisan::output()));
        $this->info('Database kosong siap. Akun developer telah dibuat.');

        return self::SUCCESS;
    }
}
