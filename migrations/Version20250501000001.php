<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250501000001 extends AbstractMigration
{
    public function getDescription(): string { return 'Event — recurrence_end_at + recurrence_group_id'; }

    public function up(Schema $schema): void
    {
        // Date de fin de récurrence
        $this->addSql('ALTER TABLE event ADD COLUMN recurrence_end_at DATE DEFAULT NULL');
        // ID de groupe pour lier les occurrences entre elles (UUID)
        $this->addSql('ALTER TABLE event ADD COLUMN recurrence_group_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_event_recurrence_group ON event (recurrence_group_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_event_recurrence_group ON event');
        $this->addSql('ALTER TABLE event DROP COLUMN recurrence_end_at');
        $this->addSql('ALTER TABLE event DROP COLUMN recurrence_group_id');
    }
}
