<?php

declare(strict_types=1);

if (! defined('SQLITE3_ASSOC')) {
    define('SQLITE3_ASSOC', 1);
}
if (! defined('SQLITE3_NUM')) {
    define('SQLITE3_NUM', 2);
}
if (! defined('SQLITE3_BOTH')) {
    define('SQLITE3_BOTH', 3);
}
if (! defined('SQLITE3_INTEGER')) {
    define('SQLITE3_INTEGER', 1);
}
if (! defined('SQLITE3_FLOAT')) {
    define('SQLITE3_FLOAT', 2);
}
if (! defined('SQLITE3_TEXT')) {
    define('SQLITE3_TEXT', 3);
}
if (! defined('SQLITE3_BLOB')) {
    define('SQLITE3_BLOB', 4);
}
if (! defined('SQLITE3_NULL')) {
    define('SQLITE3_NULL', 5);
}
if (! defined('SQLITE3_OPEN_READWRITE')) {
    define('SQLITE3_OPEN_READWRITE', 2);
}
if (! defined('SQLITE3_OPEN_CREATE')) {
    define('SQLITE3_OPEN_CREATE', 4);
}

if (! class_exists('SQLite3')) {
    class SQLite3
    {
        private \PDO $pdo;
        private int $lastChanges = 0;
        private int $lastErrorCode = 0;
        private string $lastErrorMessage = 'not an error';

        public function __construct(string $filename)
        {
            $dsn       = 'sqlite:' . $filename;
            $this->pdo = new \PDO($dsn);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        public function enableExceptions(bool $enable): bool
        {
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, $enable ? \PDO::ERRMODE_EXCEPTION : \PDO::ERRMODE_SILENT);

            return true;
        }

        public function busyTimeout(int $milliseconds): bool
        {
            $this->pdo->setAttribute(\PDO::ATTR_TIMEOUT, max(1, (int) ceil($milliseconds / 1000)));

            return true;
        }

        public function exec(string $query): bool
        {
            try {
                $affected          = $this->pdo->exec($query);
                $this->lastChanges = $affected === false ? 0 : (int) $affected;
                $this->lastErrorCode = 0;
                $this->lastErrorMessage = 'not an error';

                return $affected !== false;
            } catch (\Throwable $e) {
                $this->lastErrorCode    = (int) $e->getCode();
                $this->lastErrorMessage = $e->getMessage();

                return false;
            }
        }

        public function query(string $query): SQLite3Result|false
        {
            try {
                $stmt = $this->pdo->query($query);
                if ($stmt === false) {
                    return false;
                }

                $this->lastErrorCode    = 0;
                $this->lastErrorMessage = 'not an error';

                return new SQLite3Result($stmt);
            } catch (\Throwable $e) {
                $this->lastErrorCode    = (int) $e->getCode();
                $this->lastErrorMessage = $e->getMessage();

                return false;
            }
        }

        public function changes(): int
        {
            return $this->lastChanges;
        }

        public function escapeString(string $value): string
        {
            $quoted = $this->pdo->quote($value);
            if ($quoted === false) {
                return str_replace("'", "''", $value);
            }

            return substr($quoted, 1, -1);
        }

        public function lastErrorCode(): int
        {
            return $this->lastErrorCode;
        }

        public function lastErrorMsg(): string
        {
            return $this->lastErrorMessage;
        }

        public function lastInsertRowID(): int
        {
            return (int) $this->pdo->lastInsertId();
        }

        public function close(): bool
        {
            unset($this->pdo);

            return true;
        }

        /**
         * @return array{versionString: string, versionNumber: int}
         */
        public static function version(): array
        {
            return [
                'versionString' => '3.40.0',
                'versionNumber' => 3040000,
            ];
        }
    }

    class SQLite3Result
    {
        private \PDOStatement $statement;
        /** @var array<int, array<string, mixed>> */
        private array $rows;
        private int $index = 0;

        public function __construct(\PDOStatement $statement)
        {
            $this->statement = $statement;
            $this->rows      = $statement->fetchAll(\PDO::FETCH_ASSOC);
        }

        public function numColumns(): int
        {
            return $this->statement->columnCount();
        }

        public function columnName(int $index): string
        {
            $meta = $this->statement->getColumnMeta($index);

            return (string) ($meta['name'] ?? '');
        }

        public function columnType(int $index): int
        {
            $meta = $this->statement->getColumnMeta($index);
            $type = strtolower((string) ($meta['native_type'] ?? ''));

            return match ($type) {
                'integer', 'int' => SQLITE3_INTEGER,
                'double', 'float', 'real' => SQLITE3_FLOAT,
                'blob' => SQLITE3_BLOB,
                'null' => SQLITE3_NULL,
                default => SQLITE3_TEXT,
            };
        }

        public function fetchArray(int $mode = SQLITE3_BOTH): array|false
        {
            if (! isset($this->rows[$this->index])) {
                return false;
            }

            $assoc = $this->rows[$this->index++];

            if ($mode === SQLITE3_ASSOC) {
                return $assoc;
            }

            $num = array_values($assoc);

            if ($mode === SQLITE3_NUM) {
                return $num;
            }

            $both = $assoc;
            foreach ($num as $i => $value) {
                $both[$i] = $value;
            }

            return $both;
        }

        public function reset(): bool
        {
            $this->index = 0;

            return true;
        }

        public function finalize(): bool
        {
            $this->rows = [];

            return true;
        }
    }
}

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';
