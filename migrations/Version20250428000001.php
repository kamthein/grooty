<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250428000001 extends AbstractMigration
{
    public function getDescription(): string { return 'Fix FK constraints pour suppression guardian'; }

    public function up(Schema $schema): void
    {
        // event_image.uploaded_by_id → SET NULL
        $this->addSql('ALTER TABLE event_image DROP FOREIGN KEY FK_ei_uploader');
        $this->addSql('ALTER TABLE event_image MODIFY uploaded_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event_image ADD CONSTRAINT FK_ei_uploader FOREIGN KEY (uploaded_by_id) REFERENCES guardian (id) ON DELETE SET NULL');

        // note.author_id → SET NULL
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_note_author');
        $this->addSql('ALTER TABLE note MODIFY author_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_note_author FOREIGN KEY (author_id) REFERENCES guardian (id) ON DELETE SET NULL');

        // event.created_by_id → SET NULL
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_event_created_by');
        $this->addSql('ALTER TABLE event MODIFY created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_event_created_by FOREIGN KEY (created_by_id) REFERENCES guardian (id) ON DELETE SET NULL');

        // event.responsible_guardian_id → SET NULL
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_event_responsible');
        $this->addSql('ALTER TABLE event MODIFY responsible_guardian_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_event_responsible FOREIGN KEY (responsible_guardian_id) REFERENCES guardian (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void {}
}
