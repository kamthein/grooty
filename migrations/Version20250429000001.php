<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250429000001 extends AbstractMigration
{
    public function getDescription(): string { return 'Guardian — reset_token + reset_token_expires_at'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guardian ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE guardian ADD COLUMN reset_token_expires_at DATETIME DEFAULT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guardian DROP COLUMN reset_token');
        $this->addSql('ALTER TABLE guardian DROP COLUMN reset_token_expires_at');
    }
}
