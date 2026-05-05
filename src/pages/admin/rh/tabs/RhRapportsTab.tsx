import React, { useState } from 'react'
import {
  Card, Row, Col, Statistic, Table, Alert, Button, Select, Tabs,
  Tag, Progress, message, Descriptions,
} from 'antd'
import {
  FilePdfOutlined, FileExcelOutlined, DownloadOutlined, WarningOutlined,
} from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import {
  rhService, RhBilanSocial, RhEtatCnps, RhEtatIts,
  RhDeclarationMO, RhRapportHeursSup,
} from '@services/rh.service'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'
import dayjs from 'dayjs'
import * as XLSX from 'exceljs'
import { saveAs } from 'file-saver'

const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR') + ' FCFA'
const pct = (n: number) => `${n} %`

// ── Export Excel ──────────────────────────────────────────────────────────────
async function exportExcel(data: unknown[], headers: string[], nomFichier: string) {
  const wb = new XLSX.Workbook()
  const ws = wb.addWorksheet('Données')
  ws.addRow(headers)
  ;(data as Record<string, unknown>[]).forEach((row) => ws.addRow(Object.values(row)))
  const buf = await wb.xlsx.writeBuffer()
  saveAs(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), `${nomFichier}.xlsx`)
}

// ── Téléchargement PDF depuis le backend ──────────────────────────────────────
function telechargerPdf(url: string, nom: string) {
  const link = document.createElement('a')
  link.href = `/api${url}`
  link.download = nom
  link.click()
}

