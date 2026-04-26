<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250422000004 extends AbstractMigration
{
    public function getDescription(): string { return 'Child — share_token pour lien public'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE child ADD COLUMN share_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_child_share_token ON child (share_token)');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_child_share_token ON child');
        $this->addSql('ALTER TABLE child DROP COLUMN share_token');
    }
}
