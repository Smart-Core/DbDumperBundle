<?php

namespace SmartCore\Bundle\DbDumperBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class RestoreCommand extends ContainerAwareCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setName('smart:dbdumper:restore')
            ->setDescription('Restore default backup.');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dbdumper = $this->getContainer()->get('smart_db_dumper.manager');
        $dialog   = $this->getHelper('dialog');

        $dumpFile = $dbdumper->getDefaultDumpFilePath();

        if (is_dir($dbdumper->getBackupsDir().$dbdumper->getPlatform())) {
            $finder = new Finder();
            $files = $finder->ignoreDotFiles(true)->in($dbdumper->getBackupsDir().$dbdumper->getPlatform());

            if ($files->count()) {
                $output->writeln('<info>Select backup file:</info>');
                $output->writeln('0) <comment>'.$dumpFile.'</comment>');

                $count = 0;
                $fileNames = [];
                $fileNames[$count++] = $dumpFile;
                /** @var \Symfony\Component\Finder\SplFileInfo $file */
                foreach ($files as $file) {
                    $output->writeln($count.') <comment>'.$file->getRelativePathname().'</comment>');
                    $fileNames[$count++] = $file->getRelativePathname();
                }

                $fileId = $dialog->ask($output, 'Please enter the number of dump file [0]: ', '0');

                if (!isset($fileNames[$fileId])) {
                    $output->writeln('<error>Error:</error> File number <comment>'.$fileId.'</comment> does\'t exists.');

                    return false;
                }

                if ($fileId) {
                    $dumpFile = $dbdumper->getBackupsDir().$dbdumper->getPlatform().'/'.$fileNames[$fileId];
                }
            }
        }

        if (!file_exists($dumpFile)) {
            $output->writeln('<error>Error:</error> File <comment>'.$dumpFile.'</comment> does\'t exists.');

            return false;
        }

        $confirm = $dialog->ask($output, '<question>Warning:</question> This action is drop all your database and import from file <comment>'.$dumpFile.'</comment> [y,N]: ', 'n');

        if (strtolower($confirm) !== 'y') {
            $output->writeln('<info>Abort.</info>');

            return false;
        }

//        $this->executeCommand('doctrine:schema:update', ['--force' => true, '--complete' => true]);
//        $this->executeCommand('doctrine:schema:drop', ['--force' => true]);
        $this->executeShellCommand('php app/console doctrine:schema:update --force --complete', $output);
        $this->executeShellCommand('php app/console doctrine:schema:drop --force', $output);

        $output->writeln('Importing from: <comment>'.$dumpFile.'</comment>');

        $dbdumper->import($dumpFile);

        $output->writeln('<info>Restore complete.</info>');
    }

    protected function executeCommand($cmd, array $args = [])
    {
        $args['command'] = $cmd;

        $application = new Application($this->getContainer()->get('kernel'));
        $application->setAutoExit(false);
        $input = new ArrayInput($args);
        $output = new BufferedOutput();

        return $application->run($input, $output);
    }

    protected function executeShellCommand($cmd, OutputInterface $output = null)
    {
        $process = new Process($cmd);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer, false);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('An error occurred when executing the "%s" command.', escapeshellarg($cmd)));
        }
    }
}
