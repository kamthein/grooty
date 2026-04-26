<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250422000003 extends AbstractMigration
{
    public function getDescription(): string { return 'Child — ajout colonne theme'; }
    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE child ADD COLUMN theme VARCHAR(50) NOT NULL DEFAULT 'train'");
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE child DROP COLUMN theme');
    }
}
