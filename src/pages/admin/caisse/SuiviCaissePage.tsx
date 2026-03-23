/**
 * Page de suivi de caisse avec sections APPRO, DÉCAISSEMENT, ENTREES, RAPPORT
 */

import React from 'react'
import { Tabs, Button, Space, Card, Select } from 'antd'
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
import { PERMISSIONS } from '@constants/permissions'
import { useCaisses, useSoldeCaisse } from '@hooks/useCaisse'

export const SuiviCaissePage: React.FC = () => {
  const [activeTab, setActiveTab] = React.useState('appro')
  const [approFormVisible, setApproFormVisible] = React.useState(false)
  const [decaissementFormVisible, setDecaissementFormVisible] = React.useState(false)
  const [entreeFormVisible, setEntreeFormVisible] = React.useState(false)
  const [entreeType, setEntreeType] = React.useState<
    'ENTREE_CHEQUE' | 'ENTREE_ESPECE' | 'ENTREE_VIREMENT'
  >('ENTREE_ESPECE')
  const [refreshKey, setRefreshKey] = React.useState(0)
  const [selectedCaisseId, setSelectedCaisseId] = React.useState<number | undefined>()

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
                    onChange={(val) => setSelectedCaisseId(val)}
                    style={{ minWidth: 200 }}
                    options={caisses.map((c) => ({
                      label: c.libelle || c.nom,
                      value: c.id,
                    }))}
                  />
                  <strong>Solde actuel:</strong>{' '}
                  <span style={{ color: '#52c41a', fontWeight: 'bold' }}>
                    {soldeActuel.toLocaleString('fr-FR')} FCFA
                  </span>
                </Space>
              </Card>
            )}
          </div>

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
    </div>
  )
}
