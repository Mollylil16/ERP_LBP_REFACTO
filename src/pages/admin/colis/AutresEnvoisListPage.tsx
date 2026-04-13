import React, { useState } from "react";
import { Modal, Typography, Grid } from "antd";
import { ColisList } from "@components/colis/ColisList";
import { ColisForm } from "@components/colis/ColisForm";
import { ColisDetails } from "@components/colis/ColisDetails";
import { Colis, CreateColisDto } from "@types";
import { useCreateAutresEnvois, useUpdateColis } from "@hooks/useColis";

const { Title } = Typography;

export const ColisAutresEnvoisListPage: React.FC = () => {
  const screens = Grid.useBreakpoint();
  const modalFullBleed = screens.lg === false;

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedColis, setSelectedColis] = useState<Colis | null>(null);
  const [isViewMode, setIsViewMode] = useState(false);

  const createMutation = useCreateAutresEnvois();
  const updateMutation = useUpdateColis();

  const handleCreate = () => {
    setSelectedColis(null);
    setIsViewMode(false);
    setIsModalOpen(true);
  };

  const handleEdit = (colis: Colis) => {
    setSelectedColis(colis);
    setIsViewMode(false);
    setIsModalOpen(true);
  };

  const handleView = (colis: Colis) => {
    setSelectedColis(colis);
    setIsViewMode(true);
    setIsModalOpen(true);
  };

  const handleSubmit = async (data: CreateColisDto) => {
    try {
      if (selectedColis) {
        await updateMutation.mutateAsync({
          id: selectedColis.id,
          data: data as any,
        });
      } else {
        await createMutation.mutateAsync(data);
      }
      setIsModalOpen(false);
      setSelectedColis(null);
    } catch (error) {
      console.error("Error submitting form:", error);
    }
  };

  const handleCancel = () => {
    setIsModalOpen(false);
    setSelectedColis(null);
    setIsViewMode(false);
  };

  return (
    <div>
      <Title level={2}>Gestion des Colis - Autres Envois</Title>

      <ColisList
        formeEnvoi="autres_envoi"
        onCreate={handleCreate}
        onEdit={handleEdit}
        onView={handleView}
      />

      <Modal
        title={
          isViewMode
            ? `Détails Colis - ${selectedColis?.ref_colis || ""}`
            : selectedColis
              ? "Modifier Colis"
              : "Nouveau Colis"
        }
        open={isModalOpen}
        onCancel={handleCancel}
        footer={null}
        width={modalFullBleed ? "100%" : "90%"}
        style={{ top: modalFullBleed ? 0 : 20, maxWidth: "100vw", paddingBottom: 0 }}
        styles={
          modalFullBleed
            ? {
                content: { margin: 0, maxWidth: "100vw" },
                body: {
                  maxHeight: "calc(100dvh - 108px)",
                  overflowY: "auto",
                  padding: "12px 14px",
                },
              }
            : undefined
        }
      >
        {isViewMode && selectedColis ? (
          <ColisDetails
            colisId={selectedColis.id}
            onClose={handleCancel}
            onEdit={() => handleEdit(selectedColis)}
          />
        ) : (
          <ColisForm
            formeEnvoi="autres_envoi"
            onSubmit={handleSubmit}
            onCancel={handleCancel}
            initialData={
              selectedColis
                ? {
                  trafic_envoi: selectedColis.trafic_envoi,
                  date_envoi: selectedColis.date_envoi,
                  mode_envoi: selectedColis.mode_envoi,
                  client_colis: selectedColis.client_colis,
                  marchandise: [
                    {
                      nom_marchandise: selectedColis.nom_marchandise,
                      nbre_colis: selectedColis.nbre_colis,
                      nbre_articles: selectedColis.nbre_articles,
                      poids_total: selectedColis.poids_total,
                      prix_unit: selectedColis.prix_unit,
                      prix_emballage: selectedColis.prix_emballage || 0,
                      prix_assurance: selectedColis.prix_assurance || 0,
                      prix_agence: selectedColis.prix_agence || 0,
                      type_emballage: [],
                      produits_catalogue_ids: [],
                      nbre_emballage: 1,
                    },
                  ],
                  nom_destinataire: selectedColis.nom_destinataire,
                  lieu_dest: selectedColis.lieu_dest,
                  tel_dest: selectedColis.tel_dest,
                  email_dest: selectedColis.email_dest,
                  nom_recup: selectedColis.nom_recup,
                  adresse_recup: selectedColis.adresse_recup,
                  tel_recup: selectedColis.tel_recup,
                  email_recup: selectedColis.email_recup,
                }
                : undefined
            }
            loading={createMutation.isPending || updateMutation.isPending}
          />
        )}
      </Modal>
    </div>
  );
};
