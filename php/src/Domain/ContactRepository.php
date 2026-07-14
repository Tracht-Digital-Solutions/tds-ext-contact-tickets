<?php
declare(strict_types=1);

namespace Tds\Ext\ContactTickets\Domain;

use PDO;

/** Contact-form inbox data access. */
final class ContactRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function newCount(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM contact_message WHERE status = 'new'")->fetchColumn();
    }

    /**
     * Inbox list, optionally filtered by status.
     *
     * @return list<array<string,mixed>>
     */
    public function list(?string $status): array
    {
        $sql = 'SELECT id, name, email, company, subject, status, created_at FROM contact_message';
        $params = [];
        if ($status !== null) {
            $sql .= ' WHERE status = :s';
            $params[':s'] = $status;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contact_message WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function create(string $name, string $email, ?string $company, ?string $subject, string $message): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO contact_message (name, email, company, subject, message)
             VALUES (:n, :e, :c, :s, :m)'
        );
        $stmt->execute([':n' => $name, ':e' => $email, ':c' => $company, ':s' => $subject, ':m' => $message]);
        return (int) $this->pdo->lastInsertId();
    }

    public function setStatus(int $id, string $status): void
    {
        $handled = $status === 'new' ? null : date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE contact_message SET status = :s, handled_at = :h WHERE id = :id');
        $stmt->execute([':s' => $status, ':h' => $handled, ':id' => $id]);
    }
}
