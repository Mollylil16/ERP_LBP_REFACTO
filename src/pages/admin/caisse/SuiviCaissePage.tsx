/**
 * Page de suivi de caisse avec sections APPRO, DÉCAISSEMENT, ENTREES, RAPPORT
 */

import React from 'react'
import { useSearchParams } from 'react-router-dom'
import { Tabs, Button, Space, Card, Select, Modal, InputNumber, message, Typography, Alert, Row, Col, Statistic, Divider, Tooltip, Tag } from 'antd'
import {
  WalletOutlined,
  ArrowUpOutlined,
  ArrowDownOutlined,
  DollarOutlined,
  FileTextOutlined,
  PlusOutlined,
  CheckCircleOutlined,
} from '@ant-design/icons'
import { ApproForm } from '@components/caisse/ApproForm'
import { DecaissementForm } from '@components/caisse/DecaissementForm'
import { EntreeCaisseForm } from '@components/caisse/EntreeCaisseForm'
import { MouvementsCaisseList } from '@components/caisse/MouvementsCaisseList'
import { RapportGrandesLignes } from '@components/caisse/RapportGrandesLignes'
import { WithPermission } from '@components/common/WithPermission'
import { TracedActionButton } from '@components/audit/TracedActionButton'
import { PERMISSIONS } from '@constants/permissions'
import { usePermissions } from '@hooks/usePermissions'
import { RecettesDuJourCard } from '@components/paiements/RecettesDuJourCard'
import { EncaissementRapideCard } from '@components/caisse/EncaissementRapideCard'
import { PointsSoumisCaisseTab } from '@components/caisse/PointsSoumisCaisseTab'
import { useCaisses, useSoldeCaisse } from '@hooks/useCaisse'
import { caisseService } from '@services/caisse.service'
import { useQuery, useMutation } from '@tanstack/react-query'
import { useAuth } from '@hooks/useAuth'
import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'

