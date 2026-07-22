<?php
declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * CP2 additions to the contact inbox:
 *  - `contact_message.ip_hash` — a salted hash of the submitter IP, used purely
 *    to rate-limit the PUBLIC `POST /contact` (never shown; not the raw IP).
 *  - `contact_reply` — an admin's email reply to a submission (audit trail shown
 *    in the detail view). FK → contact_message (CASCADE). `signed => false`
 *    matches the unsigned Phinx PK (MySQL-8 FK type match). Module-prefixed class.
 */
final class AddContactTicketsReply extends AbstractMigration
{
    public function change(): void
    {
        $this->table('contact_message')
            ->addColumn('ip_hash', 'string', ['limit' => 64, 'null' => true, 'after' => 'message'])
            ->addIndex(['ip_hash', 'created_at'], ['name' => 'idx_contact_ip'])
            ->update();

        $this->table('contact_reply', [
            'id' => true,
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('message_id', 'integer', ['signed' => false])
            ->addColumn('body', 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM])
            ->addColumn('sent_by', 'string', ['limit' => 200, 'null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['message_id', 'created_at'], ['name' => 'idx_contact_reply_msg'])
            ->addForeignKey('message_id', 'contact_message', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
