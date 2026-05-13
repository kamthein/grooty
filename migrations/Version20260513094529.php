<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513094529 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create join_request table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE join_request (
            id INT AUTO_INCREMENT NOT NULL,
            child_id INT NOT NULL,
            requester_id INT NOT NULL,
            status VARCHAR(20) NOT NULL,
            token VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_E932E4FFDD62C21B (child_id),
            INDEX IDX_E932E4FFED442CF4 (requester_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE join_request ADD CONSTRAINT FK_E932E4FFDD62C21B FOREIGN KEY (child_id) REFERENCES child (id)');
        $this->addSql('ALTER TABLE join_request ADD CONSTRAINT FK_E932E4FFED442CF4 FOREIGN KEY (requester_id) REFERENCES guardian (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE join_request DROP FOREIGN KEY FK_E932E4FFDD62C21B');
        $this->addSql('ALTER TABLE join_request DROP FOREIGN KEY FK_E932E4FFED442CF4');
        $this->addSql('DROP TABLE join_request');
    }
}