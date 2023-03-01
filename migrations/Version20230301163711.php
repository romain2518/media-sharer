<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230301163711 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX UNIQ_62FED0E5E7927C74 ON ban (email)');
        $this->addSql('CREATE INDEX idx_email ON ban (email)');
        $this->addSql('CREATE INDEX idx_email ON user (email)');
        $this->addSql('CREATE INDEX idx_pseudo ON user (pseudo)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_62FED0E5E7927C74 ON ban');
        $this->addSql('DROP INDEX idx_email ON ban');
        $this->addSql('DROP INDEX idx_email ON user');
        $this->addSql('DROP INDEX idx_pseudo ON user');
    }
}
