<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251208041826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user table for authentication';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('user');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('email', 'string', ['length' => 180]);
        $table->addColumn('first_name', 'string', ['length' => 100]);
        $table->addColumn('last_name', 'string', ['length' => 100]);
        $table->addColumn('roles', 'json');
        $table->addColumn('password', 'string');
        $table->addColumn('is_active', 'boolean', ['default' => true]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('last_login', 'datetime_immutable', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['email'], 'UNIQ_8D93D649E7927C74');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('user');
    }
}
