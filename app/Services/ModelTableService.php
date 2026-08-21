<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class ModelTableService
{
    public function getModelsForTables(): array
    {
        $models = [];

        $this->discoverModels(app_path('Models'), 'App\\Models', $models);

        if (File::isDirectory(base_path('Modules'))) {
            foreach (File::directories(base_path('Modules')) as $modulePath) {
                $moduleName = basename($modulePath);
                $modelsPath = $modulePath.'/Models';

                if (File::isDirectory($modelsPath)) {
                    $this->discoverModels($modelsPath, "Modules\\{$moduleName}\\Models", $models);
                }
            }
        }

        return $models;
    }

    protected function discoverModels(string $path, string $namespace, array &$models): void
    {
        if (! File::isDirectory($path)) {
            return;
        }

        $items = File::allFiles($path);

        foreach ($items as $item) {
            $relativePath = $item->getPathname();
            $className = $item->getFilenameWithoutExtension();

            $subNamespace = trim(str_replace([$path, '.php'], '', $relativePath), DIRECTORY_SEPARATOR);
            $subNamespace = str_replace(DIRECTORY_SEPARATOR, '\\', $subNamespace);

            $fullNamespace = $namespace.($subNamespace ? '\\'.$subNamespace : '');

            if (! class_exists($fullNamespace)) {
                continue;
            }

            $reflection = new \ReflectionClass($fullNamespace);

            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                continue;
            }

            if (! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            /** @var Model $instance */
            $instance = $reflection->newInstanceWithoutConstructor();

            $table = $instance->getTable();

            $key = $reflection->getShortName();
            $models[$key] = $table;
        }
    }
}