export const RhRapportsTab: React.FC = () => {
  const { hasPermission } = usePermissions()
  const canExport = hasPermission(PERMISSIONS.RH.RAPPORTS_EXPORT)

  const [annee, setAnnee] = useState(dayjs().year())
  const [periode, setPeriode] = useState(dayjs().format('YYYY-MM'))
  const [activeTab, setActiveTab] = useState('bilan')

  const { data: bilan, isLoading: loadingBilan } = useQuery<RhBilanSocial>({
    queryKey: ['rh-bilan-social', annee],
    queryFn: () => rhService.getBilanSocial(annee),
    enabled: activeTab === 'bilan',
  })

  const { data: cnps, isLoading: loadingCnps } = useQuery<RhEtatCnps>({
    queryKey: ['rh-etat-cnps', periode],
    queryFn: () => rhService.getEtatCnps(periode),
    enabled: activeTab === 'cnps',
  })

  const { data: its, isLoading: loadingIts } = useQuery<RhEtatIts>({
    queryKey: ['rh-etat-its', periode],
    queryFn: () => rhService.getEtatIts(periode),
    enabled: activeTab === 'its',
  })

  const { data: decMO, isLoading: loadingMO } = useQuery<RhDeclarationMO>({
    queryKey: ['rh-declaration-mo', annee],
    queryFn: () => rhService.getDeclarationMO(annee),
    enabled: activeTab === 'mo',
  })

  const { data: hs, isLoading: loadingHs } = useQuery<RhRapportHeursSup>({
    queryKey: ['rh-heures-sup', periode],
    queryFn: () => rhService.getHeuresSup(periode),
    enabled: activeTab === 'hs',
  })

  // Bilan social content
  const bilanContent = (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
        <Select value={annee} onChange={(v: number) => setAnnee(v)} style={{ width: 120 }}>
          {[annee - 2, annee - 1, annee].map((y) => <Select.Option key={y} value={y}>{y}</Select.Option>)}
        </Select>
        {canExport && bilan && (
          <Button
            icon={<FileExcelOutlined />}
            onClick={() => exportExcel(
              bilan.par_departement,
              ['Département', 'Effectif'],
              `bilan-social-${annee}`,
            )}
          >
            Export Excel
          </Button>
        )}
      </div>

      <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
        {[
          { title: 'Effectif total', value: bilan?.effectif_total ?? 0 },
          { title: 'Effectif actif', value: bilan?.effectif_actif ?? 0, color: '#52c41a' },
          { title: 'Embauches', value: bilan?.embauches_annee ?? 0, color: '#1677ff' },
          { title: 'Sorties', value: bilan?.sorties_annee ?? 0, color: '#f5222d' },
        ].map((k) => (
          <Col key={k.title} xs={12} md={6}>
            <Card size="small" loading={loadingBilan}>
              <Statistic title={k.title} value={k.value} valueStyle={k.color ? { color: k.color } : {}} />
            </Card>
          </Col>
        ))}
      </Row>

      <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
        <Col xs={12} md={6}>
          <Card size="small" loading={loadingBilan}>
            <div style={{ marginBottom: 4 }}>Taux d'absentéisme</div>
            <Progress percent={bilan?.taux_absenteisme ?? 0} strokeColor="#fa8c16" />
          </Card>
        </Col>
        <Col xs={12} md={6}>
          <Card size="small" loading={loadingBilan}>
            <div style={{ marginBottom: 4 }}>Taux de turnover</div>
            <Progress percent={bilan?.taux_turnover ?? 0} strokeColor="#f5222d" />
          </Card>
        </Col>
        <Col xs={12} md={6}>
          <Card size="small" loading={loadingBilan}>
            <Statistic title="Masse salariale brute" value={bilan?.masse_salariale_brute ?? 0} suffix="FCFA" />
          </Card>
        </Col>
        <Col xs={12} md={6}>
          <Card size="small" loading={loadingBilan}>
            <Statistic title="Masse salariale nette" value={bilan?.masse_salariale_nette ?? 0} suffix="FCFA" />
          </Card>
        </Col>
      </Row>

      <Row gutter={16}>
        <Col xs={24} md={8}>
          <Card title="Par sexe" size="small">
            {(bilan?.par_sexe ?? []).map((r) => (
              <Row key={r.sexe} justify="space-between" style={{ marginBottom: 4 }}>
                <Tag>{r.sexe === 'M' ? 'Homme' : r.sexe === 'F' ? 'Femme' : r.sexe}</Tag>
                <strong>{r.nb}</strong>
              </Row>
            ))}
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card title="Par type contrat" size="small">
            {(bilan?.par_type_contrat ?? []).map((r) => (
              <Row key={r.type} justify="space-between" style={{ marginBottom: 4 }}>
                <Tag>{r.type}</Tag>
                <strong>{r.nb}</strong>
              </Row>
            ))}
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card title="Par département (top 8)" size="small">
            {(bilan?.par_departement ?? []).slice(0, 8).map((r) => (
              <Row key={r.departement} justify="space-between" style={{ marginBottom: 4 }}>
                <span style={{ fontSize: 12 }}>{r.departement}</span>
                <Tag>{r.nb}</Tag>
              </Row>
            ))}
          </Card>
        </Col>
      </Row>
    </div>
  )

  // CNPS content
  const colonnesCnps = [
    { title: 'Matricule', dataIndex: 'matricule', key: 'matricule', width: 100 },
    { title: 'Nom', dataIndex: 'nom', key: 'nom' },
    { title: 'N° CNPS', dataIndex: 'numero_cnps', key: 'numero_cnps' },
    { title: 'Salaire brut', dataIndex: 'salaire_brut', key: 'salaire_brut', render: (v: number) => fmt(v) },
    { title: 'Retraite sal.', dataIndex: 'cnps_retraite_salarial', key: 'rs', render: (v: number) => fmt(v) },
    { title: 'Retraite pat.', dataIndex: 'cnps_retraite_patronal', key: 'rp', render: (v: number) => fmt(v) },
    { title: 'AT pat.', dataIndex: 'cnps_at_patronal', key: 'at', render: (v: number) => fmt(v) },
    { title: 'Famille pat.', dataIndex: 'cnps_famille_patronal', key: 'fam', render: (v: number) => fmt(v) },
    { title: 'CMU sal.', dataIndex: 'cmu_salarial', key: 'cmu', render: (v: number) => fmt(v) },
    { title: 'Total CNPS', dataIndex: 'total_cnps', key: 'total', render: (v: number) => <strong>{fmt(v)}</strong> },
  ]

  const cnpsContent = (
    <div>
      <div style={{ marginBottom: 12, display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
        <Select value={periode} onChange={(v: string) => setPeriode(v)} style={{ width: 140 }}>
          {Array.from({ length: 12 }, (_, i) => dayjs().subtract(i, 'month').format('YYYY-MM'))
            .map((p) => <Select.Option key={p} value={p}>{p}</Select.Option>)}
        </Select>
        {canExport && cnps && (
          <Button
            icon={<FileExcelOutlined />}
            onClick={() => exportExcel(cnps.lignes, ['Matricule', 'Nom', 'N°CNPS', 'Brut', 'Retraite-S', 'Retraite-P', 'AT', 'Famille', 'CMU-S', 'CMU-P', 'Total'], `etat-cnps-${periode}`)}
          >
            Export Excel
          </Button>
        )}
      </div>
      {cnps && (
        <Card size="small" style={{ marginBottom: 12 }}>
          <Descriptions size="small" column={4}>
            <Descriptions.Item label="Total brut">{fmt(cnps.totaux.salaire_brut ?? 0)}</Descriptions.Item>
            <Descriptions.Item label="Retraite salarial">{fmt(cnps.totaux.cnps_retraite_salarial ?? 0)}</Descriptions.Item>
            <Descriptions.Item label="Retraite patronal">{fmt(cnps.totaux.cnps_retraite_patronal ?? 0)}</Descriptions.Item>
            <Descriptions.Item label="Total CNPS">{fmt(cnps.totaux.total_cnps ?? 0)}</Descriptions.Item>
          </Descriptions>
        </Card>
      )}
      <Table
        dataSource={cnps?.lignes ?? []}
        columns={colonnesCnps}
        rowKey="matricule"
        loading={loadingCnps}
        size="small"
        pagination={{ pageSize: 20 }}
        scroll={{ x: 900 }}
      />
    </div>
  )

  // ITS/DGI
  const colonnesIts = [
    { title: 'Matricule', dataIndex: 'matricule', key: 'matricule', width: 100 },
    { title: 'Nom', dataIndex: 'nom', key: 'nom' },
    { title: 'Salaire brut', dataIndex: 'salaire_brut', key: 'sb', render: (v: number) => fmt(v) },
    { title: 'ITS', dataIndex: 'its', key: 'its', render: (v: number) => fmt(v) },
    { title: 'CN (1,5%)', dataIndex: 'cn', key: 'cn', render: (v: number) => fmt(v) },
    { title: 'Total ITS+CN', dataIndex: 'total_its_cn', key: 'tot', render: (v: number) => <strong>{fmt(v)}</strong> },
  ]

  const itsContent = (
    <div>
      <div style={{ marginBottom: 12, display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
        <Select value={periode} onChange={(v: string) => setPeriode(v)} style={{ width: 140 }}>
          {Array.from({ length: 12 }, (_, i) => dayjs().subtract(i, 'month').format('YYYY-MM'))
            .map((p) => <Select.Option key={p} value={p}>{p}</Select.Option>)}
        </Select>
        {canExport && its && (
          <Button
            icon={<FileExcelOutlined />}
            onClick={() => exportExcel(its.lignes, ['Matricule', 'Nom', 'Brut', 'ITS', 'CN', 'Total'], `etat-its-${periode}`)}
          >
            Export Excel
          </Button>
        )}
      </div>
      {its && (
        <Card size="small" style={{ marginBottom: 12 }}>
          <Descriptions size="small" column={3}>
            <Descriptions.Item label="Total ITS">{fmt(its.total_its)}</Descriptions.Item>
            <Descriptions.Item label="Total CN">{fmt(its.total_cn)}</Descriptions.Item>
            <Descriptions.Item label="Total à reverser">{fmt(its.total_its_cn)}</Descriptions.Item>
          </Descriptions>
        </Card>
      )}
      <Table
        dataSource={its?.lignes ?? []}
        columns={colonnesIts}
        rowKey="matricule"
        loading={loadingIts}
        size="small"
        pagination={{ pageSize: 20 }}
      />
    </div>
  )

  // Déclaration MO
  const colonnesMO = [
    { title: 'Matricule', dataIndex: 'matricule', key: 'matricule', width: 100 },
    { title: 'Nom', key: 'nom', render: (_: unknown, r: RhDeclarationMO['employes'][number]) => `${r.nom} ${r.prenoms}` },
    { title: 'Sexe', dataIndex: 'sexe', key: 'sexe', render: (v: string | null) => v ?? '—' },
    { title: 'Nationalité', dataIndex: 'nationalite', key: 'nat', render: (v: string | null) => v ?? '—' },
    { title: 'Embauche', dataIndex: 'date_embauche', key: 'emb', render: (v: string) => dayjs(v).format('DD/MM/YYYY') },
    { title: 'Type contrat', dataIndex: 'type_contrat_actuel', key: 'type' },
    { title: 'Poste', dataIndex: 'intitule_poste', key: 'poste', render: (v: string | null) => v ?? '—' },
    { title: 'Département', dataIndex: 'departement', key: 'dept', render: (v: string | null) => v ?? '—' },
    { title: 'Agence', dataIndex: 'agence_nom', key: 'agence', render: (v: string | null) => v ?? '—' },
  ]

  const moContent = (
    <div>
      <div style={{ marginBottom: 12, display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
        <Select value={annee} onChange={(v: number) => setAnnee(v)} style={{ width: 120 }}>
          {[annee - 1, annee].map((y) => <Select.Option key={y} value={y}>{y}</Select.Option>)}
        </Select>
        {canExport && decMO && (
          <Button
            icon={<FileExcelOutlined />}
            onClick={() => exportExcel(
              decMO.employes,
              ['Matricule', 'Nom', 'Prenoms', 'Sexe', 'Nationalite', 'Date embauche', 'Date sortie', 'Type contrat', 'Poste', 'Categorie', 'Departement', 'CNPS', 'Agence'],
              `declaration-main-oeuvre-${annee}`,
            )}
          >
            Export Excel
          </Button>
        )}
      </div>
      {decMO?.alerte_delai && (
        <Alert
          type="warning"
          icon={<WarningOutlined />}
          showIcon
          message={decMO.alerte_delai}
          style={{ marginBottom: 12 }}
        />
      )}
      {decMO && (
        <Card size="small" style={{ marginBottom: 12 }}>
          <Statistic title={`Employés déclarés (${annee})`} value={decMO.nb_employes} />
        </Card>
      )}
      <Table
        dataSource={decMO?.employes ?? []}
        columns={colonnesMO}
        rowKey="matricule"
        loading={loadingMO}
        size="small"
        pagination={{ pageSize: 20 }}
        scroll={{ x: 900 }}
      />
    </div>
  )

  // Heures sup
  const hsContent = (
    <div>
      <div style={{ marginBottom: 12, display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
        <Select value={periode} onChange={(v: string) => setPeriode(v)} style={{ width: 140 }}>
          {Array.from({ length: 12 }, (_, i) => dayjs().subtract(i, 'month').format('YYYY-MM'))
            .map((p) => <Select.Option key={p} value={p}>{p}</Select.Option>)}
        </Select>
        {canExport && hs && (
          <Button
            icon={<FileExcelOutlined />}
            onClick={() => exportExcel(hs.lignes, ['Matricule', 'Nom', 'Montant HS'], `heures-sup-${periode}`)}
          >
            Export Excel
          </Button>
        )}
      </div>
      {hs && (
        <Card size="small" style={{ marginBottom: 12 }}>
          <Statistic title="Total heures supplémentaires payées" value={hs.total_montant} suffix="FCFA" />
        </Card>
      )}
      <Table
        dataSource={hs?.lignes ?? []}
        columns={[
          { title: 'Matricule', dataIndex: 'matricule', key: 'matricule' },
          { title: 'Nom', dataIndex: 'nom', key: 'nom' },
          { title: 'Montant HS', dataIndex: 'montant_hs', key: 'montant', render: (v: number) => fmt(v) },
        ]}
        rowKey="matricule"
        loading={loadingHs}
        size="small"
      />
    </div>
  )

  return (
    <div>
      <Tabs
        activeKey={activeTab}
        onChange={setActiveTab}
        items={[
          { key: 'bilan', label: 'Bilan social', children: bilanContent },
          { key: 'cnps', label: 'État CNPS', children: cnpsContent },
          { key: 'its', label: 'ITS / DGI', children: itsContent },
          { key: 'mo', label: 'Déclaration MO', children: moContent },
          { key: 'hs', label: 'Heures sup.', children: hsContent },
          {
            key: 'registre',
            label: 'Registre employeur',
            children: (
              <Card title="Registre employeur 3 fascicules (Décret 2024-902 Art.9)">
                <Alert type="info" showIcon message="Ces 3 fascicules constituent le registre légal de l'employeur. Ils doivent être conservés 5 ans et présentés à l'inspecteur du travail sur demande." style={{ marginBottom: 16 }} />
                <Row gutter={16}>
                  {[
                    { key: 'a', label: 'Fascicule A — Registre du personnel', desc: 'Liste nominative de tous les employés', file: 'registre-personnel' },
                    { key: 'b', label: 'Fascicule B — Registre des congés', desc: 'Suivi des congés payés (Art. 27 CDT CI)', file: 'registre-conges' },
                    { key: 'c', label: 'Fascicule C — Registre AT/Maladies', desc: 'Accidents du travail et maladies professionnelles', file: 'registre-at' },
                  ].map(f => (
                    <Col xs={24} md={8} key={f.key}>
                      <Card size="small" style={{ textAlign: 'center', height: '100%' }}>
                        <FilePdfOutlined style={{ fontSize: 40, color: '#cf1322', marginBottom: 8 }} />
                        <div style={{ fontWeight: 600, marginBottom: 4 }}>{f.label}</div>
                        <div style={{ color: '#888', fontSize: 12, marginBottom: 12 }}>{f.desc}</div>
                        <Button
                          type="primary"
                          danger
                          icon={<DownloadOutlined />}
                          onClick={() => telechargerPdf(`/rh/rapports/registre/${f.key}/pdf`, `${f.file}-${new Date().getFullYear()}.pdf`)}
                        >
                          Télécharger PDF
                        </Button>
                      </Card>
                    </Col>
                  ))}
                </Row>
              </Card>
            ),
          },
        ]}
      />
    </div>
  )
}
