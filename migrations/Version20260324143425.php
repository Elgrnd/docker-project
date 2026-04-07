<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260324143425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE binary_file (id INT NOT NULL, storage_path VARCHAR(255) NOT NULL, size INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE etre_partage (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT NOT NULL, file_id INT NOT NULL, date_partage DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_FB6FCD32FB88E14F (utilisateur_id), INDEX IDX_FB6FCD3293CB796C (file_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE file (id INT AUTO_INCREMENT NOT NULL, utilisateur_file_id INT DEFAULT NULL, name_file VARCHAR(255) NOT NULL, mime_type VARCHAR(100) DEFAULT NULL, extension VARCHAR(15) DEFAULT NULL, description LONGTEXT DEFAULT NULL, from_gitlab TINYINT(1) DEFAULT 0 NOT NULL, gitlab_path VARCHAR(1024) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, from_vm TINYINT(1) DEFAULT 0 NOT NULL, vm_path VARCHAR(1024) DEFAULT NULL, dtype VARCHAR(255) NOT NULL, INDEX IDX_8C9F3610E9C08EFE (utilisateur_file_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE groupe (id INT AUTO_INCREMENT NOT NULL, etre_chef_id INT NOT NULL, vm_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, INDEX IDX_4B98C2165F71BAD (etre_chef_id), UNIQUE INDEX UNIQ_4B98C21E0FCD18E (vm_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE groupe_file_repertoire (repertoire_id INT NOT NULL, file_id INT NOT NULL, groupe_id INT NOT NULL, droit VARCHAR(255) NOT NULL, deleted_at DATETIME DEFAULT NULL, INDEX IDX_C6AC91F1E61B789 (repertoire_id), INDEX IDX_C6AC91F93CB796C (file_id), INDEX IDX_C6AC91F7A45358C (groupe_id), PRIMARY KEY(repertoire_id, file_id, groupe_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE repertoire (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, groupe_repertoire_id INT DEFAULT NULL, utilisateur_repertoire_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, deleted_at DATETIME DEFAULT NULL, INDEX IDX_3C367876727ACA70 (parent_id), INDEX IDX_3C3678768214E212 (groupe_repertoire_id), INDEX IDX_3C3678763ABCE60B (utilisateur_repertoire_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE text_file (id INT NOT NULL, body_file LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE text_file_version (id INT AUTO_INCREMENT NOT NULL, text_file_id_id INT NOT NULL, body_file LONGTEXT NOT NULL, date_edition DATETIME NOT NULL, commentaire VARCHAR(255) DEFAULT NULL, INDEX IDX_16082C36493D27FA (text_file_id_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, vm_id INT DEFAULT NULL, login VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, adresse_mail VARCHAR(255) DEFAULT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, promotion VARCHAR(100) DEFAULT NULL, gitlab_last_commit_sha VARCHAR(64) DEFAULT NULL, gitlab_url VARCHAR(500) DEFAULT NULL, gitlab_token_cipher LONGTEXT DEFAULT NULL, gitlab_token_nonce VARCHAR(64) DEFAULT NULL, UNIQUE INDEX UNIQ_1D1C63B3E0FCD18E (vm_id), UNIQUE INDEX UNIQ_IDENTIFIER_LOGIN (login), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE utilisateur_file_repertoire (utilisateur_id INT NOT NULL, file_id INT NOT NULL, repertoire_id INT NOT NULL, INDEX IDX_90071CB6FB88E14F (utilisateur_id), INDEX IDX_90071CB693CB796C (file_id), INDEX IDX_90071CB61E61B789 (repertoire_id), PRIMARY KEY(utilisateur_id, file_id, repertoire_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE utilisateur_groupe (utilisateur_id INT NOT NULL, groupe_id INT NOT NULL, role VARCHAR(50) NOT NULL, INDEX IDX_6514B6AAFB88E14F (utilisateur_id), INDEX IDX_6514B6AA7A45358C (groupe_id), PRIMARY KEY(utilisateur_id, groupe_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE virtual_machine (id INT AUTO_INCREMENT NOT NULL, vm_id INT DEFAULT NULL, vm_ip VARCHAR(255) DEFAULT NULL, vm_status VARCHAR(255) DEFAULT NULL, delete_vm_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE binary_file ADD CONSTRAINT FK_866F67A1BF396750 FOREIGN KEY (id) REFERENCES file (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE etre_partage ADD CONSTRAINT FK_FB6FCD32FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE etre_partage ADD CONSTRAINT FK_FB6FCD3293CB796C FOREIGN KEY (file_id) REFERENCES file (id)');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F3610E9C08EFE FOREIGN KEY (utilisateur_file_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE groupe ADD CONSTRAINT FK_4B98C2165F71BAD FOREIGN KEY (etre_chef_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE groupe ADD CONSTRAINT FK_4B98C21E0FCD18E FOREIGN KEY (vm_id) REFERENCES virtual_machine (id)');
        $this->addSql('ALTER TABLE groupe_file_repertoire ADD CONSTRAINT FK_C6AC91F1E61B789 FOREIGN KEY (repertoire_id) REFERENCES repertoire (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE groupe_file_repertoire ADD CONSTRAINT FK_C6AC91F93CB796C FOREIGN KEY (file_id) REFERENCES file (id)');
        $this->addSql('ALTER TABLE groupe_file_repertoire ADD CONSTRAINT FK_C6AC91F7A45358C FOREIGN KEY (groupe_id) REFERENCES groupe (id)');
        $this->addSql('ALTER TABLE repertoire ADD CONSTRAINT FK_3C367876727ACA70 FOREIGN KEY (parent_id) REFERENCES repertoire (id)');
        $this->addSql('ALTER TABLE repertoire ADD CONSTRAINT FK_3C3678768214E212 FOREIGN KEY (groupe_repertoire_id) REFERENCES groupe (id)');
        $this->addSql('ALTER TABLE repertoire ADD CONSTRAINT FK_3C3678763ABCE60B FOREIGN KEY (utilisateur_repertoire_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE text_file ADD CONSTRAINT FK_8BE14825BF396750 FOREIGN KEY (id) REFERENCES file (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE text_file_version ADD CONSTRAINT FK_16082C36493D27FA FOREIGN KEY (text_file_id_id) REFERENCES text_file (id)');
        $this->addSql('ALTER TABLE utilisateur ADD CONSTRAINT FK_1D1C63B3E0FCD18E FOREIGN KEY (vm_id) REFERENCES virtual_machine (id)');
        $this->addSql('ALTER TABLE utilisateur_file_repertoire ADD CONSTRAINT FK_90071CB6FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE utilisateur_file_repertoire ADD CONSTRAINT FK_90071CB693CB796C FOREIGN KEY (file_id) REFERENCES file (id)');
        $this->addSql('ALTER TABLE utilisateur_file_repertoire ADD CONSTRAINT FK_90071CB61E61B789 FOREIGN KEY (repertoire_id) REFERENCES repertoire (id)');
        $this->addSql('ALTER TABLE utilisateur_groupe ADD CONSTRAINT FK_6514B6AAFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE utilisateur_groupe ADD CONSTRAINT FK_6514B6AA7A45358C FOREIGN KEY (groupe_id) REFERENCES groupe (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE binary_file DROP FOREIGN KEY FK_866F67A1BF396750');
        $this->addSql('ALTER TABLE etre_partage DROP FOREIGN KEY FK_FB6FCD32FB88E14F');
        $this->addSql('ALTER TABLE etre_partage DROP FOREIGN KEY FK_FB6FCD3293CB796C');
        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F3610E9C08EFE');
        $this->addSql('ALTER TABLE groupe DROP FOREIGN KEY FK_4B98C2165F71BAD');
        $this->addSql('ALTER TABLE groupe DROP FOREIGN KEY FK_4B98C21E0FCD18E');
        $this->addSql('ALTER TABLE groupe_file_repertoire DROP FOREIGN KEY FK_C6AC91F1E61B789');
        $this->addSql('ALTER TABLE groupe_file_repertoire DROP FOREIGN KEY FK_C6AC91F93CB796C');
        $this->addSql('ALTER TABLE groupe_file_repertoire DROP FOREIGN KEY FK_C6AC91F7A45358C');
        $this->addSql('ALTER TABLE repertoire DROP FOREIGN KEY FK_3C367876727ACA70');
        $this->addSql('ALTER TABLE repertoire DROP FOREIGN KEY FK_3C3678768214E212');
        $this->addSql('ALTER TABLE repertoire DROP FOREIGN KEY FK_3C3678763ABCE60B');
        $this->addSql('ALTER TABLE text_file DROP FOREIGN KEY FK_8BE14825BF396750');
        $this->addSql('ALTER TABLE text_file_version DROP FOREIGN KEY FK_16082C36493D27FA');
        $this->addSql('ALTER TABLE utilisateur DROP FOREIGN KEY FK_1D1C63B3E0FCD18E');
        $this->addSql('ALTER TABLE utilisateur_file_repertoire DROP FOREIGN KEY FK_90071CB6FB88E14F');
        $this->addSql('ALTER TABLE utilisateur_file_repertoire DROP FOREIGN KEY FK_90071CB693CB796C');
        $this->addSql('ALTER TABLE utilisateur_file_repertoire DROP FOREIGN KEY FK_90071CB61E61B789');
        $this->addSql('ALTER TABLE utilisateur_groupe DROP FOREIGN KEY FK_6514B6AAFB88E14F');
        $this->addSql('ALTER TABLE utilisateur_groupe DROP FOREIGN KEY FK_6514B6AA7A45358C');
        $this->addSql('DROP TABLE binary_file');
        $this->addSql('DROP TABLE etre_partage');
        $this->addSql('DROP TABLE file');
        $this->addSql('DROP TABLE groupe');
        $this->addSql('DROP TABLE groupe_file_repertoire');
        $this->addSql('DROP TABLE repertoire');
        $this->addSql('DROP TABLE text_file');
        $this->addSql('DROP TABLE text_file_version');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE utilisateur_file_repertoire');
        $this->addSql('DROP TABLE utilisateur_groupe');
        $this->addSql('DROP TABLE virtual_machine');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
