<?php

namespace ImageCache2\Console\Command;

use ImageCache2_DataObject_Common;
use PDOException;
use Symfony\Component\Console\Command\Command as sfConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

require_once P2EX_LIB_DIR . '/ImageCache2/bootstrap.php';

class Setup extends sfConsoleCommand
{
    const PG_TRGM_GIST = 'gist';
    const PG_TRGM_GIN = 'gin';

    // {{{ properties

    /**
     * @var array
     */
    private $config;

    /**
     * @var bool
     */
    private $dryRun;

    /**
     * @var string
     */
    private $pgTrgm;

    /**
     * @var ImageCache2_DataObject_Common
     */
    private $db;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var string
     */
    private $serialPrimaryKey;

    /**
     * @var string
     */
    private $tableExtraDefs;

    /**
     * @var string
     */
    private $findTableStatement;

    /**
     * @var string
     */
    private $findIndexFormat;

    // }}}

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this
            ->setName('setup')
            ->setDescription('Setups ImageCache2 environment')
            ->setDefinition(array(
                new InputOption('check-only', null, InputOption::VALUE_NONE, 'Don\'t execute anything'),
                new InputOption('pg-trgm', null, InputOption::VALUE_REQUIRED, 'Enable gist or gin 3-gram index'),
            ));
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->config = ic2_loadconfig();
        $this->dryRun = (bool)$input->getOption('check-only');
        $this->pgTrgm = $input->getOption('pg-trgm');
        $this->output = $output;

        if ($this->checkConfiguration()) {
            $result = $this->connect();
            if ($result) {
                $this->info('Database: OK');
                $this->serialPrimaryKey = $result[0];
                $this->tableExtraDefs = $result[1];
                $this->createTables();
                $this->createIndexes();
            }
        }

