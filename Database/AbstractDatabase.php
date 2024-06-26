<?php
namespace SmartCore\Bundle\DbDumperBundle\Database;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Class BaseDatabase.
 *
 * @author  Jonathan Dizdarevic <dizda@dizda.fr>
 */
abstract class AbstractDatabase implements DatabaseInterface
{
    const DB_PATH = '';

    protected $dataPath;
    protected $filesystem;
    protected $timeout;

    protected $host;
    protected $port;
    protected $user;
    protected $password;

    /**
     * Get SF2 Filesystem.
     *
     * @param string $basePath
     */
    public function __construct($basePath)
    {
        $this->dataPath = $basePath.static::DB_PATH.'/';
        $this->filesystem = new Filesystem();
        $this->timeout = 300;
    }

    /**
     * Handle process error on fails.
     *
     * @param string $command
     *
     * @throws \RuntimeException
     */
    protected function execute($command): void
    {
        $process = Process::fromShellCommandline($command, null, null, null, $this->timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
    }

    /**
     * Prepare path for dump file.
     */
    protected function preparePath(): void
    {
        $this->filesystem->mkdir($this->dataPath);
    }

    /**
     * @param int $timeout
     *
     * @return $this
     */
    public function setTimeout($timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }
}
