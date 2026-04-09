/**
 * Code rôle effectif pour RBAC : préfère lbp_roles (roleEntity) à la colonne legacy `user.role`.
 */
export function effectiveRoleCode(user: any): string {
  const fromEntity = user?.roleEntity?.code;
  if (fromEntity != null && String(fromEntity).trim() !== '') {
    return String(fromEntity);
  }
  if (typeof user?.role === 'string' && user.role.trim() !== '') {
    return user.role;
  }
  if (user?.role?.code) {
    return String(user.role.code);
  }
  return 'AGENT_EXPLOITATION';
}
