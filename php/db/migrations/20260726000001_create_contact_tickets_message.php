<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * A public contact-form submission → an admin-triaged message. Standalone
 * (separate from the support-ticket system). `status` moves new → handled | spam.
 * Module-prefixed class name (in-process auto-migrator loads all modules into
 * one process).
 */
final class CreateContactTicketsMessage extends AbstractMigration
{
    public function change(): void
    {
        $this->table('contact_message', [
            'id' => true,
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('name', 'string', ['limit' => 200])
            ->addColumn('email', 'string', ['limit' => 254])
            ->addColumn('company', 'string', ['limit' => 200, 'null' => true])
            ->addColumn('subject', 'string', ['limit' => 200, 'null' => true])
            ->addColumn('message', 'text')
            ->addColumn('status', 'enum', ['values' => ['new', 'handled', 'spam'], 'default' => 'new'])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('handled_at', 'datetime', ['null' => true])
            ->addIndex(['status', 'created_at'], ['name' => 'idx_contact_status'])
            ->addIndex(['email'], ['name' => 'idx_contact_email'])
            ->create();
    }
}
