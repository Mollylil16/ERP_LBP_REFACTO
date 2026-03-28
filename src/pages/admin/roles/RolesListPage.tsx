import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { rolesService } from '../../../services/roles.service';
import { permissionsService } from '../../../services/permissions.service';
import { Role, Permission, PermissionModule } from '../../../types/roles.types';
import { usePermissions } from '@hooks/usePermissions';
import { PERMISSIONS } from '@constants/permissions';
import { TableSkeleton } from '@components/common/SkeletonLoader';
import { EmptyRolesList, EmptyErrorState } from '@components/common/EmptyState';
import './RolesListPage.css';

const RolesListPage: React.FC = () => {
    const navigate = useNavigate();
    const { hasPermission } = usePermissions();
    const [roles, setRoles] = useState<Role[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        loadRoles();
    }, []);

    const loadRoles = async () => {
        try {
            setLoading(true);
            const data = await rolesService.getAllRoles();
            setRoles(data);
        } catch (err: any) {
            setError(err.message || 'Erreur lors du chargement des rôles');
        } finally {
            setLoading(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!window.confirm('Êtes-vous sûr de vouloir supprimer ce rôle ?')) {
            return;
        }

        try {
            await rolesService.deleteRole(id);
            await loadRoles();
        } catch (err: any) {
            alert(err.message || 'Erreur lors de la suppression du rôle');
        }
    };

    if (loading) {
        return (
            <div className="roles-list-page">
                <h1>Gestion des Rôles</h1>
                <TableSkeleton rows={4} columns={4} />
            </div>
        );
    }

    if (error) {
        return (
            <div className="roles-list-page">
                <h1>Gestion des Rôles</h1>
                <EmptyErrorState
                    title="Erreur de chargement des rôles"
                    description={error}
                    onRetry={loadRoles}
                />
            </div>
        );
    }

    return (
        <div className="roles-list-page">
            <div className="page-header">
                <h1>Gestion des Rôles</h1>
                {hasPermission(PERMISSIONS.USERS.CREATE) && (
                    <button
                        className="btn btn-primary"
                        onClick={() => navigate('/admin/roles/new')}
                    >
                        + Nouveau Rôle
                    </button>
                )}
            </div>

            <div className="roles-grid">
                {roles.length === 0 && (
                    <EmptyRolesList onCreateClick={() => navigate('/admin/roles/new')} />
                )}
                {roles.map((role) => (
                    <div key={role.id} className="role-card">
                        <div className="role-header">
                            <h3>{role.libelle}</h3>
                            <span className={`role-badge ${role.est_actif ? 'active' : 'inactive'}`}>
                                {role.est_actif ? 'Actif' : 'Inactif'}
                            </span>
                        </div>
                        <p className="role-code">{role.code}</p>
                        {role.description && (
                            <p className="role-description">{role.description}</p>
                        )}
                        <div className="role-meta">
                            <span className="niveau">Niveau {role.niveau_hierarchique}</span>
                        </div>
                        <div className="role-actions">
                            {hasPermission(PERMISSIONS.USERS.READ) && (
                                <button
                                    className="btn btn-sm btn-secondary"
                                    onClick={() => navigate(`/admin/roles/${role.id}`)}
                                >
                                    Voir
                                </button>
                            )}
                            {hasPermission(PERMISSIONS.USERS.UPDATE) && (
                                <button
                                    className="btn btn-sm btn-primary"
                                    onClick={() => navigate(`/admin/roles/${role.id}/edit`)}
                                >
                                    Modifier
                                </button>
                            )}
                            {hasPermission(PERMISSIONS.USERS.DELETE) && (
                                <button
                                    className="btn btn-sm btn-danger"
                                    onClick={() => handleDelete(role.id)}
                                >
                                    Supprimer
                                </button>
                            )}
                        </div>
                    </div>
                ))}
            </div>

            {roles.length === 0 && (
                <div className="empty-state">
                    <p>Aucun rôle trouvé</p>
                </div>
            )}
        </div>
    );
};

export default RolesListPage;
