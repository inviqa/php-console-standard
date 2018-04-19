<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HelloWorldCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('hello-world');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('hello-world');
    }
}
