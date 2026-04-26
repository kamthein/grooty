<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250422000001 extends AbstractMigration
{
    public function getDescription(): string { return 'child_guardian — guardian nullable + invite_email'; }

    public function up(Schema $schema): void
    {
        // Rendre guardian_id nullable
        $this->addSql('ALTER TABLE child_guardian MODIFY guardian_id INT DEFAULT NULL');

        // Supprimer la FK existante et la recréer avec SET NULL
        $this->addSql('ALTER TABLE child_guardian DROP FOREIGN KEY FK_cg_guardian');
        $this->addSql('ALTER TABLE child_guardian ADD CONSTRAINT FK_cg_guardian FOREIGN KEY (guardian_id) REFERENCES guardian (id) ON DELETE SET NULL');

        // Ajouter invite_email
        $this->addSql('ALTER TABLE child_guardian ADD COLUMN invite_email VARCHAR(180) DEFAULT NULL AFTER guardian_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE child_guardian DROP COLUMN invite_email');
        $this->addSql('ALTER TABLE child_guardian DROP FOREIGN KEY FK_cg_guardian');
        $this->addSql('ALTER TABLE child_guardian MODIFY guardian_id INT NOT NULL');
        $this->addSql('ALTER TABLE child_guardian ADD CONSTRAINT FK_cg_guardian FOREIGN KEY (guardian_id) REFERENCES guardian (id) ON DELETE CASCADE');
    }
}
