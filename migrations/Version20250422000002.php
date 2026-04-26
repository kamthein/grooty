<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250422000002 extends AbstractMigration
{
    public function getDescription(): string { return 'Guardian — last_name nullable'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guardian MODIFY last_name VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE guardian SET last_name = '' WHERE last_name IS NULL");
        $this->addSql('ALTER TABLE guardian MODIFY last_name VARCHAR(100) NOT NULL');
    }
}
