<?php

namespace QuickCrud\Commands;

use Illuminate\Console\Command;
use QuickCrud\Services\CrudGeneratorService;

class GenerateCrudCommand extends Command
{
    // Use `*` to capture fields as an array
   protected $signature = 'quick-crud:generate {name} {--fields=}';

    protected $description = 'Generate CRUD operations for a model';

   public function handle()
{
    $name = $this->argument('name');
    $fieldsString = $this->option('fields');

    // Validate and parse fields from comma-separated string
    $fields = array_map('trim', explode(',', $fieldsString));

    foreach ($fields as $field) {
        if (!preg_match('/^\w+:\w+$/', $field)) {
            $this->error("Invalid field format: $field. Use name:type format.");
            return Command::FAILURE;
        }
    }

   (new CrudGeneratorService($name, $fields, $this))->generate();


    $this->info("CRUD for {$name} generated successfully.");
    return Command::SUCCESS;
}

}
