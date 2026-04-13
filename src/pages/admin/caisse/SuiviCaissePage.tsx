/**
 * Page de suivi de caisse avec sections APPRO, DÉCAISSEMENT, ENTREES, RAPPORT
 */

import React from 'react'
import { Tabs, Button, Space, Card, Select, Modal, InputNumber, message, Typography, Alert } from 'antd'
import {
  WalletOutlined,
  ArrowUpOutlined,
  ArrowDownOutlined,
  DollarOutlined,
  FileTextOutlined,
  PlusOutlined,
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
import { useCaisses, useSoldeCaisse } from '@hooks/useCaisse'
import { caisseService } from '@services/caisse.service'
import { useQuery, useMutation } from '@tanstack/react-query'

export const SuiviCaissePage: React.FC = () => {
  const { hasPermission } = usePermissions()
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

  // Initialisation de la caisse sélectionnée au premier chargement
  React.useEffect(() => {
    if (caisses && caisses.length > 0 && !selectedCaisseId) {
      setSelectedCaisseId(caisses[0].id)
    }
  }, [caisses, selectedCaisseId])

  const selectedCaisse = caisses?.find(c => c.id === selectedCaisseId) || caisses?.[0]
  const idCaisse = selectedCaisseId || selectedCaisse?.id || 1

  const { data: soldeActuelRealtime, refetch: refetchSolde } = useSoldeCaisse(idCaisse)
  const soldeActuel = soldeActuelRealtime ?? selectedCaisse?.solde_actuel ?? 0

  const { data: activeSession, refetch: refetchSession } = useQuery({
    queryKey: ['caisse-active-session', idCaisse],
    queryFn: () => caisseService.getActiveSession(idCaisse),
    enabled: Boolean(idCaisse),
  })

  const openSessionMutation = useMutation({
    mutationFn: (amount: number) =>
      caisseService.openSession({ id_caisse: idCaisse, solde_ouverture_reel: amount }),
    onSuccess: () => {
      message.success('Session de caisse ouverte avec succes.');
      setOpenModalVisible(false);
      setOpenAmount(null);
      refetchSession();
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
    },
    onError: (err: any) => message.error(err.message || 'Erreur lors de la cloture de session'),
  })

  const handleSuccess = () => {
    setApproFormVisible(false)
    setDecaissementFormVisible(false)
    setEntreeFormVisible(false)
    setRefreshKey((prev) => prev + 1) // Force le rechargement des listes
    refetchSolde()
    refetchCaisses()
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
                onClick={() => setApproFormVisible(true)}
              >
                Nouvel Approvisionnement
              </Button>
            </WithPermission>
          </div>

          <MouvementsCaisseList
            key={`appro-${refreshKey}`}
            type="APPRO"
            idCaisse={idCaisse}
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
                onClick={() => setDecaissementFormVisible(true)}
              >
                Nouveau Décaissement
              </Button>
            </WithPermission>
          </div>

          <MouvementsCaisseList
            key={`decaissement-${refreshKey}`}
            type="DECAISSEMENT"
            idCaisse={idCaisse}
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
                  onClick={() => handleOpenEntreeForm('ENTREE_ESPECE')}
                >
                  Entrée Espèce
                </Button>
                <Button
                  type="primary"
                  icon={<PlusOutlined />}
                  onClick={() => handleOpenEntreeForm('ENTREE_CHEQUE')}
                >
                  Entrée Chèque
                </Button>
                <Button
                  type="primary"
                  icon={<PlusOutlined />}
                  onClick={() => handleOpenEntreeForm('ENTREE_VIREMENT')}
                >
                  Entrée Virement
                </Button>
              </Space>
            </WithPermission>
          </div>

          <Tabs size="small" type="card" items={entreesTabItems} />
        </Space>
      ),
    },
    {
      key: 'rapport',
      label: (
        <span>
          <FileTextOutlined /> RAPPORT GRANDES LIGNES
        </span>
      ),
      children: <RapportGrandesLignes idCaisse={idCaisse} />,
    },
  ]

  return (
    <div style={{ padding: 24 }}>
      <Card>
        <Space direction="vertical" style={{ width: '100%' }} size="large">
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
                      label: c.libelle || c.code,
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
                      <Button type="primary" onClick={() => setOpenModalVisible(true)}>
                        Ouvrir caisse
                      </Button>
                    ) : (
                      <TracedActionButton
                        danger
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

          {canSeeRecettesDuJour ? <RecettesDuJourCard /> : null}

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
