<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250430000001 extends AbstractMigration
{
    public function getDescription(): string { return 'Système de validation et historique des modifications'; }

    public function up(Schema $schema): void
    {
        // Statut pending sur event + snapshot de l'état proposé
        $this->addSql("ALTER TABLE event ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
        $this->addSql('ALTER TABLE event ADD COLUMN pending_snapshot JSON DEFAULT NULL');

        // Demandes de modification
        $this->addSql('CREATE TABLE event_change_request (
            id              INT AUTO_INCREMENT NOT NULL,
            event_id        INT NOT NULL,
            requested_by_id INT DEFAULT NULL,
            action          VARCHAR(20) NOT NULL COMMENT "create/update/delete",
            event_snapshot  JSON NOT NULL COMMENT "état proposé de l événement",
            status          VARCHAR(20) NOT NULL DEFAULT "pending" COMMENT "pending/approved/rejected/cancelled",
            created_at      DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            resolved_at     DATETIME DEFAULT NULL,
            INDEX IDX_ecr_event     (event_id),
            INDEX IDX_ecr_requester (requested_by_id),
            INDEX IDX_ecr_status    (status),
            PRIMARY KEY (id),
            CONSTRAINT FK_ecr_event     FOREIGN KEY (event_id)        REFERENCES event    (id) ON DELETE CASCADE,
            CONSTRAINT FK_ecr_requester FOREIGN KEY (requested_by_id) REFERENCES guardian (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        // Approbations individuelles par gardien
        $this->addSql('CREATE TABLE event_change_approval (
            id                INT AUTO_INCREMENT NOT NULL,
            change_request_id INT NOT NULL,
            guardian_id       INT DEFAULT NULL,
            token             VARCHAR(64) NOT NULL COMMENT "token unique pour valider depuis l email",
            status            VARCHAR(20) NOT NULL DEFAULT "pending" COMMENT "pending/approved/rejected",
            responded_at      DATETIME DEFAULT NULL,
            UNIQUE INDEX UNIQ_eca_token (token),
            INDEX IDX_eca_request  (change_request_id),
            INDEX IDX_eca_guardian (guardian_id),
            PRIMARY KEY (id),
            CONSTRAINT FK_eca_request  FOREIGN KEY (change_request_id) REFERENCES event_change_request (id) ON DELETE CASCADE,
            CONSTRAINT FK_eca_guardian FOREIGN KEY (guardian_id)       REFERENCES guardian (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        // Historique des modifications
        $this->addSql('CREATE TABLE event_history (
            id          BIGINT AUTO_INCREMENT NOT NULL,
            event_id    INT DEFAULT NULL,
            guardian_id INT DEFAULT NULL,
            action      VARCHAR(30) NOT NULL COMMENT "created/updated/deleted/approved/rejected/notified",
            label       VARCHAR(255) NOT NULL COMMENT "description lisible de l action",
            snapshot    JSON DEFAULT NULL,
            created_at  DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX IDX_eh_event    (event_id),
            INDEX IDX_eh_guardian (guardian_id),
            INDEX IDX_eh_created  (created_at),
            PRIMARY KEY (id),
            CONSTRAINT FK_eh_event    FOREIGN KEY (event_id)    REFERENCES event    (id) ON DELETE SET NULL,
            CONSTRAINT FK_eh_guardian FOREIGN KEY (guardian_id) REFERENCES guardian (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_change_approval');
        $this->addSql('DROP TABLE event_change_request');
        $this->addSql('DROP TABLE event_history');
        $this->addSql('ALTER TABLE event DROP COLUMN status');
        $this->addSql('ALTER TABLE event DROP COLUMN pending_snapshot');
    }
}
