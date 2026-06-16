<?php

namespace App\Security;

use InvalidArgumentException;

final class OperationPolicy
{
    public const RH_EMPLOYEE_VIEW = 'rh.employee.view';
    public const RH_EMPLOYEE_CREATE = 'rh.employee.create';
    public const RH_EMPLOYEE_UPDATE = 'rh.employee.update';
    public const RH_MUTATION_CREATE = 'rh.mutation.create';
    public const RH_EXIT_MANAGE = 'rh.exit.manage';
    public const RH_HISTORY_CREATE = 'rh.history.create';
    public const RH_CONTRACT_MANAGE = 'rh.contract.manage';
    public const RH_PAYROLL_PARAMS_MANAGE = 'rh.payroll_params.manage';
    public const RH_ATTENDANCE_MANAGE = 'rh.attendance.manage';
    public const RH_PAYROLL_MANAGE = 'rh.payroll.manage';
    public const RH_LEAVES_MANAGE = 'rh.leaves.manage';
    public const RH_LEAVES_REQUEST = 'rh.leaves.request';

    public const CRM_CLIENTS_MANAGE = 'crm.clients.manage';
    public const CRM_CLIENTS_VIEW = 'crm.clients.view';
    public const CRM_OPPORTUNITIES_MANAGE = 'crm.opportunities.manage';
    public const CRM_OPPORTUNITIES_VIEW = 'crm.opportunities.view';
    
    public const COLISAGE_COLIS_MANAGE = 'colisage.colis.manage';
    public const COLISAGE_COLIS_VIEW = 'colisage.colis.view';
    public const COLISAGE_EXPEDITIONS_MANAGE = 'colisage.expeditions.manage';
    public const COLISAGE_EXPEDITIONS_VIEW = 'colisage.expeditions.view';

    public const FLOTTE_LIVREURS_MANAGE = 'flotte_livreurs.manage';
    public const FLOTTE_LIVREURS_VIEW = 'flotte_livreurs.view';
    public const TRACKING_GPS_MANAGE = 'tracking_gps.manage';
    public const TRACKING_GPS_VIEW = 'tracking_gps.view';
    public const ENTREPOT_INVENTAIRES_MANAGE = 'entrepot_inventaires.manage';
    public const ENTREPOT_INVENTAIRES_VIEW = 'entrepot_inventaires.view';

    public const TRANSIT_PRESTATAIRES_MANAGE = 'transit_prestataires.manage';
    public const TRANSIT_PRESTATAIRES_VIEW = 'transit_prestataires.view';
    public const FACTURATION_FACTURES_MANAGE = 'facturation_factures.manage';
    public const FACTURATION_FACTURES_VIEW = 'facturation_factures.view';
    public const FINANCE_RETRAITS_MANAGE = 'finance_retraits.manage';
    public const FINANCE_RETRAITS_VIEW = 'finance_retraits.view';
    public const FINANCE_COMPENSATIONS_MANAGE = 'finance_compensations.manage';
    public const FINANCE_COMPENSATIONS_VIEW = 'finance_compensations.view';
    public const LOGISTIQUE_FOURNITURES_MANAGE = 'logistique_fournitures.manage';
    public const LOGISTIQUE_FOURNITURES_VIEW = 'logistique_fournitures.view';

