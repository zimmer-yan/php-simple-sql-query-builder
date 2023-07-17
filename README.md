# php-simple-sql-query-builder

## Usage
Create by passing a `PDO` to it:
```php
$pdo = new PDO(/* ... */);
$builder = new SimpleQueryBuilder($pdo);
```

### Select
```php
// select *
$builder->select()->from('table_name');
// select columns
$builder->select(['column1', 'column2'])->from('table_name');
// select column as
$builder->select(['column1' => 'otherName'])->from('table_name');
```

### Insert
```php
// insert
$builder->insert(['column1' => 'value', 'column2' => 123])->into('table_name');
```

### Update
```php
// update
$builder->update(['column1' => 'value', 'column2' => 123])->from('table_name');
```

### Delete
```php
// delete needs no params
$builder->delete()->from('table_name');
```

### Add a where clause
```php
// where column
$builder->select()->where('column')->from('table_name');
// where column equals
$builder->select()->where('column' => 'value')->from('table_name');
// where with operator 
$builder->select()->where('column >' => 123)->from('table_name');
```

### Run the query
```php
// run
$builder->select()->from('table_name')->exec();
```

### Get the query instead of running it
```php
// get query string
$builder->select()->from('table_name')->getQuery();
```

### All ways to set the table name
```php
// new (query)
$builder->new('table_name')->select()->exec();
// from / into can both be used and are just flavor for readability
$builder->select(/*...*/)->from('table_name')->exec();
$builder->insert(/*...*/)->into('table_name')->exec();
// pass the table name to exec
$builder->select()->exec('table_name');
```

### `exec`s return values:
- on `select`: `array` of rows (assoc)
- on `insert`/`update`/`delete`: `int` rows affected
