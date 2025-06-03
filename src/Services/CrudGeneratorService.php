<?php

namespace QuickCrud\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Filesystem\Filesystem; // This dependency is declared but not used in the original code for file operations.

class CrudGeneratorService
{
    protected string $name;
    protected array $fields;
    protected Filesystem $files; // Declared but not used in file operations in the provided code
    protected $command; // Property to hold the Artisan command instance

    public function __construct($name, $fields, $command = null)
    {
        $this->name = $name;
        $this->fields = $fields;
        $this->command = $command;
        // If Filesystem was meant to be used, it would be instantiated here:
        // $this->files = new Filesystem();
    }

    public function generate()
    {
        $this->generateModel();
        $this->generateMigration();
        $this->generateRequest();
        $this->generateSeeder();
        $this->generateController();
        $this->generateViews();
        $this->generateRoute();
        $this->generateDataTable();
        $this->runMigration();
        $this->runSeeder();
    }

    protected function generateModel()
    {
        $modelTemplate =
            "<?php

            namespace App\\Models;

            use Illuminate\\Database\\Eloquent\\Model;

            class {$this->name} extends Model
            {
                protected \$fillable = [" .
                        $this->fillableFields() .
                        "];
            }";

        $this->putFile(app_path("Models/{$this->name}.php"), $modelTemplate);
    }

    protected function generateMigration()
    {
        $table = Str::snake(Str::pluralStudly($this->name));
        $migrationFileNamePart = "_create_{$table}_table.php";
        $migrationPath = database_path('migrations');

        // Check for existing migration
        $existing = collect(glob($migrationPath . '/*' . $migrationFileNamePart))
            ->first();

        if ($existing) {
            if ($this->command) {
                $this->command->warn("Skipped (migration already exists): " . basename($existing));
            } else {
                echo "Skipped (migration already exists): " . basename($existing) . "\n";
            }
            return;
        }

        // No existing migration — generate it
        $timestamp = date('Y_m_d_His');
        $fieldsMigration = $this->migrationFields();

        $migrationTemplate = "<?php

    use Illuminate\\Database\\Migrations\\Migration;
    use Illuminate\\Database\\Schema\\Blueprint;
    use Illuminate\\Support\\Facades\\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::create('$table', function (Blueprint \$table) {
                \$table->id();
$fieldsMigration
                \$table->timestamps();
            });
        }

        public function down(): void
        {
            Schema::dropIfExists('$table');
        }
    };";

        $this->putFile("{$migrationPath}/{$timestamp}_create_{$table}_table.php", $migrationTemplate);
    }


    protected function generateRequest()
    {
        $fieldsRules = collect($this->fields)
            ->map(fn($f) => "'" . explode(':', $f)[0] . "' => 'required'")
            ->implode(",\n            ");

        $requestTemplate = "<?php

        namespace App\\Http\\Requests;

        use Illuminate\\Foundation\\Http\\FormRequest;

        class Store{$this->name}Request extends FormRequest
        {
            public function authorize(): bool
            {
                return true;
            }

            public function rules(): array
            {
                return [
                    $fieldsRules
                ];
            }
        }";

        $this->putFile(app_path("Http/Requests/Store{$this->name}Request.php"), $requestTemplate);
    }

