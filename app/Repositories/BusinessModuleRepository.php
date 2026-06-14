<?php

namespace App\Repositories;

use PDO;

final class BusinessModuleRepository
{
    public function __construct(private PDO $pdo) {}

    public function crmStats(): array
    {
        return [
            ['label'=>'Clients','value'=>(string)$this->count('crm_clients'), 'meta'=>'Clients et prospects enregistrés'],
            ['label'=>'Relances ouvertes','value'=>(string)$this->countWhere('crm_interactions', "next_action_date IS NOT NULL AND next_action_date >= CURDATE()"), 'meta'=>'Actions commerciales à suivre'],
            ['label'=>'Opportunités','value'=>(string)$this->count('crm_opportunities'), 'meta'=>'Dossiers commerciaux actifs'],
            ['label'=>'Interactions','value'=>(string)$this->count('crm_interactions'), 'meta'=>'Historique relation client'],
        ];
    }

    public function ticketStats(): array
    {
        return [
            ['label'=>'Tickets ouverts','value'=>(string)$this->countWhere('tickets', "status NOT IN ('closed','cancelled')"), 'meta'=>'Demandes interservices en cours'],
            ['label'=>'Urgents','value'=>(string)$this->countWhere('tickets', "priority IN ('high','critical') AND status NOT IN ('closed','cancelled')"), 'meta'=>'Priorités à traiter'],
            ['label'=>'En retard','value'=>(string)$this->countWhere('tickets', "due_at IS NOT NULL AND due_at < NOW() AND status NOT IN ('closed','cancelled')"), 'meta'=>'SLA dépassés'],
            ['label'=>'Interactions','value'=>(string)$this->count('ticket_messages'), 'meta'=>'Messages et suivis internes'],
        ];
    }

    public function websiteStats(): array
    {
        return [
            ['label'=>'Pages publiées','value'=>(string)$this->countWhere('website_pages', "is_published = 1"), 'meta'=>'Contenus visibles sur le site'],
            ['label'=>'Services','value'=>(string)$this->count('website_services'), 'meta'=>'Offres import-export mises en avant'],
            ['label'=>'Leads web','value'=>(string)$this->count('website_leads'), 'meta'=>'Demandes reçues via le site'],
            ['label'=>'Suivis colis','value'=>(string)$this->count('shipment_tracking_requests'), 'meta'=>'Consultations tracking enregistrées'],
        ];
    }

    private function count(string $table): int { return (int)$this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(); }
    private function countWhere(string $table, string $where): int { return (int)$this->pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn(); }
}
