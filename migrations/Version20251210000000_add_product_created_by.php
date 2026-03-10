<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251210000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds createdBy field to Product entity and updates activity_log table structure';
    }

    public function up(Schema $schema): void
    {
        // Add createdBy field to product table
        $this->addSql('ALTER TABLE product ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_D34A04ADB03A8386 ON product (created_by_id)');

        // Update activity_log table structure
        // Note: Check if columns exist first to avoid errors
        $connection = $this->connection;
        $schemaManager = $connection->createSchemaManager();
        $table = $schemaManager->introspectTable('activity_log');
        
        if (!$table->hasColumn('username')) {
            $this->addSql('ALTER TABLE activity_log ADD username VARCHAR(50) DEFAULT NULL');
        }
        if (!$table->hasColumn('user_role')) {
            $this->addSql('ALTER TABLE activity_log ADD user_role VARCHAR(50) DEFAULT NULL');
        }
        
        // The existing migration uses 'details' (LONGTEXT), but entity uses 'targetData' (JSON)
        // If you need to migrate, run manually: ALTER TABLE activity_log CHANGE details target_data JSON DEFAULT NULL;
        // Or if target_data doesn't exist: ALTER TABLE activity_log ADD COLUMN target_data JSON DEFAULT NULL;
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADB03A8386');
        $this->addSql('DROP INDEX IDX_D34A04ADB03A8386 ON product');
        $this->addSql('ALTER TABLE product DROP created_by_id');
        
        $this->addSql('ALTER TABLE activity_log DROP COLUMN IF EXISTS username');
        $this->addSql('ALTER TABLE activity_log DROP COLUMN IF EXISTS user_role');
    }
}

