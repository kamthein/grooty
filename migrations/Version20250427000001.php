<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250427000001 extends AbstractMigration
{
    public function getDescription(): string { return 'Analytics events + onboarding step'; }

    public function up(Schema $schema): void
    {
        // Tracking des événements utilisateur
        $this->addSql('CREATE TABLE analytics_event (
            id           BIGINT AUTO_INCREMENT NOT NULL,
            guardian_id  INT          DEFAULT NULL,
            session_id   VARCHAR(64)  NOT NULL,
            event_type   VARCHAR(50)  NOT NULL,
            page         VARCHAR(500) NOT NULL,
            target       VARCHAR(500) DEFAULT NULL,
            data         JSON         DEFAULT NULL,
            ip_hash      VARCHAR(64)  DEFAULT NULL,
            user_agent   VARCHAR(500) DEFAULT NULL,
            created_at   DATETIME     NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX IDX_ae_guardian   (guardian_id),
            INDEX IDX_ae_type       (event_type),
            INDEX IDX_ae_page       (page(191)),
            INDEX IDX_ae_created_at (created_at),
            PRIMARY KEY (id),
            CONSTRAINT FK_ae_guardian FOREIGN KEY (guardian_id) REFERENCES guardian (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        // Progression onboarding sur chaque guardian
        $this->addSql('ALTER TABLE guardian ADD COLUMN onboarding_step INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE analytics_event');
        $this->addSql('ALTER TABLE guardian DROP COLUMN onboarding_step');
    }
}
