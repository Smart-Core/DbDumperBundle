<?php

namespace SmartCore\Bundle\DbDumperBundle\Manager;

use Doctrine\ORM\EntityManager;
use SmartCore\Bundle\DbDumperBundle\Database\MySQL;
use SmartCore\Bundle\DbDumperBundle\Database\PostgreSQL;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class DatabaseManager
{
    use ContainerAwareTrait;

    /** @var \SmartCore\Bundle\DbDumperBundle\Database\MySQL */
    protected $db = null;

    /** @var string */
    protected $archive;

    /** @var string */
    protected $backups_dir;

    /** @var \Doctrine\ORM\EntityManager */
    protected $em;

    /** @var string */
    protected $platform = null;

    /** @var int */
    protected $timeout;

    /** @var string */
    protected $filename;

    /**
     * @param EntityManager $em
     * @param string $backups_dir
     * @param int $timeout
     * @param string|null $filename
     */
    public function __construct(EntityManager $em, $backups_dir, $timeout, $filename = null)
    {
        $this->em           = $em;
        $this->archive      = null;
        $this->backups_dir  = $backups_dir;
        $this->filename     = $filename;
        $this->timeout      = $timeout;
    }

    public function init()
    {
        if ($this->db) {
            return;
        }

        $archive = $this->container->getParameter('smart_db_dumper.archive');
        if ($archive !== 'none') {
            $this->archive = $archive;
        }

        $this->platform = $this->em->getConnection()->getDatabasePlatform()->getName();

        $connection = $this->container->get('doctrine.dbal.default_connection');
        $connectionParams = $connection->getParams();

        $paramsCommon = [
            'all_databases' => false,
            'database' => $connectionParams['dbname'],
            'db_user' => $connectionParams['user'],
            'db_password' => $connectionParams['password'],
            'db_host' => $connectionParams['host'],
            'db_port' => $connectionParams['port'] ?: 3306,
            'ignore_tables' => [],
        ];

        switch ($this->platform) {
            case 'mysql':
                $params['mysql'] = $paramsCommon;
                $this->db = new MySQL($params, $this->backups_dir, date('Y-m-d_H-i-s_'), $this->filename);
                break;
            case 'postgresql':
                $params['postgresql'] = $paramsCommon;
                $this->db = new PostgreSQL($params, $this->backups_dir, date('Y-m-d_H-i-s_'), $this->filename);
                break;
            default:
                throw new \Exception('Unknown database platform: '.$this->platform);
        }
    }

    public function import($path = null)
    {
        return $this->db->import($path);
    }

    public function dump($filename = null)
    {
        $currentDbFilename = $this->db->getFileName();

        if ($filename) {
            $this->db->setFileName($filename);
        }

        $this->db->dump();

        if ($this->archive === 'gz') {
            $file = $this->gzip($this->db->getPath());

            unlink($this->db->getPath());

            $this->db->setFileName($currentDbFilename);

            return $file;
        }

        if ($this->archive === 'zip') {
            $file = $this->zip($this->db->getPath());

            unlink($this->db->getPath());

            $this->db->setFileName($currentDbFilename);

            return $file;
        }

        $this->db->setFileName($currentDbFilename);

        return $this->db->getPath();
    }

    public function getPath()
    {
        return $this->archive ? $this->db->getPath().'.'.$this->archive : $this->db->getPath();
    }

    /**
     * @return MySQL
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * @return string
     */
    public function getPlatform()
    {
        return $this->platform;
    }

    /**
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @return string
     */
    public function getBackupsDir()
    {
        return $this->backups_dir;
    }

    /**
     * @return string
     */
    public function getDefaultDumpFilePath()
    {
        if ($this->filename) {
            $path = $this->container->getParameter('kernel.project_dir').'/'.$this->getFilename(true);
        } else {
            $path = $this->container->getParameter('kernel.project_dir').'/'.$this->container->get('doctrine.dbal.default_connection')->getDatabase().$this->getFilenameExtension();
        }

        return $path;
    }

    /**
     * @param bool $ext
     *
     * @return null|string
     */
    public function getFilename($ext = false)
    {
        $filename = $this->filename;

        if ($ext) {
            $filename .= $this->getFilenameExtension();
        }

        return $filename;
    }

    /**
     * @return string
     */
    public function getFilenameExtension()
    {
        $ext = '.sql';

        if ($this->archive) {
            $ext .= '.' . $this->archive;
        }

        return $ext;
    }

    /**
     * @return string
     */
    public function getArchive()
    {
        return $this->archive;
    }

    /**
     * @param string $archive
     *
     * @return $this
     */
    public function setArchive($archive)
    {
        $this->archive = $archive;

        return $this;
    }

    protected function gzip($filename)
    {
        // Name of the gz file we're creating
        $gzfile = $filename.".gz";

        // Open the gz file (w9 is the highest compression)
        $fp = gzopen($gzfile, 'w9');

        // Compress the file
        gzwrite($fp, file_get_contents($filename));

        // Close the gz file and we're done
        gzclose($fp);

        return $gzfile;
    }

    protected function zip($filename)
    {
        $zip = new \ZipArchive();

        $zip->open($filename . '.zip', \ZipArchive::CREATE);

        $zip->addFile($filename, basename($filename));

        $zip->close();

        return $filename . '.zip';
    }
}