        return 0;
    }

    /**
     * @return bool
     */
    private function checkConfiguration()
    {
        $result = true;

        $enabled = $GLOBALS['_conf']['expack.ic2.enabled'];
        $dsn = $this->config['General']['dsn'];
        $driver = $this->config['General']['driver'];

        $this->comment('enabled=' . var_export($enabled, true));
        $this->comment('dsn=' . var_export($dsn, true));
        $this->comment('driver=' . var_export($driver, true));

        if (!$enabled) {
            $this->error("\$_conf['expack.ic2.enabled'] is not enabled in conf/conf_admin_ex.inc.php.");
            $result = false;
        }

        if (!$dsn) {
            $this->error("\$_conf['expack.ic2.general.dsn'] is not set in conf/conf_ic2.inc.php.");
            $result = false;
        }

        $driver = strtolower($driver);
        switch ($driver) {
            case 'imagemagick6':
            case 'imagemagick':
                if (!ic2_findexec('convert', $this->config['General']['magick'])) {
                    $this->error("Command 'convert' is not found");
                    $result = false;
                } else {
                    $this->info('Image Driver: OK');
                }
                break;
            case 'gd':
            case 'imagick':
            case 'imlib2':
                if (!extension_loaded($driver)) {
                    $this->error("Extension {$driver} is not loaded");
                    $result = false;
                } else {
                    $this->info('Image Driver: OK');
                }
                break;
            default:
                $this->error('Unknow image driver.');
                $result = false;
        }

        return $result;
    }

    private function comment($message)
    {
        $this->output->writeln("<comment>{$message}</comment>");
    }

    // {{{ post connect methods

    private function error($message)
    {
        if ($this->dryRun) {
            $this->output->writeln("<error>{$message}</error>");
        } else {
            throw new \Exception($message);
        }
    }

    private function info($message)
    {
        $this->output->writeln("<info>{$message}</info>");
    }

    /**
     * @return array
     */
    private function connect()
    {
        $phptype = null;

        $dsn = $this->config['General']['dsn'];

        if (preg_match('/^(\w+)(?:\((\w+)\))?:/', $dsn, $matches)) {
            $phptype = strtolower($matches[1]);
        }

        if (!in_array($phptype, array('mysql', 'pgsql', 'sqlite'))) {
            $this->error('Supports only MySQL, PostgreSQL and SQLite.');
            return null;
        }

        if (!extension_loaded('pdo_' . $phptype)) {
            $this->error("Extension '{$phptype}' is not loaded.");
            return null;
        }

        $this->db = new ImageCache2_DataObject_Common();

        return $this->postConnect($phptype);
    }

    private function postConnect($phptype)
    {
        $result = null;

        switch ($phptype) {
            case 'mysql':
                $result = $this->postConnectMysql();
                break;
            case 'pgsql':
                $result = $this->postConnectPgsql();
                break;
            case 'sqlite':
                $result = $this->postConnectSqlite();
                break;
        }

        return $result;
    }

    // }}}
    // {{{ methods to create table

    private function postConnectMysql()
    {
        $serialPrimaryKey = 'INTEGER PRIMARY KEY AUTO_INCREMENT';
        $tableExtraDefs = ' TYPE=MyISAM';

        $db = $this->db->PDO();
        $result = $db->query("SHOW VARIABLES LIKE 'version'")->fetch();
        if (is_array($result)) {
            $version = $result[1];
            if (version_compare($version, '4.1.2', 'ge')) {
                $tableExtraDefs = ' ENGINE=MyISAM DEFAULT CHARACTER SET utf8';
            }
        }

        $this->findTableStatement = "SHOW TABLES LIKE ?";
        $this->findIndexFormat = "SHOW INDEX FROM %s WHERE Key_name LIKE ?";

        return array($serialPrimaryKey, $tableExtraDefs);
    }

    private function postConnectPgsql()
    {
        $serialPrimaryKey = 'SERIAL PRIMARY KEY';
        $tableExtraDefs = '';

        $this->findTableStatement = "SELECT relname FROM pg_class WHERE relkind = 'r' AND relname = ?";
        $this->findIndexFormat = "SELECT relname FROM pg_class WHERE relkind = 'i' AND relname = ?";

        return array($serialPrimaryKey, $tableExtraDefs);
    }

    private function postConnectSqlite()
    {
        $serialPrimaryKey = 'INTEGER PRIMARY KEY';
        $tableExtraDefs = '';

        $this->findTableStatement = "SELECT name FROM sqlite_master WHERE type = 'table' AND name= ?";
        $this->findIndexFormat = "SELECT name FROM sqlite_master WHERE type = 'index' AND name= ?";

        return array($serialPrimaryKey, $tableExtraDefs);
    }

    private function createTables()
    {
        $imagesTable = $this->config['General']['table'];
        $errorLogTable = $this->config['General']['error_table'];
        $blackListTable = $this->config['General']['blacklist_table'];

        if ($this->findTable($imagesTable)) {
            $this->info("Table '{$imagesTable}' already exists");
        } else {
            $this->createImagesTable($imagesTable);
        }

        if ($this->findTable($errorLogTable)) {
            $this->info("Table '{$errorLogTable}' already exists");
        } else {
            $this->createErrorLogTable($errorLogTable);
        }

        if ($this->findTable($blackListTable)) {
            $this->info("Table '{$blackListTable}' already exists");
        } else {
            $this->createBlackListTable($blackListTable);
        }
    }

    private function findTable($tableName)
    {
        $db = $this->db->PDO();
        try {
            $stmt = $db->prepare($this->findTableStatement);
            $stmt->execute([$tableName]);
            return count($stmt->fetchAll()) > 0;
        } catch (PDOException $e) {
            $this->error($e->getMessage());
            return false;
        }
    }

    private function createImagesTable($tableName)
    {
        $quotedTableName = $this->db->quoteIdentifier($tableName);
        $sql = <<<SQL
CREATE TABLE {$quotedTableName} (
    "id"     {$this->serialPrimaryKey},
    "uri"    VARCHAR (255),
    "host"   VARCHAR (255),
    "name"   VARCHAR (255),
    "size"   INTEGER NOT NULL,
    "md5"    CHAR (32) NOT NULL,
    "width"  SMALLINT NOT NULL,
    "height" SMALLINT NOT NULL,
    "mime"   VARCHAR (50) NOT NULL,
    "time"   INTEGER NOT NULL,
    "rank"   SMALLINT NOT NULL DEFAULT 0,
    "memo"   TEXT
){$this->tableExtraDefs};
SQL;
        if ($this->db->db_class === 'mysql') { // MySQL 8.0.2‚©‚çRANK‚Í—\–ñŒê https://dev.mysql.com/doc/refman/8.0/en/keywords.html
            $sql = str_replace('"', '`', $sql);
        }
        return $this->doCreateTable($tableName, $sql);
    }

    // }}}
    // {{{ methods to create index

    private function doCreateTable($tableName, $sql)
    {
        if ($this->dryRun) {
            $this->comment($sql);
            return true;
        }

        try {
            $this->db->PDO()->query($sql);
        } catch (PDOException $e) {
            $this->error($e->getMessage());
            return false;
        }

        $this->info("Table '{$tableName}' created");
        return true;
    }

    private function createErrorLogTable($tableName)
    {
        $quotedTableName = $this->db->quoteIdentifier($tableName);
        $sql = <<<SQL
CREATE TABLE {$quotedTableName} (
    uri     VARCHAR (255),
    errcode VARCHAR(64) NOT NULL,
    errmsg  TEXT,
    occured INTEGER NOT NULL
){$this->tableExtraDefs};
SQL;
        return $this->doCreateTable($tableName, $sql);
    }

    private function createBlackListTable($tableName)
    {
        $quotedTableName = $this->db->quoteIdentifier($tableName);
        $sql = <<<SQL
CREATE TABLE {$quotedTableName} (
    id     {$this->serialPrimaryKey},
    uri    VARCHAR (255),
    size   INTEGER NOT NULL,
    md5    CHAR (32) NOT NULL,
    type   SMALLINT NOT NULL DEFAULT 0
){$this->tableExtraDefs};
SQL;
        return $this->doCreateTable($tableName, $sql);
    }

    private function createIndexes()
    {
        $imagesTable = $this->config['General']['table'];
        $errorLogTable = $this->config['General']['error_table'];
        $blackListTable = $this->config['General']['blacklist_table'];

        $indexes = array(
            $imagesTable => array(
                '_uri' => array('uri'),
                '_time' => array('time'),
                '_unique' => array('size', 'md5', 'mime'),
            ),
            $errorLogTable => array(
                '_uri' => array('uri'),
            ),
            $blackListTable => array(
                '_uri' => array('uri'),
                '_unique' => array('size', 'md5'),
            ),
        );

        foreach ($indexes as $tableName => $indexList) {
            foreach ($indexList as $indexNameSuffix => $fieldNames) {
                $indexName = 'idx_' . $tableName . $indexNameSuffix;
                if ($this->findIndex($indexName, $tableName)) {
                    $this->info("Index '{$indexName}' already exists");
                } else {
                    $this->doCreateIndex($indexName, $tableName, $fieldNames);
                }
            }
        }

        if ($this->db->db_class === 'pgsql') {
            $pgTrgm = $this->pgTrgm;
            if ($pgTrgm === self::PG_TRGM_GIST ||
                $pgTrgm === self::PG_TRGM_GIN) {
                $indexName = 'idx_memo_tgrm';
                if ($this->findIndex($indexName, $imagesTable)) {
                    $this->info("Index '{$indexName}' already exists");
                } else {
                    $this->doCreatePgTrgmIndex($pgTrgm, $indexName,
                        $imagesTable, 'memo');
                }
            }
        }
    }

    // }}}
    // {{{ console output methods

    private function findIndex($indexName, $tableName)
    {
        $db = $this->db->PDO();
        $sql = sprintf($this->findIndexFormat, $this->db->quoteIdentifier($tableName));
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([$indexName]);
            return count($stmt->fetchAll()) > 0;
        } catch (PDOException $e) {
            $this->error($e->getMessage());
            return false;
        }
    }

    private function doCreateIndex($indexName, $tableName, array $fieldNames)
    {
        $db = $this->db;
        $callback = array($db, 'quoteIdentifier');
        $sql = sprintf('CREATE INDEX %s ON %s (%s);',
            $db->quoteIdentifier($indexName),
            $db->quoteIdentifier($tableName),
            implode(', ', array_map($callback, $fieldNames)));

        if ($this->dryRun) {
            $this->comment($sql);
            return true;
        }

        try {
            $this->db->PDO()->query($sql);
        } catch (PDOException $e) {
            $this->error($e->getMessage());
            return false;
        }

        $this->info("Index '{$indexName}' created");

        return true;
    }

    private function doCreatePgTrgmIndex($indexType, $indexName, $tableName, $fieldName)
    {
        $db = $this->db;
        $sql = sprintf('CREATE INDEX %2$s ON %3$s USING %1$s (%4$s %1$s_trgm_ops);',
            $indexType,
            $db->quoteIdentifier($indexName),
            $db->quoteIdentifier($tableName),
            $db->quoteIdentifier($fieldName));

        if ($this->dryRun) {
            $this->comment($sql);
            return true;
        }

        try {
            $this->db->PDO()->query($sql);
        } catch (PDOException $e) {
            $this->error($e->getMessage());
            return false;
        }

        $this->info("{$indexType} Index '{$indexName}' created");

        return true;
    }

    // }}}
}
