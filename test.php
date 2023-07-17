#!/usr/bin/php
<?php

include __DIR__ . "/SimpleQueryBuilder.php";

function println($args)
{
    echo "> $args";
    echo PHP_EOL;
}

$tmpName = tempnam(sys_get_temp_dir(), 'rdb');
$pdo = new \PDO("sqlite:$tmpName");

$pdo->exec(<<<SQL
            CREATE TABLE temp (
                txt text default "default",
                num integer default 0,
                num2 integer default 10
            )
            SQL);
$builder = new SimpleQueryBuilder\SimpleQueryBuilder($pdo);

$affected = $builder->insert(['txt' => 'foo', 'num' => 1])->into('temp')->exec();
println('insert row');
var_dump($affected);
assert($affected === 1);

$rows = $builder->select()->from('temp')->exec();
println('select all');
var_dump($rows);

$rows = $builder->select(['txt'])->from('temp')->exec();
println('select some');
var_dump($rows);

$rows = $builder->select(['num' => 'number'])->from('temp')->exec();
println('select some as');
var_dump($rows);

$affected = $builder->insert(['txt' => 'bar'])->into('temp')->exec();
println('insert another row');
var_dump($affected);
assert($affected === 1);

$rows = $builder->select()->from('temp')->exec();
println('select all');
var_dump($rows);

$rows = $builder->select()->from('temp')->where(['txt' => 'bar'])->exec();
println('select where');
var_dump($rows);

$affected = $builder->update(['num' => 10000])->from('temp')->where(['txt' => 'bar'])->exec();
println('update where');
var_dump($affected);
assert($affected === 1);

$rows = $builder->select()->from('temp')->exec();
println('select all');
var_dump($rows);

$affected = $builder->delete()->from('temp')->where(['txt' => 'bar'])->exec();
println('delete where');
var_dump($affected);
assert($affected === 1);

$rows = $builder->select()->from('temp')->exec();
println('select all');
var_dump($rows);
