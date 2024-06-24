<?php
declare(strict_types=1);

namespace Pool_CLI\Commands\CreateDAOCommand;

use Pool_CLI\DBConnector\DBConnector;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateDAOCommand extends Command
{
    private PDO $pdo;

    protected function configure(): void
    {
        $this->setName('create_dao')
            ->setDescription('creates new DAO')
            ->setHelp('lookup on pool-documentation/pool-cli how to create new GUI');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('<info>Generate new DAO</info>');

        $dsn = $io->choice('Select DSN', array_keys(DATABASE_CONNECTIONS));
        $this->connect($dsn);
        $databases = $this->getDatabases($this->pdo);
        $database = $io->choice('Select Database', $databases);
        $tables = $this->getTables($this->pdo, $database);
        $table = $io->choice('Select Table', $tables);
        $columns = $this->getColumnsMeta($this->pdo, $table);
        $className = self::stringToCamelcase($table, '_');

        if (is_file(DAO_DIR . "/$className.php")) {
            $io->error("DAO already exists");
            return Command::FAILURE;
        }
        $dao = file_put_contents(
            DAO_DIR . "/$className.php",
            $this->generateDAO($columns, $table, $database, $className)
        );
        if (!$dao) {
            $io->error("dao failed to create");
            return Command::FAILURE;
        }

        $io->success("DAO generated successfully");
        return Command::SUCCESS;
    }

    private function connect($dsn): void
    {
        $dbCredentials = DATABASE_CONNECTIONS[$dsn];
        $dbConnector = new DBConnector(
            $dbCredentials['host'],
            $dbCredentials['port'],
            $dbCredentials['user'],
            $dbCredentials['password'],
        );
        $this->pdo = $dbConnector->connect();
    }

    private function getDatabases(PDO $pdo): array
    {
        return $pdo->query('SHOW DATABASES')->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function getTables(PDO $pdo, $database): array
    {
        $pdo->query('USE ' . $database);
        return $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function getColumnsMeta(PDO $pdo, $table): array
    {
        return $pdo->query('SHOW FULL COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function generateDAO(array $columns, string $table, string $database, string $className): string
    {
        $pk = ""; // array? more than one primary key?
        $fk = ""; // array of foreign keys
        $columnsComment = "";
        $columnsArray = "";

        foreach ($columns as $column) {
            $primaryKey = $column['Key'] === 'PRI' ? 'primaryKey' : '';
            $extra = $column['Extra'] !== '' ? "{$column['Extra']}" : '';
            $notNull = $column['Null'] === 'NO' ? 'NOT NULL' : '';
            $columnsComment .= "\t * {$column['Field']} ({$column['Type']}) $notNull $extra $primaryKey\n";

            if ($column['Key'] === 'PRI') {
                $pk = "\tprotected array \$pk = [\n";
                $pk .= "\t\t'{$column['Field']}'\n";
                $pk .= "\t];\n";
            }

            $columnsArray .= "\t\t'{$column['Field']}',\n";
        }

        $fileData = "<?php\n";
        $fileData .= "declare(strict_types=1);\n\n";
        /**
         * todo: rethink: Namespace for DAO = Const, because all daos from all projects are in one place.. select
         * project for namespace?
         */
        $fileData .= "namespace " . DAO_NAMESPACE . ";\n\n";
        // todo: --option from which Parent-DAO-Class to extend
        $fileData .= "use pool\classes\Database\DAO\MySQL_DAO;\n\n";
        $fileData .= "class $className extends MySQL_DAO\n";
        $fileData .= "{\n";
        $fileData .= "\tprotected static ?string \$databaseName = '$database';\n";
        $fileData .= "\tprotected static ?string \$tableName = '$table';\n";
        $fileData .= $pk;
        $fileData .= "\t\n";
        $fileData .= "\t/**\n";
        $fileData .= "\t * columns of table $table\n";
        $fileData .= "\t *\n";
        $fileData .= $columnsComment;
        $fileData .= "\t */\n";
        $fileData .= "\tprotected array \$columns = [\n";
        $fileData .= $columnsArray;
        $fileData .= "\t];\n";
        $fileData .= "}\n";

        return $fileData;
    }

    public static function stringToCamelcase(string $string, string $separator): string
    {
        return ucfirst(str_replace($separator, '', ucwords($string, $separator)));
    }
}
