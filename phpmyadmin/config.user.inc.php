<?php
/**
 * Full phpMyAdmin server config. Overrides whatever the image may have set.
 * Password is read from MYSQL_ROOT_PASSWORD (passed explicitly in docker-compose).
 */
$_pw = (string) (getenv('MYSQL_ROOT_PASSWORD') ?: '');

$cfg['ServerDefault'] = 1;

$cfg['Servers'][1] = [
    'verbose'   => 'Primary',
    'host'      => 'db-primary',
    'port'      => '3306',
    'auth_type' => 'config',
    'user'      => 'root',
    'password'  => $_pw,
];

$cfg['Servers'][2] = [
    'verbose'      => 'Replica',
    'host'         => 'db-replica',
    'port'         => '3306',
    'auth_type'    => 'config',
    'user'         => 'root',
    'password'     => $_pw,
    // All phpMyAdmin metadata writes go to primary so the read-only replica is not touched
    'controlhost'  => 'db-primary',
    'controlport'  => '3306',
    'controluser'  => 'root',
    'controlpass'  => $_pw,
];
