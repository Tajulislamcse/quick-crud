<?php

namespace QuickCrud\Commands;

use Illuminate\Console\Command;
use QuickCrud\Services\CrudGeneratorService;

class GenerateCrudCommand extends Command
{
    protected $signature = 'make:quick-crud {name}';
    protected $description = 'Generate CRUD files for a model';

    public function handle()
    {
        $name = $this->argument('name');

        $service = new CrudGeneratorService();
        $service->generate($name);

        $this->info("CRUD for {$name} generated successfully.");
    }
}
