<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250420000001 extends AbstractMigration
{
    public function getDescription(): string { return 'Grooty — schéma initial complet'; }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');

        $this->addSql('CREATE TABLE guardian (
            id         INT AUTO_INCREMENT NOT NULL,
            email      VARCHAR(180) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name  VARCHAR(100) NOT NULL,
            roles      JSON         NOT NULL,
            password   VARCHAR(255) NOT NULL,
            created_at DATETIME     NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            UNIQUE INDEX UNIQ_EMAIL (email),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addSql('CREATE TABLE child (
            id          INT AUTO_INCREMENT NOT NULL,
            first_name  VARCHAR(100) NOT NULL,
            birth_date  DATE         DEFAULT NULL,
            avatar_path VARCHAR(255) DEFAULT NULL,
            created_at  DATETIME     NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addSql('CREATE TABLE child_guardian (
            id              INT AUTO_INCREMENT NOT NULL,
            child_id        INT         NOT NULL,
            guardian_id     INT         NOT NULL,
            role            VARCHAR(50) NOT NULL DEFAULT "parent",
            permission      VARCHAR(50) NOT NULL DEFAULT "view",
            joined_at       DATETIME    NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            invite_token    VARCHAR(255) DEFAULT NULL,
            invite_accepted TINYINT(1)   DEFAULT 0,
            UNIQUE INDEX UNIQ_invite_token (invite_token),
            INDEX IDX_cg_child    (child_id),
            INDEX IDX_cg_guardian (guardian_id),
            PRIMARY KEY (id),
            CONSTRAINT FK_cg_child    FOREIGN KEY (child_id)    REFERENCES child    (id) ON DELETE CASCADE,
            CONSTRAINT FK_cg_guardian FOREIGN KEY (guardian_id) REFERENCES guardian (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addSql('CREATE TABLE event_image (
            id             INT AUTO_INCREMENT NOT NULL,
            child_id       INT          NOT NULL,
            uploaded_by_id INT          NOT NULL,
            file_path      VARCHAR(500) NOT NULL,
            thumbnail_path VARCHAR(500) DEFAULT NULL,
            label          VARCHAR(255) DEFAULT NULL,
            created_at     DATETIME     NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX IDX_ei_child (child_id),
            PRIMARY KEY (id),
            CONSTRAINT FK_ei_child   FOREIGN KEY (child_id)       REFERENCES child    (id) ON DELETE CASCADE,
            CONSTRAINT FK_ei_uploader FOREIGN KEY (uploaded_by_id) REFERENCES guardian (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addSql('CREATE TABLE event (
            id                      INT AUTO_INCREMENT NOT NULL,
            child_id                INT          NOT NULL,
            created_by_id           INT          NOT NULL,
            responsible_guardian_id INT          DEFAULT NULL,
            image_id                INT          DEFAULT NULL,
            title                   VARCHAR(255) NOT NULL,
            description             LONGTEXT     DEFAULT NULL,
            type                    VARCHAR(50)  NOT NULL DEFAULT "autre",
            start_at                DATETIME     NOT NULL,
            end_at                  DATETIME     DEFAULT NULL,
            all_day                 TINYINT(1)   NOT NULL DEFAULT 0,
            recurrence              VARCHAR(50)  NOT NULL DEFAULT "none",
            visible_to              JSON         DEFAULT NULL,
            created_at              DATETIME     NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX IDX_event_child       (child_id),
            INDEX IDX_event_created_by  (created_by_id),
            INDEX IDX_event_responsible (responsible_guardian_id),
            INDEX IDX_event_image       (image_id),
            INDEX IDX_event_start       (start_at),
            PRIMARY KEY (id),
            CONSTRAINT FK_event_child       FOREIGN KEY (child_id)               REFERENCES child       (id) ON DELETE CASCADE,
            CONSTRAINT FK_event_created_by  FOREIGN KEY (created_by_id)          REFERENCES guardian    (id),
            CONSTRAINT FK_event_responsible FOREIGN KEY (responsible_guardian_id) REFERENCES guardian    (id) ON DELETE SET NULL,
            CONSTRAINT FK_event_image       FOREIGN KEY (image_id)               REFERENCES event_image (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addSql('CREATE TABLE note (
            id         INT AUTO_INCREMENT NOT NULL,
            child_id   INT      NOT NULL,
            author_id  INT      NOT NULL,
            content    LONGTEXT DEFAULT NULL,
            visible_to JSON     DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX IDX_note_child  (child_id),
            INDEX IDX_note_author (author_id),
            INDEX IDX_note_date   (created_at),
            PRIMARY KEY (id),
            CONSTRAINT FK_note_child  FOREIGN KEY (child_id)  REFERENCES child    (id) ON DELETE CASCADE,
            CONSTRAINT FK_note_author FOREIGN KEY (author_id) REFERENCES guardian (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addSql('CREATE TABLE attachment (
            id             INT AUTO_INCREMENT NOT NULL,
            note_id        INT          NOT NULL,
            type           VARCHAR(50)  NOT NULL DEFAULT "photo",
            file_path      VARCHAR(500) NOT NULL,
            thumbnail_path VARCHAR(500) DEFAULT NULL,
            original_name  VARCHAR(255) DEFAULT NULL,
            file_size      INT          DEFAULT NULL,
            uploaded_at    DATETIME     NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX IDX_att_note (note_id),
            PRIMARY KEY (id),
            CONSTRAINT FK_att_note FOREIGN KEY (note_id) REFERENCES note (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('DROP TABLE IF EXISTS attachment');
        $this->addSql('DROP TABLE IF EXISTS note');
        $this->addSql('DROP TABLE IF EXISTS event');
        $this->addSql('DROP TABLE IF EXISTS event_image');
        $this->addSql('DROP TABLE IF EXISTS child_guardian');
        $this->addSql('DROP TABLE IF EXISTS child');
        $this->addSql('DROP TABLE IF EXISTS guardian');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }
}
