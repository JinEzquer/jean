<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251209040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates the activity_log table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT NOT NULL, 
            user_id INT DEFAULT NULL, 
            action VARCHAR(255) NOT NULL, 
            details LONGTEXT DEFAULT NULL, 
            ip_address VARCHAR(45) DEFAULT NULL, 
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            INDEX IDX_8050D73AA76ED395 (user_id), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_8050D73AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS activity_log');
    }
}
