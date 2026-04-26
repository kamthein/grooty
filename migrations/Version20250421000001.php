<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250421000001 extends AbstractMigration
{
    public function getDescription(): string { return 'Grooty — supprime event_id de note, recrée attachment si nécessaire'; }

    public function up(Schema $schema): void
    {
        // 1. Supprimer event_id de la table note si elle existe
        $this->addSql("
            SET @col_exists = (
                SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'note'
                AND COLUMN_NAME = 'event_id'
            )
        ");
        $this->addSql("
            SET @fk_exists = (
                SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'note'
                AND COLUMN_NAME = 'event_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            )
        ");
        $this->addSql("SET @sql = IF(@fk_exists > 0,
            (SELECT CONCAT('ALTER TABLE note DROP FOREIGN KEY ', CONSTRAINT_NAME)
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'note' AND COLUMN_NAME = 'event_id'
             AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1),
            'SELECT 1')");
        $this->addSql("PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt");

        $this->addSql("SET @sql2 = IF(@col_exists > 0,
            'ALTER TABLE note DROP COLUMN event_id',
            'SELECT 1')");
        $this->addSql("PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2");

        // 2. Recréer la table attachment si elle est vide / mal formée
        $this->addSql("DROP TABLE IF EXISTS attachment");
        $this->addSql("CREATE TABLE attachment (
            id             INT AUTO_INCREMENT NOT NULL,
            note_id        INT          NOT NULL,
            type           VARCHAR(50)  NOT NULL DEFAULT 'photo',
            file_path      VARCHAR(500) NOT NULL,
            thumbnail_path VARCHAR(500) DEFAULT NULL,
            original_name  VARCHAR(255) DEFAULT NULL,
            file_size      INT          DEFAULT NULL,
            uploaded_at    DATETIME     NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            INDEX IDX_att_note (note_id),
            PRIMARY KEY (id),
            CONSTRAINT FK_att_note FOREIGN KEY (note_id) REFERENCES note (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");
    }

    public function down(Schema $schema): void
    {
        // Pas de rollback nécessaire
    }
}
