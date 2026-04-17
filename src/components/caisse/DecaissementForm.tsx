/**
 * Formulaire pour créer un décaissement
 */

import React from "react";
import {
  Modal,
  Form,
  Input,
  InputNumber,
  DatePicker,
  message,
  AutoComplete,
} from "antd";
import dayjs from "dayjs";
import {
  createDecaissement,
  validateNumeroDossier,
} from "@services/caisse.service";
import type { MouvementCaisse } from "@types";
import { useAuth } from "@hooks/useAuth";
import { useColisList } from "@hooks/useColis";
import { usePermissions } from "@hooks/usePermissions";
import { PERMISSIONS } from "@constants/permissions";

const { TextArea } = Input;

interface DecaissementFormProps {
  visible: boolean;
  onCancel: () => void;
  onSuccess: () => void;
  idCaisse: number;
  soldeActuel?: number;
}

export const DecaissementForm: React.FC<DecaissementFormProps> = ({
  visible,
  onCancel,
  onSuccess,
  idCaisse,
  soldeActuel = 0,
}) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = React.useState(false);
  const [validatingDossier, setValidatingDossier] = React.useState(false);
  const { user } = useAuth();
  const { hasAnyPermission } = usePermissions();
  const canReadColis = hasAnyPermission([
    PERMISSIONS.COLIS_GROUPAGE.READ,
    PERMISSIONS.COLIS_AUTRES_ENVOIS.READ,
  ]);
  const shouldLoadColis = Boolean(visible) && canReadColis;

  const { data: colisGroupage } = useColisList(
    "groupage",
    { page: 1, limit: 1000 },
    { enabled: shouldLoadColis },
  );
  const { data: colisAutres } = useColisList(
    "autres_envoi",
    { page: 1, limit: 1000 },
    { enabled: shouldLoadColis },
  );

  // Combiner les deux listes pour l'autocomplete
  const colisData = React.useMemo(() => {
    const groupage = colisGroupage?.data || [];
    const autres = colisAutres?.data || [];
    return [...groupage, ...autres];
  }, [colisGroupage, colisAutres]);

  // Liste des numéros de dossiers pour l'autocomplete
  const dossierOptions = React.useMemo(() => {
    if (!colisData) return [];
    return colisData.map((colis) => ({
      value: colis.ref_colis,
      label: `${colis.ref_colis} - ${colis.client_colis?.nom_exp || ""}`,
    }));
  }, [colisData]);

  const handleSubmit = async (values: any) => {
    try {
      setLoading(true);

      // Vérifier que le solde est suffisant
      if (values.montant > soldeActuel) {
        message.error(
          "Le montant du décaissement dépasse le solde actuel de la caisse"
        );
        return;
      }

      const data: Partial<MouvementCaisse> = {
        date: values.date.format("YYYY-MM-DD"),
        type: "DECAISSEMENT",
        libelle: values.libelle,
        montant: values.montant,
        numero_dossier: values.numero_dossier,
        nom_demandeur: values.nom_demandeur,
        id_caisse: idCaisse,
        code_user: user?.code_user || "",
        etat: 1, // Validé par défaut
        solde: soldeActuel - values.montant, // Calcul du nouveau solde
      };

      await createDecaissement(data);

      message.success("Décaissement créé avec succès");
      form.resetFields();
      onSuccess();
    } catch (error: any) {
      console.error("Erreur lors de la création du décaissement:", error);
      message.error(
        error?.response?.data?.message ||
        "Erreur lors de la création du décaissement"
      );
    } finally {
      setLoading(false);
    }
  };

  const handleValidateDossier = async (value: string) => {
    if (!value) return;

    try {
      setValidatingDossier(true);
      const result = await validateNumeroDossier(value);

      if (!result.valid) {
        form.setFields([
          {
            name: "numero_dossier",
            errors: ["Numéro de dossier non conforme ou inexistant"],
          },
        ]);
        message.warning("Numéro de dossier non conforme ou inexistant");
      } else {
        form.setFields([
          {
            name: "numero_dossier",
            errors: [],
          },
        ]);
      }
    } catch (error) {
      console.error(
        "Erreur lors de la validation du numéro de dossier:",
        error
      );
    } finally {
      setValidatingDossier(false);
    }
  };

  return (
    <Modal
      title="Nouveau Décaissement"
      open={visible}
      onCancel={onCancel}
      onOk={() => form.submit()}
      confirmLoading={loading}
      width={700}
      okText="Enregistrer"
      cancelText="Annuler"
    >
      <Form
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={{
          date: dayjs(),
        }}
      >
        <Form.Item
          name="date"
          label="Date"
          rules={[
            { required: true, message: "Veuillez sélectionner une date" },
          ]}
        >
          <DatePicker style={{ width: "100%" }} format="DD/MM/YYYY" />
        </Form.Item>

        <Form.Item
          name="nom_demandeur"
          label="Nom du demandeur"
          rules={[
            { required: true, message: "Veuillez saisir le nom du demandeur" },
          ]}
        >
          <Input placeholder="Nom de la personne qui demande" />
        </Form.Item>

        <Form.Item
          name="numero_dossier"
          label="Numéro de dossier"
          rules={[
            { required: true, message: "Veuillez saisir un numéro de dossier" },
          ]}
        >
          <AutoComplete
            options={dossierOptions}
            placeholder="Saisissez le numéro de dossier (ex: LBP-0124-001)"
            onBlur={(e: React.FocusEvent<HTMLElement>) => {
              const target = e.target as HTMLInputElement;
              if (target.value) {
                handleValidateDossier(target.value);
              }
            }}
            onSelect={(value: string) => handleValidateDossier(value)}
            filterOption={(inputValue: string, option: any) =>
              option!.value.toUpperCase().indexOf(inputValue.toUpperCase()) !==
              -1
            }
          />
        </Form.Item>

        <Form.Item
          name="libelle"
          label="Libellé"
          rules={[{ required: true, message: "Veuillez saisir un libellé" }]}
        >
          <TextArea rows={3} placeholder="Description du décaissement" />
        </Form.Item>

        <Form.Item
          name="montant"
          label="Montant"
          rules={[
            { required: true, message: "Veuillez saisir un montant" },
            {
              type: "number",
              min: 0.01,
              message: "Le montant doit être supérieur à 0",
            },
            {
              validator: (_: any, value: number) => {
                if (value > soldeActuel) {
                  return Promise.reject(
                    new Error(
                      `Le montant ne peut pas dépasser le solde actuel (${soldeActuel.toLocaleString(
                        "fr-FR"
                      )} FCFA)`
                    )
                  );
                }
                return Promise.resolve();
              },
            },
          ]}
        >
          <InputNumber<number>
            style={{ width: "100%" }}
            placeholder="Montant en FCFA"
            min={0}
            step={1000}
            formatter={(value: any) =>
              `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, " ")
            }
            parser={(value: any) => Number(value?.replace(/\s?/g, "") || "0")}
          />
        </Form.Item>

        {soldeActuel !== undefined && (
          <Form.Item label="Solde actuel">
            <Input
              value={soldeActuel.toLocaleString("fr-FR") + " FCFA"}
              disabled
              style={{ backgroundColor: "#f5f5f5" }}
            />
          </Form.Item>
        )}

        {soldeActuel !== undefined && (
          <Form.Item
            noStyle
            shouldUpdate={(prevValues: any, currentValues: any) =>
              prevValues.montant !== currentValues.montant
            }
          >
            {({ getFieldValue }: any) => {
              const montant = getFieldValue("montant") || 0;
              const nouveauSolde = soldeActuel - montant;

              if (!montant) return null;

              return (
                <Form.Item label="Solde après décaissement">
                  <Input
                    value={nouveauSolde.toLocaleString("fr-FR") + " FCFA"}
                    disabled
                    style={{
                      backgroundColor: "#fff3cd",
                      color: nouveauSolde < 0 ? "#ff4d4f" : "#000",
                    }}
                  />
                </Form.Item>
              );
            }}
          </Form.Item>
        )}
      </Form>
    </Modal>
  );
};
