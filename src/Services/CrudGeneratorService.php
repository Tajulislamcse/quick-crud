<?php

namespace QuickCrud\Services;

class CrudGeneratorService
{
    public function generate($name)
    {
        $this->generateModel($name);
    }

    protected function generateModel($name)
    {
        $stubPath = __DIR__ . '/../../stubs/model.stub';
        $targetPath = app_path("Models/{$name}.php");

        $stub = file_get_contents($stubPath);
        $stub = str_replace('{{modelName}}', $name, $stub);

        if (!is_dir(app_path('Models'))) {
            mkdir(app_path('Models'), 0755, true);
        }

        file_put_contents($targetPath, $stub);
    }
}
