<?php

declare(strict_types=1);

namespace App\Repositories\Site;

use PDO;

final class WebsiteCustomerRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM website_customer_accounts WHERE LOWER(email) = LOWER(:email)');
        $stmt->execute(['email' => $email]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO website_customer_accounts (full_name, email, phone, password_hash)
            VALUES (:full_name, :email, :phone, :password_hash)
        ");
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM website_customer_accounts WHERE LOWER(email) = LOWER(:email) LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, full_name, email, phone, status, created_at FROM website_customer_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<string,mixed> */
    public function conversationForCustomer(int $customerId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM website_conversations WHERE customer_id = :customer_id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['customer_id' => $customerId]);
        $conversation = $stmt->fetch();
        if ($conversation) {
            return $conversation;
        }
        $create = $this->pdo->prepare('INSERT INTO website_conversations (customer_id, subject, last_message_at) VALUES (:customer_id, :subject, NOW())');
        $create->execute(['customer_id' => $customerId, 'subject' => 'Assistance et commandes']);
        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'customer_id' => $customerId,
            'subject' => 'Assistance et commandes',
            'status' => 'open',
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function messages(int $conversationId, int $afterId = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM website_conversation_messages
            WHERE conversation_id = :conversation_id AND id > :after_id
            ORDER BY id
        ");
        $stmt->bindValue('conversation_id', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue('after_id', $afterId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** @param array<string,mixed>|null $attachment */
    public function addMessage(int $conversationId, string $senderType, int $senderId, ?string $message, ?array $attachment): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO website_conversation_messages
                (conversation_id, sender_type, sender_id, message, attachment_path,
                 attachment_name, attachment_mime, attachment_size)
            VALUES
                (:conversation_id, :sender_type, :sender_id, :message, :attachment_path,
                 :attachment_name, :attachment_mime, :attachment_size)
        ");
        $stmt->execute([
            'conversation_id' => $conversationId,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message' => $message,
            'attachment_path' => $attachment['path'] ?? null,
            'attachment_name' => $attachment['original_name'] ?? null,
            'attachment_mime' => $attachment['mime_type'] ?? null,
            'attachment_size' => $attachment['size_bytes'] ?? null,
        ]);
        $this->pdo->prepare('UPDATE website_conversations SET status = :status, last_message_at = NOW(), updated_at = NOW() WHERE id = :id')
            ->execute(['status' => $senderType === 'customer' ? 'open' : 'pending', 'id' => $conversationId]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<int,array<string,mixed>> */
    public function conversations(): array
    {
        return $this->pdo->query("
            SELECT c.*, a.full_name, a.email,
                (SELECT message FROM website_conversation_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                (SELECT COUNT(*) FROM website_conversation_messages m WHERE m.conversation_id = c.id) AS message_count
            FROM website_conversations c
            INNER JOIN website_customer_accounts a ON a.id = c.customer_id
            ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
        ")->fetchAll() ?: [];
    }

    /** @return array<string,mixed>|null */
    public function conversationWithCustomer(int $conversationId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, a.full_name, a.email, a.phone
            FROM website_conversations c
            INNER JOIN website_customer_accounts a ON a.id = c.customer_id
            WHERE c.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $conversationId]);
        return $stmt->fetch() ?: null;
    }
}
