<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PrettyQuestion extends Question
{
    public function getQuestion()
    {
        if ($this->getDefault()) {
            return parent::getQuestion() . ' [' . $this->getDefault() . ']: ';
        } else {
            return parent::getQuestion() . ': ';
        }
    }
}

class PostCreateProjectCommand extends Command
{
    protected function configure()
    {
        $this->setName('default');
    }

    private function filterDirectories($directory)
    {
        $iterator = new RecursiveDirectoryIterator($directory);
        $recursiveIterator = new RecursiveCallbackFilterIterator($iterator, function (SplFileInfo $current, $key, $iterator) {
            if (in_array($current->getFilename(), ['.git', 'vendor'])) {
                return false;
            }
            $iterator->hasChildren();
            return true;
        });
        foreach (new RecursiveIteratorIterator($recursiveIterator) as $file) {
            if ($file->isFile()) {
                yield $file;
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* @var $questionHelper QuestionHelper */
        $questionHelper = $this->getHelper('question');

        $data['project_organisation'] = $questionHelper->ask($input, $output, new PrettyQuestion('What is the project\'s organisation?'));
        $data['project_name'] = $questionHelper->ask($input, $output, new PrettyQuestion('What is the project\'s name?'));
        $default = ucfirst($data['project_organisation'] . '\\' . ucfirst($data['project_name']));
        $data['project_namespace'] = $questionHelper->ask($input, $output, new PrettyQuestion('What is the project\'s namespace?', $default));
        $data['project_bin'] = $questionHelper->ask($input, $output, new PrettyQuestion('What is the project\'s binary?', strtolower($data['project_name'])));

        $replacePatterns = [];
        foreach ($data as $key => $value) {
            $replacePatterns['{{'.$key.'}}'] = $value;
        }

        foreach ($this->filterDirectories(__DIR__) as $file) {
            $filename = $file->getPathName();
            $content = file_get_contents($filename);
            $content = str_replace(array_keys($replacePatterns), array_values($replacePatterns), $content);
            if ($file->getFilename() == '.gitignore') {
                $content = str_replace('project_bin', $data['project_bin'], $content);
            }

            file_put_contents($filename, $content);
        }
        rename(__DIR__ . '/bin/project_bin', __DIR__ . '/bin/' . $data['project_bin']);
    }
}

$app = new Application();
$app->add(new PostCreateProjectCommand());
$app->setDefaultCommand('default');
$app->run();

unlink(__FILE__);
