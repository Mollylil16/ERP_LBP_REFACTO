import React, { useMemo, useState } from 'react'
import { Button, Card, Col, Input, Progress, Row, Statistic, Table, Tag, Typography, message } from 'antd'
import { useQuery } from '@tanstack/react-query'
import { FileExcelOutlined, SearchOutlined } from '@ant-design/icons'
import type { Dayjs } from 'dayjs'
import { supervisionService } from '@services/supervision.service'
import { exportSupervisionPerformanceExcel } from '@utils/supervisionExcelExport'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'

const { Text } = Typography

type UtilisateurRow = {
  id: number
  username: string
  nom_complet: string | null
  role_code: string
  agence_nom: string | null
  colis_saisis: number
  factures_saisies: number
  operations_total: number
  indice_activite: number
  niveau_activite: string
}

type ParAgenceRole = {
  id_agence: number
  nom_agence: string
  role_code: string
  n: number
}

const NIVEAU_COLOR: Record<string, string> = {
  'élevé': 'green',
  'modéré': 'gold',
  'faible': 'default',
}

const PROGRESS_STROKE: Record<string, string> = {
  'élevé': '#52c41a',
  'modéré': '#faad14',
  'faible': '#d9d9d9',
}

export const SupervisionPerformanceTab: React.FC<{ range: [Dayjs, Dayjs] }> = ({ range }) => {
  const { hasPermission } = usePermissions()
  const canExport = hasPermission(PERMISSIONS.SUPERVISION.DASHBOARD_READ)
  const debut = range[0].format('YYYY-MM-DD')
  const fin = range[1].format('YYYY-MM-DD')
  const [search, setSearch] = useState('')

  const { data: prod, isLoading: l1 } = useQuery({
    queryKey: ['supervision', 'user-productivity', debut, fin],
    queryFn: () => supervisionService.getUserProductivity(debut, fin),
  })

  const { data: headcount, isLoading: l2 } = useQuery({
    queryKey: ['supervision', 'performance-agents'],
    queryFn: () => supervisionService.getPerformanceAgents(),
  })

  const utilisateurs: UtilisateurRow[] = prod?.utilisateurs ?? []

  const filteredUsers = useMemo(() => {
    if (!search.trim()) return utilisateurs
    const q = search.toLowerCase()
    return utilisateurs.filter(
      (u) =>
        u.username.toLowerCase().includes(q) ||
        (u.nom_complet?.toLowerCase().includes(q) ?? false) ||
        (u.agence_nom?.toLowerCase().includes(q) ?? false) ||
        u.role_code.toLowerCase().includes(q),
    )
  }, [utilisateurs, search])

  const stats = useMemo(() => ({
    eleve: utilisateurs.filter((u) => u.niveau_activite === 'élevé').length,
    modere: utilisateurs.filter((u) => u.niveau_activite === 'modéré').length,
    faible: utilisateurs.filter((u) => u.niveau_activite === 'faible').length,
    totalOps: utilisateurs.reduce((s, u) => s + (u.operations_total ?? 0), 0),
  }), [utilisateurs])

  const onExportExcel = async () => {
    if (!canExport) return
    const par = (headcount as { par_agence_role?: ParAgenceRole[] } | null | undefined)?.par_agence_role
    try {
      await exportSupervisionPerformanceExcel({
        range,
        utilisateurs: prod?.utilisateurs,
        parAgenceRole: Array.isArray(par) ? (par as ParAgenceRole[]) : [],
      })
      message.success('Fichier Excel généré')
    } catch {
      message.error('Export Excel impossible')
    }
  }

  return (
    <div>
      {/* Résumé niveaux */}
      {utilisateurs.length > 0 && (
        <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
          <Col xs={12} sm={6}>
            <Card size="small">
              <Statistic
                title="Niveau élevé"
                value={stats.eleve}
                valueStyle={{ color: '#3f8600' }}
                suffix={<Text type="secondary" style={{ fontSize: 12 }}>agents</Text>}
              />
            </Card>
          </Col>
          <Col xs={12} sm={6}>
            <Card size="small">
              <Statistic
                title="Niveau modéré"
                value={stats.modere}
                valueStyle={{ color: '#d48806' }}
                suffix={<Text type="secondary" style={{ fontSize: 12 }}>agents</Text>}
              />
            </Card>
          </Col>
          <Col xs={12} sm={6}>
            <Card size="small">
              <Statistic
                title="Niveau faible"
                value={stats.faible}
                suffix={<Text type="secondary" style={{ fontSize: 12 }}>agents</Text>}
              />
            </Card>
          </Col>
          <Col xs={12} sm={6}>
            <Card size="small">
              <Statistic
                title="Opérations totales"
                value={stats.totalOps}
                suffix={<Text type="secondary" style={{ fontSize: 12 }}>sur la période</Text>}
              />
            </Card>
          </Col>
        </Row>
      )}

      {/* Tableau productivité */}
      <Card
        size="small"
        title="Productivité opérationnelle (colis + factures saisis)"
        extra={
          <Row gutter={8} align="middle">
            <Col>
              <Input
                size="small"
                placeholder="Rechercher…"
                prefix={<SearchOutlined />}
                value={search}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSearch(e.target.value)}
                allowClear
                style={{ width: 180 }}
              />
            </Col>
            {canExport && (
              <Col>
                <Button size="small" icon={<FileExcelOutlined />} onClick={onExportExcel}>
                  Excel
                </Button>
              </Col>
            )}
          </Row>
        }
        style={{ marginBottom: 16 }}
      >
        <Text type="secondary" style={{ display: 'block', marginBottom: 12 }}>
          Indice basé sur le volume d'opérations rattachées au compte sur la période — outil
          d'animation, pas de notation RH contractuelle.
        </Text>
        <Table
          size="small"
          loading={l1}
          rowKey="id"
          dataSource={filteredUsers}
          pagination={{ pageSize: 15, showSizeChanger: true }}
          scroll={{ x: true }}
          rowClassName={(r: UtilisateurRow) =>
            r.niveau_activite === 'élevé' ? 'lbp-row-performance-eleve' : ''
          }
          columns={[
            { title: 'Utilisateur', dataIndex: 'username', width: 130, sorter: (a: UtilisateurRow, b: UtilisateurRow) => a.username.localeCompare(b.username) },
            { title: 'Nom complet', dataIndex: 'nom_complet', render: (v: string | null) => v ?? '—' },
            { title: 'Rôle', dataIndex: 'role_code', width: 140 },
            { title: 'Agence', dataIndex: 'agence_nom', render: (v: string | null) => v ?? '—' },
            {
              title: 'Colis',
              dataIndex: 'colis_saisis',
              width: 70,
              sorter: (a: UtilisateurRow, b: UtilisateurRow) => a.colis_saisis - b.colis_saisis,
            },
            {
              title: 'Factures',
              dataIndex: 'factures_saisies',
              width: 80,
              sorter: (a: UtilisateurRow, b: UtilisateurRow) => a.factures_saisies - b.factures_saisies,
            },
            {
              title: 'Total ops.',
              dataIndex: 'operations_total',
              width: 90,
              defaultSortOrder: 'descend' as const,
              sorter: (a: UtilisateurRow, b: UtilisateurRow) => a.operations_total - b.operations_total,
            },
            {
              title: 'Indice activité',
              key: 'indice',
              width: 160,
              sorter: (a: UtilisateurRow, b: UtilisateurRow) => a.indice_activite - b.indice_activite,
              render: (_: unknown, r: UtilisateurRow) => (
                <Progress
                  percent={r.indice_activite}
                  size="small"
                  strokeColor={PROGRESS_STROKE[r.niveau_activite] ?? '#d9d9d9'}
                  format={(p: number | undefined) => `${p ?? 0}`}
                />
              ),
            },
            {
              title: 'Niveau',
              dataIndex: 'niveau_activite',
              width: 90,
              filters: [
                { text: 'Élevé', value: 'élevé' },
                { text: 'Modéré', value: 'modéré' },
                { text: 'Faible', value: 'faible' },
              ],
              onFilter: (v: unknown, r: UtilisateurRow) => r.niveau_activite === v,
              render: (t: string) => <Tag color={NIVEAU_COLOR[t] ?? 'default'}>{t}</Tag>,
            },
          ]}
        />
      </Card>

      {/* Effectifs par agence et rôle */}
      <Card size="small" title="Effectifs par agence et rôle" loading={l2}>
        <Table
          size="small"
          rowKey={(r: ParAgenceRole) => `${r.id_agence}-${r.role_code}`}
          dataSource={(headcount as { par_agence_role?: ParAgenceRole[] } | undefined)?.par_agence_role ?? []}
          pagination={false}
          scroll={{ x: true }}
          columns={[
            {
              title: 'Agence',
              dataIndex: 'nom_agence',
              sorter: (a: ParAgenceRole, b: ParAgenceRole) => a.nom_agence.localeCompare(b.nom_agence),
            },
            { title: 'Rôle', dataIndex: 'role_code' },
            {
              title: 'Effectif',
              dataIndex: 'n',
              width: 90,
              sorter: (a: ParAgenceRole, b: ParAgenceRole) => Number(a.n) - Number(b.n),
              render: (v: number) => Number(v),
            },
          ]}
        />
      </Card>
    </div>
  )
}
