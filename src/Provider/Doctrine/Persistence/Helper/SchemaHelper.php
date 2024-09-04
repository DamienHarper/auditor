<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Helper;

abstract class SchemaHelper
{
    /**
     * Return columns of audit tables.
     *
     * @return array{id: array{type: string, options: array{autoincrement: true, unsigned: true}}, type: array{type: string, options: array{notnull: true, length: int}}, object_id: array{type: string, options: array{notnull: true}}, discriminator: array{type: string, options: array{default: null, notnull: false}}, transaction_hash: array{type: string, options: array{notnull: false, length: int}}, diffs: array{type: string, options: array{default: null, notnull: false}}, blame_id: array{type: string, options: array{default: null, notnull: false}}, blame_user: array{type: string, options: array{default: null, notnull: false, length: int}}, blame_user_fqdn: array{type: string, options: array{default: null, notnull: false, length: int}}, blame_user_firewall: array{type: string, options: array{default: null, notnull: false, length: int}}, ip: array{type: string, options: array{default: null, notnull: false, length: int}}, created_at: array{type: string, options: array{notnull: true}}}
     */
    public static function getAuditTableColumns(): array
    {
        return [
            'id' => [
                'type' => DoctrineHelper::getDoctrineType('INTEGER'),
                'options' => [
                    'autoincrement' => true,
                    'unsigned' => true,
                ],
            ],
            'type' => [
                'type' => DoctrineHelper::getDoctrineType('STRING'),
                'options' => [
                    'notnull' => true,
                    'length' => 10,
                ],
            ],
            'object_id' => [
                'type' => DoctrineHelper::getDoctrineType('STRING'),
                'options' => [
                    'notnull' => true,
                    'length' => 255,
                ],
            ],
            'discriminator' => [
                'type' => DoctrineHelper::getDoctrineType('STRING'),
                'options' => [
                    'default' => null,
                    'notnull' => false,
                    'length' => 255,
                ],
            ],
            'transaction_hash' => [
                'type' => DoctrineHelper::getDoctrineType('STRING'),
                'options' => [
                    'notnull' => false,
                    'length' => 40,
                ],
            ],
            'diffs' => [
                'type' => DoctrineHelper::getDoctrineType('JSON'),
                'options' => [
                    'default' => null,
                    'notnull' => false,
                ],
            ],
            'blame_id' => [
                'type' => DoctrineHelper::getDoctrineType('STRING'),
                'options' => [
                    'default' => null,
                    'notnull' => false,
                    'length' => 255,
                ],
            ],
            'blame_user' => [
                'type' => DoctrineHelper::getDoctrineType('STRING'),
                'options' => [
                    'default' => null,
                    'notnull' => false,
                    'length' => 255,
                ],
            ],
            'blame_user_fqdn' => [
                'type' => DoctrineHelper::getDoctrineType('STRING'),
                'options' => [
                    'default' => null,
                    'notnull' => false,
                    'length' => 255,
                ],
            ],
            'blame_user_firewall' => [
                'type' => DoctrineHelper::getDoctrineType('STRING'),
                'options' => [
                    'default' => null,
                    'notnull' => false,
                    'length' => 100,
                ],
            ],
            'ip' => [
                'type' => DoctrineHelper::getDoctrineType('STRING'),
                'options' => [
                    'default' => null,
                    'notnull' => false,
                    'length' => 45,
                ],
            ],
            'created_at' => [
                'type' => DoctrineHelper::getDoctrineType('DATETIME_IMMUTABLE'),
                'options' => [
                    'notnull' => true,
                ],
            ],
        ];
    }

    /**
     * Return indices of an audit table.
     *
     * @return array{id: array{type: string}, type: array{type: string, name: string}, object_id: array{type: string, name: string}, discriminator: array{type: string, name: string}, transaction_hash: array{type: string, name: string}, blame_id: array{type: string, name: string}, created_at: array{type: string, name: string}}
     */
    public static function getAuditTableIndices(string $tablename): array
    {
        return [
            'id' => [
                'type' => 'primary',
            ],
            'type' => [
                'type' => 'index',
                'name' => 'type_'.md5($tablename).'_idx',
            ],
            'object_id' => [
                'type' => 'index',
                'name' => 'object_id_'.md5($tablename).'_idx',
            ],
            'discriminator' => [
                'type' => 'index',
                'name' => 'discriminator_'.md5($tablename).'_idx',
            ],
            'transaction_hash' => [
                'type' => 'index',
                'name' => 'transaction_hash_'.md5($tablename).'_idx',
            ],
            'blame_id' => [
                'type' => 'index',
                'name' => 'blame_id_'.md5($tablename).'_idx',
            ],
            'created_at' => [
                'type' => 'index',
                'name' => 'created_at_'.md5($tablename).'_idx',
            ],
        ];
    }

    public static function isValidPayload(array $payload): bool
    {
        foreach (array_keys(self::getAuditTableColumns()) as $columnName) {
            if ('id' !== $columnName && !\array_key_exists($columnName, $payload)) {
                return false;
            }
        }

        return true;
    }
}
