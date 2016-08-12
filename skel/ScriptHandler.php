<?php
namespace Skel;

use Composer\Script\Event;
use Composer\IO\IOInterface;

class ScriptHandler
{
    /**
     * @param Event $event
     */
    private $event;

    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    private function ask($question, $default = null)
    {
        if ($default) {
            $question = sprintf('<question>%s</question> [<comment>%s</comment>]: ', $question, $default);
        } else {
            $question = sprintf('<question>%s</question>: ', $question);
        }
        return $this->event->getIO()->ask($question, $default);
    }

    private function filterDirectories($directory)
    {
        $iterator = new \RecursiveDirectoryIterator($directory);
        $recursiveIterator = new \RecursiveCallbackFilterIterator($iterator, function (\SplFileInfo $current, $key, $iterator) {
            if (in_array($current->getFilename(), ['.git', 'vendor'])) {
                return false;
            }
            $iterator->hasChildren();
            return true;
        });
        foreach (new \RecursiveIteratorIterator($recursiveIterator) as $file) {
            if ($file->isFile()) {
                yield $file;
            }
        }
    }

    private function formatCamelCase($name)
    {
        return str_replace(' ', '', ucwords(str_replace(['-','_'], ' ', $name)));
    }

    private function formatNamespace($organisation, $name)
    {
        return $this->formatCamelCase($organisation) . '\\' . $this->formatCamelCase($name);
    }

    protected function askQuestions()
    {
        $data = [];
        $data['project_organisation'] = $this->ask('What is the project\'s organisation?');
        $data['project_name'] = $this->ask('What is the project\'s name?');
        $default = $this->formatNamespace($data['project_organisation'], $data['project_name']);
        $data['project_namespace'] = $this->ask('What is the project\'s namespace?', $default);
        $data['project_bin'] = $this->ask('What is the project\'s binary?', strtolower($data['project_name']));
        $data['project_namespace_escaped'] = str_replace('\\', '\\\\', $data['project_namespace']);

        return $data;
    }

    protected function convertTemplates($data)
    {
        $replacePatterns = [];
        foreach ($data as $key => $value) {
            $replacePatterns['{{'.$key.'}}'] = $value;
        }

        foreach ($this->filterDirectories(getcwd()) as $file) {
            $filename = $file->getPathName();
            $content = file_get_contents($filename);
            $content = str_replace(array_keys($replacePatterns), array_values($replacePatterns), $content);
            if ($file->getFilename() == '.gitignore') {
                $content = str_replace('project_bin', $data['project_bin'], $content);
            }

            file_put_contents($filename, $content);
        }
        rename(__DIR__ . '/../bin/project_bin', __DIR__ . '/../bin/' . $data['project_bin']);
    }

    protected function cleanupSkeleton()
    {
        unlink(__FILE__);
        rmdir(__DIR__);
    }

    public function run()
    {
        $data = $this->askQuestions();

        $this->convertTemplates($data);
        $this->cleanupSkeleton();
    }

    public static function postCreateProject(Event $event)
    {
        (new self($event))->run();
    }
}