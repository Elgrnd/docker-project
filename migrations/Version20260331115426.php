<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260331115426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE text_file_version ADD utilisateur_id INT NOT NULL');
        $this->addSql('ALTER TABLE text_file_version ADD CONSTRAINT FK_16082C36FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_16082C36FB88E14F ON text_file_version (utilisateur_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE text_file_version DROP FOREIGN KEY FK_16082C36FB88E14F');
        $this->addSql('DROP INDEX IDX_16082C36FB88E14F ON text_file_version');
        $this->addSql('ALTER TABLE text_file_version DROP utilisateur_id');
    }
}