    public static function requirements(string $operation): array
    {
        return match ($operation) {
            self::RH_EMPLOYEE_VIEW => [
                PermissionEntityRegistry::RH_EMPLOYEES => PermissionAction::VIEW,
            ],
            self::RH_EMPLOYEE_CREATE => [
                PermissionEntityRegistry::RH_EMPLOYEES => PermissionAction::CREATE,
                PermissionEntityRegistry::RH_EMPLOYEE_HISTORY => PermissionAction::CREATE,
            ],
            self::RH_EMPLOYEE_UPDATE => [
                PermissionEntityRegistry::RH_EMPLOYEES => PermissionAction::UPDATE,
            ],
            self::RH_MUTATION_CREATE => [
                PermissionEntityRegistry::RH_EMPLOYEES => PermissionAction::UPDATE,
                PermissionEntityRegistry::RH_EMPLOYEE_MUTATIONS => PermissionAction::CREATE,
                PermissionEntityRegistry::RH_EMPLOYEE_HISTORY => PermissionAction::CREATE,
            ],
            self::RH_EXIT_MANAGE => [
                PermissionEntityRegistry::RH_EMPLOYEES => PermissionAction::UPDATE,
                PermissionEntityRegistry::RH_EMPLOYEE_HISTORY => PermissionAction::CREATE,
            ],
            self::RH_HISTORY_CREATE => [
                PermissionEntityRegistry::RH_EMPLOYEES => PermissionAction::VIEW,
                PermissionEntityRegistry::RH_EMPLOYEE_HISTORY => PermissionAction::CREATE,
            ],
            self::RH_CONTRACT_MANAGE => [
                PermissionEntityRegistry::RH_CONTRACTS => PermissionAction::UPDATE,
            ],
            self::RH_PAYROLL_PARAMS_MANAGE => [
                PermissionEntityRegistry::RH_PAYROLL_PARAMS => PermissionAction::UPDATE,
            ],
            self::RH_ATTENDANCE_MANAGE => [
                PermissionEntityRegistry::RH_ATTENDANCE => PermissionAction::UPDATE,
            ],
            self::RH_PAYROLL_MANAGE => [
                PermissionEntityRegistry::RH_PAYROLL => PermissionAction::UPDATE,
            ],
            self::RH_LEAVES_MANAGE => [
                PermissionEntityRegistry::RH_LEAVES => PermissionAction::UPDATE,
            ],
            self::RH_LEAVES_REQUEST => [
                PermissionEntityRegistry::RH_LEAVES => PermissionAction::VIEW,
            ],
            self::CRM_CLIENTS_MANAGE => [
                PermissionEntityRegistry::CRM_CLIENTS => PermissionAction::UPDATE,
            ],
            self::CRM_CLIENTS_VIEW => [
                PermissionEntityRegistry::CRM_CLIENTS => PermissionAction::VIEW,
                PermissionEntityRegistry::CRM_OPPORTUNITIES => PermissionAction::VIEW,
            ],
            self::CRM_OPPORTUNITIES_MANAGE => [
                PermissionEntityRegistry::CRM_OPPORTUNITIES => PermissionAction::UPDATE,
            ],
            self::CRM_OPPORTUNITIES_VIEW => [
                PermissionEntityRegistry::CRM_OPPORTUNITIES => PermissionAction::VIEW,
            ],
            self::COLISAGE_COLIS_MANAGE => [
                PermissionEntityRegistry::COLISAGE_COLIS => PermissionAction::UPDATE,
            ],
            self::COLISAGE_COLIS_VIEW => [
                PermissionEntityRegistry::COLISAGE_COLIS => PermissionAction::VIEW,
                PermissionEntityRegistry::COLISAGE_EXPEDITIONS => PermissionAction::VIEW,
            ],
            self::COLISAGE_EXPEDITIONS_MANAGE => [
                PermissionEntityRegistry::COLISAGE_EXPEDITIONS => PermissionAction::UPDATE,
            ],
            self::COLISAGE_EXPEDITIONS_VIEW => [
                PermissionEntityRegistry::COLISAGE_EXPEDITIONS => PermissionAction::VIEW,
            ],
            self::FLOTTE_LIVREURS_MANAGE => [
                PermissionEntityRegistry::FLOTTE_LIVREURS => PermissionAction::UPDATE,
            ],
            self::FLOTTE_LIVREURS_VIEW => [
                PermissionEntityRegistry::FLOTTE_LIVREURS => PermissionAction::VIEW,
            ],
            self::TRACKING_GPS_MANAGE => [
                PermissionEntityRegistry::TRACKING_GPS => PermissionAction::UPDATE,
            ],
            self::TRACKING_GPS_VIEW => [
                PermissionEntityRegistry::TRACKING_GPS => PermissionAction::VIEW,
            ],
            self::ENTREPOT_INVENTAIRES_MANAGE => [
                PermissionEntityRegistry::ENTREPOT_INVENTAIRES => PermissionAction::UPDATE,
            ],
            self::ENTREPOT_INVENTAIRES_VIEW => [
                PermissionEntityRegistry::ENTREPOT_INVENTAIRES => PermissionAction::VIEW,
            ],
            // Phase 3
            self::TRANSIT_PRESTATAIRES_MANAGE => [
                PermissionEntityRegistry::TRANSIT_PRESTATAIRES => PermissionAction::UPDATE,
            ],
            self::TRANSIT_PRESTATAIRES_VIEW => [
                PermissionEntityRegistry::TRANSIT_PRESTATAIRES => PermissionAction::VIEW,
            ],
            self::FACTURATION_FACTURES_MANAGE => [
                PermissionEntityRegistry::FACTURATION_FACTURES => PermissionAction::UPDATE,
            ],
            self::FACTURATION_FACTURES_VIEW => [
                PermissionEntityRegistry::FACTURATION_FACTURES => PermissionAction::VIEW,
            ],
            self::FINANCE_RETRAITS_MANAGE => [
                PermissionEntityRegistry::FINANCE_RETRAITS => PermissionAction::UPDATE,
            ],
            self::FINANCE_RETRAITS_VIEW => [
                PermissionEntityRegistry::FINANCE_RETRAITS => PermissionAction::VIEW,
            ],
            self::FINANCE_COMPENSATIONS_MANAGE => [
                PermissionEntityRegistry::FINANCE_COMPENSATIONS => PermissionAction::UPDATE,
            ],
            self::FINANCE_COMPENSATIONS_VIEW => [
                PermissionEntityRegistry::FINANCE_COMPENSATIONS => PermissionAction::VIEW,
            ],
            self::LOGISTIQUE_FOURNITURES_MANAGE => [
                PermissionEntityRegistry::LOGISTIQUE_FOURNITURES => PermissionAction::UPDATE,
            ],
            self::LOGISTIQUE_FOURNITURES_VIEW => [
                PermissionEntityRegistry::LOGISTIQUE_FOURNITURES => PermissionAction::VIEW,
            ],
            default => throw new InvalidArgumentException('Politique de permission inconnue : ' . $operation),
        };
    }
}