protected function generateSeeder()
{
    $table = Str::snake(Str::pluralStudly($this->name));

    $fieldsFaker = collect($this->fields)->map(function ($field) {
        [$name, $type] = explode(':', $field);

        $faker = match (trim($type)) {
            'string', 'char', 'varchar' => "fake()->word",
            'text' => "fake()->sentence",
            'integer', 'bigInteger', 'smallInteger', 'tinyInteger' => "fake()->numberBetween(1, 100)",
            'decimal', 'float', 'double' => "fake()->randomFloat(2, 1, 1000)",
            'boolean' => "fake()->boolean",
            'date' => "fake()->date()",
            'datetime', 'timestamp' => "fake()->dateTime()",
            'email' => "fake()->unique()->safeEmail",
            'name' => "fake()->name",
            'phone', 'phone_number' => "fake()->phoneNumber",
            'address' => "fake()->address",
            'city' => "fake()->city",
            'state' => "fake()->state",
            'country' => "fake()->country",
            'uuid' => "fake()->uuid",
            'slug' => "Str::slug(fake()->words(3, true))",
            default => "fake()->word",
        };

        return "                '{$name}' => {$faker},";
    })->implode("\n");

    $seederTemplate = <<<EOD
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\\{$this->name};
use Illuminate\Support\Str;

class {$this->name}Seeder extends Seeder
{
    public function run(): void
    {
        \$data = [];

        for (\$i = 0; \$i < 5; \$i++) {
            \$data[] = [
$fieldsFaker
            ];
        }

        {$this->name}::insert(\$data);
    }
}
EOD;

    $this->putFile(database_path("seeders/{$this->name}Seeder.php"), $seederTemplate);
}



    protected function generateController()
    {
        $controllerTemplate = "<?php

        namespace App\\Http\\Controllers;

        use App\\Models\\{$this->name};
        use App\\Http\\Requests\\Store{$this->name}Request;
        use Illuminate\\Http\\Request;

        class {$this->name}Controller extends Controller
        {
            public function index()
            {
                return view('{$this->viewPath()}.index');
            }

            public function create()
            {
                return view('{$this->viewPath()}.create');
            }

            public function store(Store{$this->name}Request \$request)
            {
                {$this->name}::create(\$request->validated());
                return redirect()->route('{$this->routeName()}.index');
            }

            public function edit({$this->name} \$model)
            {
                return view('{$this->viewPath()}.edit', compact('model'));
            }

            public function update(Store{$this->name}Request \$request, {$this->name} \$model)
            {
                \$model->update(\$request->validated());
                return redirect()->route('{$this->routeName()}.index');
            }

            public function destroy({$this->name} \$model)
            {
                \$model->delete();
                return back();
            }
        }";

        $this->putFile(app_path("Http/Controllers/{$this->name}Controller.php"), $controllerTemplate);
    }

    protected function generateViews()
    {
        $viewPath = resource_path("views/" . $this->viewPath());
        // This mkdir is now technically redundant if putFile creates directories,
        // but it doesn't harm anything.
        if (!file_exists($viewPath)) {
            mkdir($viewPath, 0755, true);
        }

        foreach (["index", "create", "edit"] as $view) {
            $this->putFile("$viewPath/$view.blade.php", "<!-- $view view -->");
        }
    }

    protected function generateRoute()
{
    $resourceName = Str::kebab(Str::plural($this->name));
    $controllerClass = "{$this->name}Controller";
    $controllerNamespace = "App\\Http\\Controllers\\{$controllerClass}";
    $routeLine = "Route::resource('$resourceName', $controllerClass::class);";
    $useLine = "use $controllerNamespace;";

    $webRoutesPath = base_path('routes/web.php');
    $existingRoutes = file_get_contents($webRoutesPath);

    $added = false;

    // Add `use` statement if not already present
    if (strpos($existingRoutes, $useLine) === false) {
        // Insert `use` after the opening PHP tag
        $existingRoutes = preg_replace('/<\?php\s*/', "<?php\n\n$useLine\n", $existingRoutes, 1);
        $added = true;
    }

    // Add route if not already present
    if (strpos($existingRoutes, $routeLine) === false) {
        $existingRoutes .= "\n" . $routeLine;
        $added = true;
    }

    // Save changes if anything was added
    if ($added) {
        file_put_contents($webRoutesPath, $existingRoutes);

        if ($this->command) {
            $this->command->info("Route and use statement added to web.php");
        } else {
            echo "Route and use statement added to web.php\n";
        }
    } else {
        if ($this->command) {
            $this->command->warn("Skipped (route and use already exist): $routeLine");
        } else {
            echo "Skipped (route and use already exist): $routeLine\n";
        }
    }
}



    protected function generateDataTable()
    {
        $directory = app_path('DataTables');

        // Ensure the directory exists
        // This mkdir is now technically redundant if putFile creates directories,
        // but it doesn't harm anything.
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $datatablePath = "{$directory}/{$this->name}DataTable.php";
        $datatableTemplate = "<?php

        namespace App\\DataTables;

        use App\\Models\\{$this->name};
        use Yajra\\DataTables\\DataTableAbstract;
        use Yajra\\DataTables\\Services\\DataTable;

        class {$this->name}DataTable extends DataTable
        {
            public function dataTable(\$query): DataTableAbstract
            {
                return datatables()->eloquent(\$query);
            }

            public function query({$this->name} \$model)
            {
                return \$model->newQuery();
            }

            public function html(): \Yajra\\DataTables\\Html\\Builder
            {
                return \$this->builder()
                    ->setTableId('{$this->routeName()}-table')
                    ->columns([/* Add columns */])
                    ->minifiedAjax();
            }
        }";

        $this->putFile($datatablePath, $datatableTemplate);
    }

    protected function migrationFields(): string
    {
        return collect($this->fields)
            ->map(fn($f) => explode(':', $f))
            ->map(fn($arr) => "            \$table->" . $arr[1] . "('" . $arr[0] . "');")
            ->implode("\n");
    }

    protected function fillableFields(): string
    {
        return collect($this->fields)
            ->map(fn($f) => "'" . explode(':', $f)[0] . "'")
            ->implode(', ');
    }

    protected function viewPath(): string
    {
        return Str::kebab(Str::pluralStudly($this->name));
    }

    protected function routeName(): string
    {
        return Str::kebab(Str::plural($this->name));
    }
    protected function runMigration()
    {
        $exitCode = Artisan::call('migrate');

        if ($this->command) {
            $this->command->info("Migration executed.");
        } else {
            echo "Migration executed.\n";
        }
    }

    protected function runSeeder()
    {
        $seederClass = "{$this->name}Seeder";
        $seederPath = database_path("seeders/{$seederClass}.php");

        if (!file_exists($seederPath)) {
            if ($this->command) {
                $this->command->error("Seeder not found: $seederClass");
            }
            return;
        }

        // Step 1: Register seeder in DatabaseSeeder.php
        $databaseSeederPath = database_path('seeders/DatabaseSeeder.php');
        $contents = file_get_contents($databaseSeederPath);

        if (strpos($contents, "$seederClass::class") === false) {
            $contents = preg_replace(
                '/(public function run\(\): void\s*\{\s*)/',
                "$1\n        \$this->call($seederClass::class);\n",
                $contents
            );

            file_put_contents($databaseSeederPath, $contents);

            if ($this->command) {
                $this->command->info("Seeder registered in DatabaseSeeder.php.");
            } else {
                echo "Seeder registered in DatabaseSeeder.php.\n";
            }
        } else {
            if ($this->command) {
                $this->command->warn("Seeder already registered in DatabaseSeeder.php.");
            } else {
                echo "Seeder already registered in DatabaseSeeder.php.\n";
            }
        }

        // Step 2: Run seeder
        Artisan::call('db:seed', ['--class' => $seederClass]);

        if ($this->command) {
            $this->command->info("Seeder executed.");
        } else {
            echo "Seeder executed.\n";
        }
    }



    /**
     * Writes content to a file, ensuring its parent directory exists.
     * Skips creation if the file already exists.
     *
     * @param string $path The full path to the file.
     * @param string $content The content to write to the file.
     */
    protected function putFile($path, $content)
    {
        $directory = dirname($path);

        // 1. Ensure the parent directory exists. Create it recursively if it doesn't.
        if (!file_exists($directory)) {
            // mkdir($directory, permissions, recursive)
            if (!mkdir($directory, 0755, true)) {
                // Handle error if directory creation fails (e.g., permissions issues)
                if ($this->command) {
                    $this->command->error("Failed to create directory: {$directory}. Check permissions.");
                } else {
                    error_log("Failed to create directory: {$directory}. Check permissions.");
                }
                return; // Abort file creation if directory can't be made
            }
        }

        // 2. Check if the file itself already exists.
        if (file_exists($path)) {
            if ($this->command) {
                $this->command->warn("Skipped (already exists): {$path}");
            } else {
                echo "Skipped (already exists): {$path}\n";
            }
            return;
        }

        // 3. Write the file content.
        file_put_contents($path, $content);

        if ($this->command) {
            $this->command->info("Created: {$path}");
        } else {
            echo "Created: {$path}\n";
        }
    }

}
