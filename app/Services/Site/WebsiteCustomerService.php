<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\Site\WebsiteCustomerRepository;
use RuntimeException;

final class WebsiteCustomerService
{
    public function __construct(
        private WebsiteCustomerRepository $repository,
        private ?SiteMediaUploadService $uploads = null,
    ) {
        $this->uploads ??= new SiteMediaUploadService();
    }

    /** @param array<string,mixed> $input */
    public function register(array $input): int
    {
        $name = trim((string) ($input['full_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Renseignez un nom et une adresse email valides.');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('Le mot de passe doit contenir au moins 8 caractères.');
        }
        if ($this->repository->emailExists($email)) {
            throw new RuntimeException('Un compte client utilise déjà cette adresse email.');
        }
        return $this->repository->create([
            'full_name' => $name,
            'email' => $email,
            'phone' => trim((string) ($input['phone'] ?? '')) ?: null,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    public function authenticate(string $email, string $password): int
    {
        $customer = $this->repository->findByEmail(strtolower(trim($email)));
        if (!$customer || $customer['status'] !== 'active' || !password_verify($password, (string) $customer['password_hash'])) {
            throw new RuntimeException('Email ou mot de passe incorrect.');
        }
        return (int) $customer['id'];
    }

    /** @return array<string,mixed> */
    public function dashboard(int $customerId): array
    {
        $customer = $this->repository->find($customerId);
        if (!$customer) {
            throw new RuntimeException('Compte client introuvable.');
        }
        $conversation = $this->repository->conversationForCustomer($customerId);

        $pdo = \App\Models\Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT c.*, s_dep.name AS agence_depart_name, s_arr.name AS agence_arrivee_name
            FROM lbp_colis c
            LEFT JOIN lbp_clients exp ON c.expediteur_id = exp.id
            LEFT JOIN lbp_clients dest ON c.destinataire_id = dest.id
            LEFT JOIN company_sites s_dep ON c.agence_depart_id = s_dep.id
            LEFT JOIN company_sites s_arr ON c.agence_arrivee_id = s_arr.id
            WHERE (exp.email IS NOT NULL AND exp.email != '' AND exp.email = :email_exp)
               OR (dest.email IS NOT NULL AND dest.email != '' AND dest.email = :email_dest)
               OR (exp.phone IS NOT NULL AND exp.phone != '' AND exp.phone = :phone_exp)
               OR (dest.phone IS NOT NULL AND dest.phone != '' AND dest.phone = :phone_dest)
            ORDER BY c.created_at DESC
        ");
        $customerEmail = (string) ($customer['email'] ?? '');
        $customerPhone = (string) ($customer['phone'] ?? '');

        $stmt->execute([
            'email_exp' => $customerEmail,
            'email_dest' => $customerEmail,
            'phone_exp' => $customerPhone,
            'phone_dest' => $customerPhone,
        ]);
        $parcels = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return [
            'customer' => $customer,
            'conversation' => $conversation,
            'messages' => $this->repository->messages((int) $conversation['id']),
            'parcels' => $parcels,
        ];
    }

    public function sendCustomerMessage(int $customerId, string $message, ?array $file): int
    {
        $conversation = $this->repository->conversationForCustomer($customerId);
        return $this->send((int) $conversation['id'], 'customer', $customerId, $message, $file);
    }

    public function sendManagerMessage(int $conversationId, int $managerId, string $message, ?array $file): int
    {
        if (!$this->repository->conversationWithCustomer($conversationId)) {
            throw new RuntimeException('Conversation introuvable.');
        }
        return $this->send($conversationId, 'manager', $managerId, $message, $file);
    }

    /** @return array<int,array<string,mixed>> */
    public function messages(int $conversationId, int $afterId = 0): array
    {
        return $this->repository->messages($conversationId, $afterId);
    }

    /** @return array<int,array<string,mixed>> */
    public function conversations(): array
    {
        return $this->repository->conversations();
    }

    /** @return array<string,mixed>|null */
    public function conversation(int $id): ?array
    {
        return $this->repository->conversationWithCustomer($id);
    }

    private function send(int $conversationId, string $senderType, int $senderId, string $message, ?array $file): int
    {
        $message = trim($message);
        $attachment = $this->uploads->storeMessageAttachment($file);
        if ($message === '' && $attachment === null) {
            throw new RuntimeException('Écrivez un message ou joignez un média.');
        }
        return $this->repository->addMessage(
            $conversationId,
            $senderType,
            $senderId,
            $message !== '' ? $message : null,
            $attachment,
        );
    }
}
