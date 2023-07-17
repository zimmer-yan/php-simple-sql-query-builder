<?php

declare(strict_types=1);

namespace SimpleQueryBuilder;

class SimpleQueryBuilder
{
    private const QUERY_SELECT = 'SELECT';
    private const QUERY_INSERT = 'INSERT';
    private const QUERY_UPDATE = 'UPDATE';
    private const QUERY_DELETE = 'DELETE';

    private ?string $queryType = null;
    private ?string $table = null;
    /** @var array<string|int, string|int> $fields */
    private ?array $fields = null;
    /** @var array<string, string|int> $filterParams */
    private ?array $filterParams = null;

    private const COMPARISON_OPERATORS = ['=', '<', '>', '=>', '<=', '<>'];
    private const DEFAULT_OPERATOR = self::COMPARISON_OPERATORS[0];


    public function __construct(
        private readonly \PDO $pdo
    ) {
    }

    private function clean(): void
    {
        $this->queryType = $this->table = $this->fields = $this->filterParams = null;
    }

    public function new(string $table): self
    {
        $this->clean();
        $this->setTable($table);
        return $this;
    }

    private function setTable(string $table): void
    {
        $this->table = $table;
    }

    /** @param array<string|int, string|int> $fields */
    private function setFields(?array $fields = null): void
    {
        $this->fields = $fields;
    }

    private function setQueryType(string $queryType): void
    {
        $this->queryType = $queryType;
    }

    /** @param array<string|int, string|int> $fields */
    public function insert(array $fields): self
    {
        $this->setFields($fields);
        $this->setQueryType(self::QUERY_INSERT);
        return $this;
    }

    /** @param array<string|int, string|int> $fields */
    public function update(array $fields): self
    {
        $this->setFields($fields);
        $this->setQueryType(self::QUERY_UPDATE);
        return $this;
    }

    /** @param array<string|int, string|int> $fields */
    public function select(?array $fields = null): self
    {
        $this->setFields($fields);
        $this->setQueryType(self::QUERY_SELECT);
        return $this;
    }

    public function delete(): self
    {
        $this->setQueryType(self::QUERY_DELETE);
        return $this;
    }

    public function from(string $table): self
    {
        $this->setTable($table);
        return $this;
    }

    public function into(string $table): self
    {
        $this->setTable($table);
        return $this;
    }

    /** @param array<string, string|int>|null $filterParams */
    public function where(?array $filterParams = null): self
    {
        $this->filterParams = $filterParams;
        return $this;
    }

    /** @param array<string, string|int> $filterParams */
    public function andWhere(array $filterParams): self
    {
        $this->filterParams = array_merge($this->filterParams, $filterParams);
        return $this;
    }

    public function getQuery(): string
    {
        return trim($this->buildQuery());
    }

    /** @return int|mixed[] */
    public function exec(?string $table = null): array|int
    {
        if (!is_null($table)) {
            $this->setTable($table);
        }

        $query = $this->buildQuery();
        if (!$query) {
            return 0;
        }
        if ($this->queryType === self::QUERY_INSERT && !$this->fields) {
            return 0;
        }

        $params = match ($this->queryType) {
            // select query has to bind params in where
            self::QUERY_SELECT, self::QUERY_DELETE => $this->filterParams ?? [],
            // insert query has to bind values fields
            self::QUERY_INSERT => $this->fields,
            // update has to build both set values and where params
            self::QUERY_UPDATE => ($this->fields ?? []) + ($this->filterParams ?? []),
            default => [],
        };

        return $this->execQuery($query, $params);
    }

    private function buildQuery(): string
    {
        return match ($this->queryType) {
            self::QUERY_SELECT => $this->buildSelect(),
            self::QUERY_INSERT => $this->buildInsert(),
            self::QUERY_UPDATE => $this->buildUpdate(),
            self::QUERY_DELETE => $this->buildDelete(),
            default => '',
        };
    }

    /**
     * @param array<string|int, string|int|bool> $toBind
     * @return int|mixed[]
     */
    private function execQuery(string $query, array $toBind): array|int
    {
        $stmt = $this->pdo->prepare($query);

        foreach ($toBind as $key => $value) {
            if (is_numeric($key)) {
                continue;
            }
            $key = $this->removeOperator($key);
            $type = is_numeric($value) || is_bool($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue(":$key", $value, $type);
        }
        $stmt->execute();

        if ($this->queryType === self::QUERY_SELECT) {
            $this->clean();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        $this->clean();
        return $stmt->rowCount();
    }

    private function removeOperator(string $filterKey): string
    {
        foreach (self::COMPARISON_OPERATORS as $operator) {
            if (str_ends_with($filterKey, $operator)) {
                return substr($filterKey, 0, strlen($operator));
            }
        }

        return $filterKey;
    }

    private function includesOperator(string $filterKey): bool
    {
        foreach (self::COMPARISON_OPERATORS as $operator) {
            if (str_ends_with($filterKey, $operator)) {
                return true;
            }
        }

        return false;
    }

    private function buildFilter(): string
    {
        if (is_null($this->filterParams)) {
            return '';
        }
        return 'WHERE ' . implode(
            ' AND ',
            array_map(
                fn(string|int $key, string|int $value): string => is_int($key)
                ? (string)$value
                : sprintf(
                    '%s%s:%s',
                    $key,
                    $this->includesOperator($key) ? '' : self::DEFAULT_OPERATOR,
                    $key
                ),
                array_keys($this->filterParams),
                array_values($this->filterParams)
            )
        );
    }

    private function buildInsert(): string
    {
        $columns = implode(',', array_keys($this->fields));
        $values = implode(',', array_map(fn(string $key): string => ":$key", array_keys($this->fields)));

        return "INSERT INTO $this->table ($columns) VALUES ($values)";
    }

    private function buildDelete(): string
    {
        $filter = $this->buildFilter();

        return "DELETE FROM $this->table $filter";
    }

    private function buildUpdate(): string
    {
        $columns = implode(
            ',',
            array_map(
                fn(string $key): string => "$key=:$key",
                array_keys($this->fields),
            )
        );
        $filter = $this->buildFilter();
        return "UPDATE $this->table SET $columns $filter";
    }

    private function buildSelect(): string
    {
        $columns = $this->buildSelectColumns();
        $filter = $this->buildFilter();

        return "SELECT $columns FROM $this->table $filter";
    }

    private function buildSelectColumns(): string
    {
        if (is_null($this->fields)) {
            return '*';
        }
        return implode(
            ', ',
            array_map(
                fn(string|int $k, string $v): string => is_string($k) ? "$k AS `$v`" : $v,
                array_keys($this->fields),
                array_values($this->fields)
            )
        );
    }
}
