import React, { useState } from "react";
import { Modal, Typography } from "antd";
import { ColisList } from "@components/colis/ColisList";
import { ColisForm } from "@components/colis/ColisForm";
import { ColisDetails } from "@components/colis/ColisDetails";
import { Colis, CreateColisDto } from "@types";
import { useCreateGroupage, useUpdateColis } from "@hooks/useColis";
import { useColis } from "@hooks/useColis";

const { Title } = Typography;

export const ColisGroupageListPage: React.FC = () => {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedColis, setSelectedColis] = useState<Colis | null>(null);
  const [isViewMode, setIsViewMode] = useState(false);

  const createMutation = useCreateGroupage();
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
        // Mise à jour
        await updateMutation.mutateAsync({
          id: selectedColis.id,
          data: data as any,
        });
      } else {
        // Création
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
      <Title level={2}>Gestion des Colis - Groupage</Title>

      <ColisList
        formeEnvoi="groupage"
        onCreate={handleCreate}
        onEdit={handleEdit}
        onView={handleView}
      />

      <Modal
        title={
          isViewMode
            ? `Détails Colis - ${selectedColis?.ref_colis || ""}`
            : selectedColis
              ? "Modifier Colis Groupage"
              : "Nouveau Colis Groupage"
        }
        open={isModalOpen}
        onCancel={handleCancel}
        footer={null}
        width="90%"
        style={{ top: 20 }}
      >
        {isViewMode && selectedColis ? (
          <ColisDetails
            colisId={selectedColis.id}
            onClose={handleCancel}
            onEdit={() => handleEdit(selectedColis)}
          />
        ) : (
          <ColisForm
            formeEnvoi="groupage"
            onSubmit={handleSubmit}
            onCancel={handleCancel}
            initialData={
              selectedColis
                ? {
                  trafic_envoi: selectedColis.trafic_envoi,
                  date_envoi: selectedColis.date_envoi,
                  mode_envoi: selectedColis.mode_envoi || "groupage",
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
