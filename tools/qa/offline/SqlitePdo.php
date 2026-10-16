<?php
/** Ephemeral QA-only adapter. Not part of production runtime. Uses the system SQLite library through FFI. */
final class OfflineSqlitePdo extends PDO
{
    public FFI $lib;
    public mixed $db;
    private array $attributes = [PDO::ATTR_DRIVER_NAME => 'sqlite', PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ];
    public function __construct(string $filename = ':memory:')
    {
        $this->lib = FFI::cdef('typedef struct sqlite3 sqlite3; typedef struct sqlite3_stmt sqlite3_stmt;
            int sqlite3_open(const char*,sqlite3**); int sqlite3_close(sqlite3*); const char* sqlite3_errmsg(sqlite3*);
            int sqlite3_prepare_v2(sqlite3*,const char*,int,sqlite3_stmt**,const char**);
            int sqlite3_step(sqlite3_stmt*); int sqlite3_finalize(sqlite3_stmt*); int sqlite3_reset(sqlite3_stmt*);
            int sqlite3_clear_bindings(sqlite3_stmt*); int sqlite3_bind_parameter_index(sqlite3_stmt*,const char*);
            int sqlite3_bind_null(sqlite3_stmt*,int); int sqlite3_bind_int64(sqlite3_stmt*,int,long long);
            int sqlite3_bind_double(sqlite3_stmt*,int,double); int sqlite3_bind_text(sqlite3_stmt*,int,const char*,int,void*);
            int sqlite3_column_count(sqlite3_stmt*); const char* sqlite3_column_name(sqlite3_stmt*,int);
            int sqlite3_column_type(sqlite3_stmt*,int); long long sqlite3_column_int64(sqlite3_stmt*,int);
            double sqlite3_column_double(sqlite3_stmt*,int); const unsigned char* sqlite3_column_text(sqlite3_stmt*,int);
            int sqlite3_column_bytes(sqlite3_stmt*,int); int sqlite3_changes(sqlite3*); long long sqlite3_last_insert_rowid(sqlite3*);
            int sqlite3_get_autocommit(sqlite3*); int sqlite3_exec(sqlite3*,const char*,void*,void*,char**);
            const char* sqlite3_libversion(void);', 'libsqlite3.so.0');
        $ptr = $this->lib->new('sqlite3 *');
        $status = $this->lib->sqlite3_open($filename, FFI::addr($ptr));
        $this->db = $ptr;
        $this->check($status);
        $this->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    }
    public function check(int $code): void
    {
        if ($code === 0 || $code === 100 || $code === 101) return;
        $message = $this->lib->sqlite3_errmsg($this->db);
        $e = new PDOException('SQLSTATE['.($code===19?'23000':'HY000').']: '.$message, $code);
        $e->errorInfo = [$code === 19 ? '23000' : 'HY000', $code, $message];
        throw $e;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new OfflineSqliteStatement($this, $query); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    { $s = $this->prepare($query); if ($fetchMode !== null) $s->setFetchMode($fetchMode, ...$fetchModeArgs); $s->execute(); return $s; }
    public function exec(string $statement): int|false
    { $this->check($this->lib->sqlite3_exec($this->db, $statement, null, null, null)); return $this->lib->sqlite3_changes($this->db); }
    public function beginTransaction(): bool { $this->exec('BEGIN'); return true; }
    public function commit(): bool { $this->exec('COMMIT'); return true; }
    public function rollBack(): bool { $this->exec('ROLLBACK'); return true; }
    public function inTransaction(): bool { return !$this->lib->sqlite3_get_autocommit($this->db); }
    public function lastInsertId(?string $name = null): string|false { return (string) $this->lib->sqlite3_last_insert_rowid($this->db); }
    public function getAttribute(int $attribute): mixed { return $attribute === PDO::ATTR_SERVER_VERSION ? $this->lib->sqlite3_libversion() : ($this->attributes[$attribute] ?? null); }
    public function setAttribute(int $attribute, mixed $value): bool { $this->attributes[$attribute] = $value; return true; }
    public function quote(string $string, int $type = PDO::PARAM_STR): string|false { return "'".str_replace("'", "''", $string)."'"; }
    public function errorCode(): ?string { return '00000'; }
    public function errorInfo(): array { return ['00000', null, null]; }
}
final class OfflineSqliteStatement extends PDOStatement
{
    private mixed $stmt;
    private array $bindings = [];
    private array $rows = [];
    private int $index = 0;
    private int $mode;
    private int $affected = 0;
    public function __construct(private OfflineSqlitePdo $pdo, string $query)
    {
        $this->queryString = $query;
        $s = $pdo->lib->new('sqlite3_stmt *');
        $pdo->check($pdo->lib->sqlite3_prepare_v2($pdo->db, $query, -1, FFI::addr($s), null));
        $this->stmt = $s;
        $this->mode = $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE) ?? PDO::FETCH_OBJ;
    }
    public function __destruct() { if ($this->stmt !== null) $this->pdo->lib->sqlite3_finalize($this->stmt); }
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool { $this->bindings[$param] = [$value, $type]; return true; }
    public function bindParam(string|int $param, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool { $this->bindings[$param] = [&$var, $type]; return true; }
    public function execute(?array $params = null): bool
    {
        $f = $this->pdo->lib;
        $f->sqlite3_reset($this->stmt); $f->sqlite3_clear_bindings($this->stmt);
        if ($params !== null) foreach ($params as $key => $value) $this->bindValue(is_int($key) ? $key + 1 : $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        foreach ($this->bindings as $key => [$value, $type]) {
            $i = is_int($key) ? $key : $f->sqlite3_bind_parameter_index($this->stmt, str_starts_with($key, ':') ? $key : ':'.$key);
            $code = match (true) {
                $value === null => $f->sqlite3_bind_null($this->stmt, $i),
                $type === PDO::PARAM_INT || $type === PDO::PARAM_BOOL => $f->sqlite3_bind_int64($this->stmt, $i, (int) $value),
                is_float($value) => $f->sqlite3_bind_double($this->stmt, $i, $value),
                default => $f->sqlite3_bind_text($this->stmt, $i, (string) $value, strlen((string) $value), FFI::cast('void *', -1)),
            };
            $this->pdo->check($code);
        }
        $this->rows = []; $this->index = 0;
        while (($rc = $f->sqlite3_step($this->stmt)) === 100) {
            $row = [];
            for ($i = 0; $i < $f->sqlite3_column_count($this->stmt); $i++) {
                $name = $f->sqlite3_column_name($this->stmt, $i);
                $type = $f->sqlite3_column_type($this->stmt, $i);
                $row[$name] = match ($type) {
                    1 => $f->sqlite3_column_int64($this->stmt, $i),
                    2 => $f->sqlite3_column_double($this->stmt, $i),
                    5 => null,
                    default => FFI::string($f->sqlite3_column_text($this->stmt, $i), $f->sqlite3_column_bytes($this->stmt, $i)),
                };
            }
            $this->rows[] = $row;
        }
        $this->pdo->check($rc);
        $this->affected = $f->sqlite3_changes($this->pdo->db);
        return true;
    }
    public function setFetchMode(int $mode, mixed ...$args): bool { $this->mode = $mode; return true; }
    private function convert(array $row, int $mode): mixed
    {
        return match ($mode ?: $this->mode) { PDO::FETCH_OBJ => (object) $row, PDO::FETCH_NUM => array_values($row), PDO::FETCH_COLUMN => reset($row), PDO::FETCH_BOTH => array_merge($row, array_values($row)), default => $row };
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return isset($this->rows[$this->index]) ? $this->convert($this->rows[$this->index++], $mode) : false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    { $result = []; while ($this->index < count($this->rows)) { $row = $this->rows[$this->index++]; $result[] = ($mode === PDO::FETCH_COLUMN && isset($args[0])) ? array_values($row)[$args[0]] : $this->convert($row, $mode); } return $result; }
    public function fetchColumn(int $column = 0): mixed { return isset($this->rows[$this->index]) ? array_values($this->rows[$this->index++])[$column] : false; }
    public function rowCount(): int { return $this->affected; }
    public function columnCount(): int { return count($this->rows[0] ?? []); }
    public function closeCursor(): bool { $this->rows = []; $this->index = 0; return true; }
    public function errorCode(): ?string { return '00000'; }
    public function errorInfo(): array { return ['00000', null, null]; }
}
