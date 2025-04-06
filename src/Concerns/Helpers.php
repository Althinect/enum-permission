<?php

namespace Althinect\EnumPermission\Concerns;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionException;

trait Helpers
{
    /**
     * Extract namespace from a file.
     *
     * @param  string  $file  Path to the file
     * @return string The extracted namespace
     */
    protected function extractNamespace($file): string
    {
        $ns = '';
        $handle = fopen($file, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+);/', $line, $matches)) {
                    $ns = $matches[1];
                    break;
                }
            }
            fclose($handle);
        }

        return $ns;
    }

    /**
     * Check if a class is an enum.
     *
     * @param  string  $classPath  Full class path
     * @return bool Whether the class is an enum
     */
    protected function isEnumClass(string $classPath): bool
    {
        try {
            $reflection = new ReflectionClass($classPath);

            return $reflection->isEnum();
        } catch (ReflectionException $e) {
            return false;
        }
    }

    /**
     * Get classes in a directory.
     *
     * @param  string  $path  Directory path
     * @return array Array of ReflectionClass objects
     */
    protected function getClassesInDirectory(string $path): array
    {
        $files = File::allFiles($path);
        $classes = [];

        foreach ($files as $file) {
            $namespace = $this->extractNamespace($file);
            $class = $namespace.'\\'.$file->getFilenameWithoutExtension();

            try {
                $reflection = new ReflectionClass($class);
                if (! $reflection->isAbstract()) {
                    $classes[] = $reflection;
                }
            } catch (ReflectionException $e) {
                // Skip classes that cannot be loaded
                continue;
            }
        }

        return $classes;
    }

    /**
     * Prompt user to confirm file overwrite.
     *
     * @param  string  $filePath  Path to the file
     * @return bool Whether to overwrite the file
     */
    protected function shouldOverwriteFile(string $filePath): bool
    {
        if (! function_exists('Laravel\Prompts\select')) {
            // Fallback for environments without Laravel Prompts
            $this->info('File '.$filePath.' already exists.');

            return $this->confirm('Do you want to overwrite it?', false);
        }

        return \Laravel\Prompts\select(
            required: true,
            label: 'File '.$filePath.' already exists. Do you want to overwrite it?',
            options: ['yes', 'no'],
        ) === 'yes';
    }

    /**
     * Get all permission files in the application.
     *
     * @param  string|null  $customPath  Custom path to search in
     * @return array Array of permission files
     */
    protected function getPermissionFiles(?string $customPath = null): array
    {
        $permissionClasses = [];
        $files = File::allFiles($customPath ?? app_path());

        // Search all the Enum files that are Suffixed with Permission
        foreach ($files as $file) {
            $fileName = $file->getFilename();
            $fileExtension = $file->getExtension();

            if ($fileExtension === 'php' && strpos($fileName, 'Permission') !== false) {
                $permissionClasses[] = $file;
            }
        }

        return $permissionClasses;
    }
}