export const SuiviCaissePage: React.FC = () => {
  const { user } = useAuth()
  const [searchParams] = useSearchParams()
  const idCaisseParam = searchParams.get('id_caisse')
  const { hasPermission } = usePermissions()
  const roleCodePrincipal = String(user?.role?.code ?? '').toUpperCase()
  const canValidatePointsJournaliers = hasPermission(
    PERMISSIONS.EXPLOITATION.POINTS_VALIDATE,
  )
  const canSeeRecettesDuJour =
    hasPermission(PERMISSIONS.CAISSE.VIEW) ||
    hasPermission(PERMISSIONS.PAIEMENTS.READ) ||
    hasPermission(PERMISSIONS.EXPLOITATION.CREDITS_MANAGE)

  const [activeTab, setActiveTab] = React.useState('appro')
  const [approFormVisible, setApproFormVisible] = React.useState(false)
  const [decaissementFormVisible, setDecaissementFormVisible] = React.useState(false)
  const [entreeFormVisible, setEntreeFormVisible] = React.useState(false)
  const [entreeType, setEntreeType] = React.useState<
    'ENTREE_CHEQUE' | 'ENTREE_ESPECE' | 'ENTREE_VIREMENT'
  >('ENTREE_ESPECE')
  const [refreshKey, setRefreshKey] = React.useState(0)
  const [selectedCaisseId, setSelectedCaisseId] = React.useState<number | undefined>()
  const [openAmount, setOpenAmount] = React.useState<number | null>(null)
  const [closeAmount, setCloseAmount] = React.useState<number | null>(null)
  const [openModalVisible, setOpenModalVisible] = React.useState(false)
  const [closeModalVisible, setCloseModalVisible] = React.useState(false)

  const { data: caisses, isLoading: caissesLoading, refetch: refetchCaisses } = useCaisses()

  // Pré-sélection depuis l’URL (ex. vue consolidée) : ?id_caisse=3
  React.useEffect(() => {
    if (!caisses?.length || !idCaisseParam) return
    const n = Number(idCaisseParam)
    if (Number.isFinite(n) && caisses.some((c) => c.id === n)) {
      setSelectedCaisseId(n)
    }
  }, [caisses, idCaisseParam])

  // Première caisse : priorité à celle sur laquelle les opérations sont autorisées (caisse principale siège pour la caissière).
  React.useEffect(() => {
    if (!caisses?.length || selectedCaisseId != null) return
    if (idCaisseParam) return
    const op = caisses.find((c) => c.peut_operer === true)
    setSelectedCaisseId((op ?? caisses[0]).id)
  }, [caisses, selectedCaisseId, idCaisseParam])

  const selectedCaisse = caisses?.find(c => c.id === selectedCaisseId) || caisses?.[0]
  const idCaisse = selectedCaisseId || selectedCaisse?.id || 1
  const canOperateOnSelectedCaisse = selectedCaisse?.peut_operer !== false

  const { data: soldeActuelRealtime, refetch: refetchSolde } = useSoldeCaisse(idCaisse)
  const soldeActuel = soldeActuelRealtime ?? selectedCaisse?.solde_actuel ?? 0
  const seuilAlerte = Number(selectedCaisse?.seuil_alerte ?? 0)
  const isSoldeSousSeuil = seuilAlerte > 0 && soldeActuel < seuilAlerte

  const { data: activeSession, refetch: refetchSession } = useQuery({
    queryKey: ['caisse-active-session', idCaisse],
    queryFn: () => caisseService.getActiveSession(idCaisse),
    enabled: Boolean(idCaisse),
    // Important: un autre utilisateur peut ouvrir/fermer la session.
    // Sans refresh, l'écran d'un chef d'agence reste bloqué sur "fermée".
    refetchInterval: 10_000,
    refetchOnWindowFocus: true,
  })

  const { data: pointDuJour, isLoading: pointLoading, refetch: refetchPoint } = useQuery({
    queryKey: ['caisse-point', idCaisse],
    queryFn: () => caisseService.getPointCaisse(undefined, idCaisse),
    enabled: Boolean(idCaisse) && hasPermission(PERMISSIONS.CAISSE.VIEW),
    refetchInterval: 30000,
  })

  const exportPointPdf = React.useCallback(() => {
    try {
      const doc = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'a4' })
      const title = 'Point de caisse (journée)'
      const caisseLabel = selectedCaisse?.libelle || selectedCaisse?.code || `#${idCaisse}`
      const dateLabel = new Date().toLocaleDateString('fr-FR')

      doc.setFontSize(14)
      doc.text(title, 40, 40)
      doc.setFontSize(10)
      doc.text(`Caisse: ${caisseLabel} — Date: ${dateLabel}`, 40, 60)

      const entrees = Number(pointDuJour?.entrees ?? 0)
      const sorties = Number(pointDuJour?.sorties ?? 0)
      const soldeNet = Number(entrees) - Number(sorties)

      autoTable(doc, {
        startY: 90,
        head: [['Indicateur', 'Valeur']],
        body: [
          ['Entrées (jour)', `${entrees.toLocaleString('fr-FR')} FCFA`],
          ['Sorties (jour)', `${sorties.toLocaleString('fr-FR')} FCFA`],
          ['Solde net (jour)', `${soldeNet.toLocaleString('fr-FR')} FCFA`],
          ['Solde actuel', `${Number(soldeActuel ?? 0).toLocaleString('fr-FR')} FCFA`],
          [
            'Seuil alerte',
            seuilAlerte > 0 ? `${seuilAlerte.toLocaleString('fr-FR')} FCFA` : '—',
          ],
        ],
        styles: { fontSize: 10, cellPadding: 6 },
        headStyles: { fillColor: [22, 119, 255] },
      })

      doc.save(`point-caisse_${idCaisse}_${new Date().toISOString().slice(0, 10)}.pdf`)
    } catch {
      message.error('Export PDF impossible')
    }
  }, [selectedCaisse, idCaisse, pointDuJour, soldeActuel, seuilAlerte])

  const { data: sessionHistory, isLoading: sessionHistoryLoading, refetch: refetchSessionHistory } = useQuery({
    queryKey: ['caisse-session-history', idCaisse],
    queryFn: () => caisseService.getSessionHistory(idCaisse, 20),
    enabled: Boolean(idCaisse) && hasPermission(PERMISSIONS.CAISSE.VIEW),
  })

  const openSessionMutation = useMutation({
    mutationFn: (amount: number) =>
      caisseService.openSession({ id_caisse: idCaisse, solde_ouverture_reel: amount }),
    onSuccess: () => {
      message.success('Session de caisse ouverte avec succes.');
      setOpenModalVisible(false);
      setOpenAmount(null);
      refetchSession();
      refetchSessionHistory();
    },
    onError: (err: any) => message.error(err.message || "Erreur lors de l'ouverture de session"),
  })

  const closeSessionMutation = useMutation({
    mutationFn: (amount: number) =>
      caisseService.closeSession(activeSession.id, { solde_fermeture_reel: amount }),
    onSuccess: () => {
      message.success('Session de caisse cloturee avec succes.');
      setCloseModalVisible(false);
      setCloseAmount(null);
      refetchSession();
      refetchSolde();
      refetchCaisses();
      refetchSessionHistory();
      refetchPoint();
    },
    onError: (err: any) => message.error(err.message || 'Erreur lors de la cloture de session'),
  })

  const isSessionOpen = Boolean(activeSession?.id)

  const requireOpenSession = (action: () => void) => {
    if (!canOperateOnSelectedCaisse) {
      message.warning(
        'Cette caisse est en consultation seule. Sélectionnez la caisse principale siège pour enregistrer des opérations.',
      )
      return
    }
    if (!isSessionOpen) {
      message.warning('Veuillez ouvrir la caisse (session) avant d’enregistrer des opérations.')
      setOpenModalVisible(true)
      return
    }
    action()
  }

  const handleSuccess = () => {
    setApproFormVisible(false)
    setDecaissementFormVisible(false)
    setEntreeFormVisible(false)
    setRefreshKey((prev) => prev + 1) // Force le rechargement des listes
    refetchSolde()
    refetchCaisses()
    refetchPoint()
  }

  const handleOpenEntreeForm = (type: 'ENTREE_CHEQUE' | 'ENTREE_ESPECE' | 'ENTREE_VIREMENT') => {
    setEntreeType(type)
    setEntreeFormVisible(true)
  }

  // Items pour les Tabs imbriqués (entrées)
  const entreesTabItems = [
    {
      key: 'espece',
      label: 'Espèce',
      children: (
        <MouvementsCaisseList
          key={`entree-espece-${refreshKey}`}
          type="ENTREE_ESPECE"
          idCaisse={idCaisse}
          operationsEnabled={canOperateOnSelectedCaisse}
        />
      ),
    },
    {
      key: 'cheque',
      label: 'Chèque',
      children: (
        <MouvementsCaisseList
          key={`entree-cheque-${refreshKey}`}
          type="ENTREE_CHEQUE"
          idCaisse={idCaisse}
          operationsEnabled={canOperateOnSelectedCaisse}
        />
      ),
    },
    {
      key: 'virement',
      label: 'Virement',
      children: (
        <MouvementsCaisseList
          key={`entree-virement-${refreshKey}`}
          type="ENTREE_VIREMENT"
          idCaisse={idCaisse}
          operationsEnabled={canOperateOnSelectedCaisse}
        />
      ),
    },
  ]

  // Items pour les Tabs principaux
  const mainTabItems = [
    {
      key: 'appro',
      label: (
        <span>
          <ArrowUpOutlined /> APPRO (Approvisionnement)
        </span>
      ),
      children: (
        <Space direction="vertical" style={{ width: '100%' }} size="middle">
          <div>
            <WithPermission permission={PERMISSIONS.CAISSE.OPERATIONS}>
              <Button
                type="primary"
                icon={<PlusOutlined />}
                disabled={!canOperateOnSelectedCaisse || !isSessionOpen}
                onClick={() => requireOpenSession(() => setApproFormVisible(true))}
              >
                Nouvel Approvisionnement
              </Button>
            </WithPermission>
            {!isSessionOpen ? (
              <Alert
                style={{ marginTop: 12 }}
                type="warning"
                showIcon
                message="Session fermée"
                description="Ouvrez la caisse pour enregistrer des approvisionnements, décaissements et entrées."
              />
            ) : null}
          </div>

          <MouvementsCaisseList
            key={`appro-${refreshKey}`}
            type="APPRO"
            idCaisse={idCaisse}
            operationsEnabled={canOperateOnSelectedCaisse}
          />
        </Space>
      ),
    },
    {
      key: 'decaissement',
      label: (
        <span>
          <ArrowDownOutlined /> DÉCAISSEMENT
        </span>
      ),
      children: (
        <Space direction="vertical" style={{ width: '100%' }} size="middle">
          <div>
            <WithPermission permission={PERMISSIONS.CAISSE.OPERATIONS}>
              <Button
                type="primary"
                danger
                icon={<PlusOutlined />}
                disabled={!canOperateOnSelectedCaisse || !isSessionOpen}
                onClick={() => requireOpenSession(() => setDecaissementFormVisible(true))}
              >
                Nouveau Décaissement
              </Button>
            </WithPermission>
            {!isSessionOpen ? (
              <Alert
                style={{ marginTop: 12 }}
                type="warning"
                showIcon
                message="Session fermée"
                description="Ouvrez la caisse pour enregistrer des décaissements."
              />
            ) : null}
          </div>

          <MouvementsCaisseList
            key={`decaissement-${refreshKey}`}
            type="DECAISSEMENT"
            idCaisse={idCaisse}
            operationsEnabled={canOperateOnSelectedCaisse}
          />
        </Space>
      ),
    },
    {
      key: 'entrees',
      label: (
        <span>
          <DollarOutlined /> ENTRÉES DE CAISSE
        </span>
      ),
      children: (
        <Space direction="vertical" style={{ width: '100%' }} size="middle">
          <div>
            <WithPermission permission={PERMISSIONS.CAISSE.OPERATIONS}>
              <Space wrap>
                <Button
                  type="primary"
                  icon={<PlusOutlined />}
                  disabled={!canOperateOnSelectedCaisse || !isSessionOpen}
                  onClick={() => requireOpenSession(() => handleOpenEntreeForm('ENTREE_ESPECE'))}
                >
                  Entrée Espèce
                </Button>
                <Button
                  type="primary"
                  icon={<PlusOutlined />}
                  disabled={!canOperateOnSelectedCaisse || !isSessionOpen}
                  onClick={() => requireOpenSession(() => handleOpenEntreeForm('ENTREE_CHEQUE'))}
                >
                  Entrée Chèque
                </Button>
                <Button
                  type="primary"
                  icon={<PlusOutlined />}
                  disabled={!canOperateOnSelectedCaisse || !isSessionOpen}
                  onClick={() => requireOpenSession(() => handleOpenEntreeForm('ENTREE_VIREMENT'))}
                >
                  Entrée Virement
                </Button>
              </Space>
            </WithPermission>
            {!isSessionOpen ? (
              <Alert
                style={{ marginTop: 12 }}
                type="warning"
                showIcon
                message="Session fermée"
                description="Ouvrez la caisse pour enregistrer des entrées."
              />
            ) : null}
          </div>

          <Tabs size="small" type="card" items={entreesTabItems} />
        </Space>
      ),
    },
    ...(canValidatePointsJournaliers
      ? [
          {
            key: 'points-soumis',
            label: (
              <span>
                <CheckCircleOutlined /> Points soumis
              </span>
            ),
            children: (
              <PointsSoumisCaisseTab
                idAgence={selectedCaisse?.id_agence}
                refreshKey={refreshKey}
              />
            ),
          },
        ]
      : []),
    {
      key: 'rapport',
      label: (
        <span>
          <FileTextOutlined /> RAPPORT GRANDES LIGNES
        </span>
      ),
      children: <RapportGrandesLignes idCaisse={idCaisse} />,
    },
    {
      key: 'sessions',
      label: <span><WalletOutlined /> Sessions</span>,
      children: (
        <Card>
          <Space direction="vertical" style={{ width: '100%' }}>
            <Space style={{ justifyContent: 'space-between', width: '100%' }} wrap>
              <Typography.Text strong>Historique des sessions (20 dernières)</Typography.Text>
              <Button onClick={() => refetchSessionHistory()} loading={sessionHistoryLoading}>
                Actualiser
              </Button>
            </Space>
            {Array.isArray(sessionHistory) && sessionHistory.length > 0 ? (
              <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                  <thead>
                    <tr>
                      <th style={{ textAlign: 'left', padding: 8, borderBottom: '1px solid #f0f0f0' }}>Ouverture</th>
                      <th style={{ textAlign: 'left', padding: 8, borderBottom: '1px solid #f0f0f0' }}>Fermeture</th>
                      <th style={{ textAlign: 'right', padding: 8, borderBottom: '1px solid #f0f0f0' }}>Solde ouverture</th>
                      <th style={{ textAlign: 'right', padding: 8, borderBottom: '1px solid #f0f0f0' }}>Solde fermeture</th>
                      <th style={{ textAlign: 'left', padding: 8, borderBottom: '1px solid #f0f0f0' }}>Statut</th>
                    </tr>
                  </thead>
                  <tbody>
                    {sessionHistory.map((s: any) => (
                      <tr key={s.id}>
                        <td style={{ padding: 8, borderBottom: '1px solid #fafafa' }}>{s.opened_at ?? '—'}</td>
                        <td style={{ padding: 8, borderBottom: '1px solid #fafafa' }}>{s.closed_at ?? '—'}</td>
                        <td style={{ padding: 8, borderBottom: '1px solid #fafafa', textAlign: 'right' }}>
                          {(s.solde_ouverture_reel ?? s.solde_ouverture ?? 0).toLocaleString('fr-FR')} FCFA
                        </td>
                        <td style={{ padding: 8, borderBottom: '1px solid #fafafa', textAlign: 'right' }}>
                          {s.solde_fermeture_reel != null ? `${Number(s.solde_fermeture_reel).toLocaleString('fr-FR')} FCFA` : '—'}
                        </td>
                        <td style={{ padding: 8, borderBottom: '1px solid #fafafa' }}>
                          <Tag color={s.closed_at ? 'default' : 'green'}>{s.closed_at ? 'Clôturée' : 'Ouverte'}</Tag>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <Typography.Text type="secondary">Aucune session trouvée.</Typography.Text>
            )}
          </Space>
        </Card>
      ),
    },
  ]

  return (
    <div style={{ padding: 24 }}>
      <Card>
        <Space direction="vertical" style={{ width: '100%' }} size="large">
          {roleCodePrincipal === 'CAISSIER' && caisses && caisses.length > 1 ? (
            <Alert
              type="info"
              showIcon
              message="Toutes les agences"
              description="Sélectionnez la caisse dans la liste : le solde et le point du jour s’appliquent à l’agence choisie. Vous pouvez consulter chaque montant (consultation) ; les opérations d’encaissement en session restent en principe sur la caisse principale (hub) lorsque celle-ci est autorisée."
            />
          ) : null}
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <h1>
              <WalletOutlined /> Suivi Caisse
            </h1>
            {caisses && caisses.length > 0 && (
              <Card size="small" style={{ backgroundColor: '#f0f2f5' }}>
                <Space wrap>
                  <strong>Caisse:</strong>
                  <Select
                    value={idCaisse}
                    onChange={(val: number) => setSelectedCaisseId(val)}
                    style={{ minWidth: 200 }}
                    options={caisses.map((c) => ({
                      label:
                        (c.libelle || c.code) +
                        (c.peut_operer === false ? ' (consultation)' : ''),
                      value: c.id,
                    }))}
                  />
                  <strong>Solde actuel:</strong>{' '}
                  <span style={{ color: '#52c41a', fontWeight: 'bold' }}>
                    {soldeActuel.toLocaleString('fr-FR')} FCFA
                  </span>
                  <Typography.Text type={activeSession?.id ? 'success' : 'warning'}>
                    {activeSession?.id ? 'Session: OUVERTE' : 'Session: FERMEE'}
                  </Typography.Text>
                  <WithPermission permission={PERMISSIONS.CAISSE.OPERATIONS}>
                    {!activeSession?.id ? (
                      <Button
                        type="primary"
                        disabled={!canOperateOnSelectedCaisse}
                        onClick={() => setOpenModalVisible(true)}
                      >
                        Ouvrir caisse
                      </Button>
                    ) : (
                      <TracedActionButton
                        danger
                        disabled={!canOperateOnSelectedCaisse}
                        onClick={() => setCloseModalVisible(true)}
                        traceHint="La clôture de session est enregistrée dans le journal d’audit métier et dans l’historique caisse."
                      >
                        Cloturer caisse
                      </TracedActionButton>
                    )}
                  </WithPermission>
                </Space>
              </Card>
            )}
          </div>

          <Row gutter={[16, 16]}>
            <Col xs={24} md={8}>
              <Card size="small">
                <Statistic
                  title="Solde actuel"
                  value={soldeActuel}
                  precision={0}
                  suffix="FCFA"
                  valueStyle={{ color: soldeActuel >= 0 ? '#52c41a' : '#ff4d4f' }}
                  loading={caissesLoading}
                />
              </Card>
            </Col>
            <Col xs={24} md={8}>
              <Card size="small">
                <Statistic
                  title="Entrées (jour)"
                  value={pointDuJour?.entrees ?? 0}
                  precision={0}
                  suffix="FCFA"
                  valueStyle={{ color: '#1677ff' }}
                  loading={pointLoading}
                />
              </Card>
            </Col>
            <Col xs={24} md={8}>
              <Card size="small">
                <Statistic
                  title="Sorties (jour)"
                  value={pointDuJour?.sorties ?? 0}
                  precision={0}
                  suffix="FCFA"
                  valueStyle={{ color: '#ff4d4f' }}
                  loading={pointLoading}
                />
              </Card>
            </Col>
          </Row>

          <Space wrap>
            <Button onClick={exportPointPdf} disabled={!pointDuJour}>
              Exporter point (PDF)
            </Button>
          </Space>

          {isSoldeSousSeuil ? (
            <Alert
              type="error"
              showIcon
              message="Solde sous le seuil d’alerte"
              description={`Solde actuel : ${soldeActuel.toLocaleString('fr-FR')} FCFA — Seuil : ${seuilAlerte.toLocaleString('fr-FR')} FCFA`}
            />
          ) : null}

          {selectedCaisse?.peut_operer === false ? (
            <Alert
              type="warning"
              showIcon
              message="Caisse en consultation seule"
              description="Vous visualisez une caisse d’agence. Les encaissements, sessions et mouvements sont réservés à la caisse principale siège (versements centralisés)."
            />
          ) : null}

          {!isSessionOpen ? (
            <Alert
              type="info"
              showIcon
              message="Caisse fermée"
              description="Ouvrez une session pour enregistrer des opérations. Vous pouvez toujours consulter l’historique et les rapports."
            />
          ) : null}

          {canSeeRecettesDuJour ? <RecettesDuJourCard /> : null}

          <EncaissementRapideCard
            isSessionOpen={isSessionOpen}
            onEncaissementSuccess={handleSuccess}
            readOnly={!canOperateOnSelectedCaisse}
          />

          <Tabs activeKey={activeTab} onChange={setActiveTab} size="large" items={mainTabItems} />
        </Space>
      </Card>

      {/* Formulaires Modaux */}
      <ApproForm
        visible={approFormVisible}
        onCancel={() => setApproFormVisible(false)}
        onSuccess={handleSuccess}
        idCaisse={idCaisse}
        soldeActuel={soldeActuel}
      />

      <DecaissementForm
        visible={decaissementFormVisible}
        onCancel={() => setDecaissementFormVisible(false)}
        onSuccess={handleSuccess}
        idCaisse={idCaisse}
        soldeActuel={soldeActuel}
      />

      <EntreeCaisseForm
        visible={entreeFormVisible}
        onCancel={() => setEntreeFormVisible(false)}
        onSuccess={handleSuccess}
        idCaisse={idCaisse}
        soldeActuel={soldeActuel}
        typeEntree={entreeType}
      />

      <Modal
        title="Ouverture de caisse"
        open={openModalVisible}
        onCancel={() => setOpenModalVisible(false)}
        onOk={() => {
          if (openAmount === null) return message.warning('Saisissez le solde reel a l ouverture.');
          openSessionMutation.mutate(openAmount);
        }}
        confirmLoading={openSessionMutation.isPending}
      >
        <Space direction="vertical" style={{ width: '100%' }}>
          <Typography.Text>Solde reel a l ouverture</Typography.Text>
          <InputNumber
            value={openAmount}
            onChange={(v: number | null) => setOpenAmount(typeof v === 'number' ? v : null)}
            style={{ width: '100%' }}
            min={0}
          />
        </Space>
      </Modal>

      <Modal
        title="Cloture de caisse"
        open={closeModalVisible}
        onCancel={() => setCloseModalVisible(false)}
        onOk={() => {
          if (closeAmount === null) return message.warning('Saisissez le solde reel a la fermeture.');
          if (!activeSession?.id) return message.warning('Aucune session active.');
          closeSessionMutation.mutate(closeAmount);
        }}
        confirmLoading={closeSessionMutation.isPending}
      >
        <Space direction="vertical" style={{ width: '100%' }}>
          <Alert
            type="info"
            showIcon
            message="Action tracée"
            description="La fermeture de session génère une trace dans le journal d’audit (horodatage, utilisateur, solde saisi)."
            style={{ marginBottom: 8 }}
          />
          <Typography.Text>Solde reel a la fermeture</Typography.Text>
          <InputNumber
            value={closeAmount}
            onChange={(v: number | null) => setCloseAmount(typeof v === 'number' ? v : null)}
            style={{ width: '100%' }}
            min={0}
          />
        </Space>
      </Modal>
    </div>
  )
}
