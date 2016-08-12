<?php
namespace Skel;

use Composer\Script\Event;
use Composer\Util\ProcessExecutor;
use Composer\Json\JsonFile;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ExecutableFinder;


class ScriptHandler
{
    /**
     * @param Event $event
     */
    private $event;
    
    /**
     * @var ProcessExecutor $process
     */
    private $process;

    /**
     * @var array
     */
    private $gitConfig = null;

    public function __construct(Event $event, ProcessExecutor $process = null)
    {
        $this->event = $event;
        $this->process = $process ?: new ProcessExecutor($event->getIO());
    }

    private function ask($question, $default = null, $validate = null)
    {
        if ($default) {
            $question = sprintf('%s [<comment>%s</comment>]: ', $question, $default);
        } else {
            $question = sprintf('%s: ', $question);
        }
        if ($validate) {
            return $this->event->getIO()->askAndValidate($question, $validate, null, $default);
        } else {
            return $this->event->getIO()->ask($question, $default);
        }
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

    private function formatDefaultNamespace($package)
    {
        return implode('\\', array_map(
            function ($name) {
                return str_replace(' ', '', ucwords(str_replace(['-','_'], ' ', $name)));
            },
            explode('/', $package)
        ));
    }

    private function getGitConfig()
    {
        if (null !== $this->gitConfig) {
            return $this->gitConfig;
        }
        $finder = new ExecutableFinder();
        $gitBin = $finder->find('git');
        $cmd = new Process(sprintf('%s config -l', ProcessExecutor::escape($gitBin)));
        $cmd->run();
        if ($cmd->isSuccessful()) {
            $this->gitConfig = array();
            preg_match_all('{^([^=]+)=(.*)$}m', $cmd->getOutput(), $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $this->gitConfig[$match[1]] = $match[2];
            }
            return $this->gitConfig;
        }
        return $this->gitConfig = array();
    }

    private function getDefaultPackageName()
    {
        $git = $this->getGitConfig();
        $name = basename(getcwd());
        $name = preg_replace('{(?:([a-z])([A-Z])|([A-Z])([A-Z][a-z]))}', '\\1\\3-\\2\\4', $name);
        $name = strtolower($name);
        if (isset($git['github.user'])) {
            $name = $git['github.user'] . '/' . $name;
        } elseif (!empty($_SERVER['USERNAME'])) {
            $name = $_SERVER['USERNAME'] . '/' . $name;
        } elseif (get_current_user()) {
            $name = get_current_user() . '/' . $name;
        } else {
            // package names must be in the format foo/bar
            $name = $name . '/' . $name;
        }
        return strtolower($name);
    }

    protected function runComposerCommand(...$args)
    {
        $finder = new PhpExecutableFinder();
        $phpPath = $finder->find();
        if (!$phpPath) {
            throw new \RuntimeException('Failed to locate PHP binary to execute '.implode(' ', $args));
        }
        $command = array_merge([$phpPath, realpath($_SERVER['argv'][0])], $args);
        $exec = implode(' ', array_map('escapeshellarg', $command));
        if (0 !== ($exitCode = $this->process->execute($exec))) {
            $this->io->writeError(sprintf('<error>Script %s returned with error code '.$exitCode.'</error>', $exec));
            throw new ScriptExecutionException('Error Output: '.$this->process->getErrorOutput(), $exitCode);
        }
    }

    protected function askQuestions()
    {
        $data = [];
        $name = $this->getDefaultPackageName();
        $data['package_name'] = $this->ask('Package name (<vendor>/<name>)', $name);
        list($data['project_organisation'], $data['project_name']) = explode('/', $data['package_name'], 2);
        $data['package_description'] = $this->ask('Description');
        $data['package_license'] = $this->ask('License (e.g. MIT,proprietary)');

        $default = $this->formatDefaultNamespace($data['package_name']);
        $data['project_namespace'] = $this->ask('Source namespace', $default);
        $data['project_bin'] = $this->ask('Project binary name', strtolower($data['project_name']));
        $data['project_namespace_escaped'] = str_replace('\\', '\\\\', $data['project_namespace']);

        return $data;
    }

    protected function convertTemplates(array $data)
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
        rename('bin/project_bin', 'bin/' . $data['project_bin']);
        unlink('README.md');
        rename('README.project.md', 'README.md');
    }

    protected function updateJson(array $data)
    {
        $file = new JsonFile('composer.json');
        $config = $file->read();

        unset($config['autoload']['psr-4']['Skel\\']);
        unset($config['scripts']['post-create-project-cmd']);
        unset($config['authors']);

        $config['name'] = $data['package_name'];
        if ($data['package_description']) {
            $config['description'] = $data['package_description'];
        } else {
            unset($config['description']);
        }
        if ($data['package_license']) {
            $config['license'] = $data['package_license'];
        } else {
            unset($config['license']);
        }
        $config['autoload']['psr-4'][$data['project_namespace'] . "\\"] = 'src/';

        $file->write($config);
    }

    protected function cleanupSkeleton()
    {
        $this->runComposerCommand('update');
        unlink(__FILE__);
        rmdir(__DIR__);
    }

    public function run()
    {
        $data = $this->askQuestions();

        $this->convertTemplates($data);
        $this->updateJson($data);
        $this->cleanupSkeleton();
    }

    public static function postCreateProject(Event $event)
    {
        (new self($event))->run();
    }
}