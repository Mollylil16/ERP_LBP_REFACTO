/**
 * Formulaire pour créer une entrée de caisse (Chèque/Espèce/Virement)
 */

import React from "react";
import {
  Modal,
  Form,
  Input,
  InputNumber,
  DatePicker,
  message,
  Select,
  AutoComplete,
} from "antd";
import dayjs from "dayjs";
import {
  createEntreeCaisse,
  validateNumeroDossier,
} from "@services/caisse.service";
import type { MouvementCaisse, ModeReglement } from "@/types";
import { useAuth } from "@hooks/useAuth";
import { useColisList } from "@hooks/useColis";
import { usePermissions } from "@hooks/usePermissions";
import { PERMISSIONS } from "@constants/permissions";

const { TextArea } = Input;
const { Option } = Select;

interface EntreeCaisseFormProps {
  visible: boolean;
  onCancel: () => void;
  onSuccess: () => void;
  idCaisse: number;
  soldeActuel?: number;
  typeEntree?: "ENTREE_CHEQUE" | "ENTREE_ESPECE" | "ENTREE_VIREMENT";
}

export const EntreeCaisseForm: React.FC<EntreeCaisseFormProps> = ({
  visible,
  onCancel,
  onSuccess,
  idCaisse,
  soldeActuel = 0,
  typeEntree = "ENTREE_ESPECE",
}) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = React.useState(false);
  const [validatingDossier, setValidatingDossier] = React.useState(false);
  const [modeReglement, setModeReglement] =
    React.useState<ModeReglement>("ESPECE");
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

  // Mapper typeEntree vers modeReglement
  React.useEffect(() => {
    if (typeEntree === "ENTREE_CHEQUE") setModeReglement("CHEQUE");
    else if (typeEntree === "ENTREE_VIREMENT") setModeReglement("VIREMENT");
    else setModeReglement("ESPECE");
    form.setFieldsValue({ mode_reglement: modeReglement });
  }, [typeEntree, form]);

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

      // Déterminer le type d'entrée en fonction du mode de règlement
      let type: MouvementCaisse["type"] = "ENTREE_ESPECE";
      if (values.mode_reglement === "CHEQUE") type = "ENTREE_CHEQUE";
      else if (values.mode_reglement === "VIREMENT") type = "ENTREE_VIREMENT";
      else type = "ENTREE_ESPECE";

      const data: Partial<MouvementCaisse> = {
        date: values.date.format("YYYY-MM-DD"),
        type,
        libelle: values.libelle,
        montant: values.montant,
        mode_reglement: values.mode_reglement,
        numero_dossier: values.numero_dossier,
        nom_client: values.nom_client,
        numero_cheque: values.numero_cheque,
        numero_recu: values.numero_recu,
        numero_virement: values.numero_virement,
        numero_fiche_recette: values.numero_fiche_recette,
        numero_bordereau_versement: values.numero_bordereau_versement,
        banque_remise: values.banque_remise,
        banque_creditee: values.banque_creditee,
        reste_a_payer: values.reste_a_payer,
        id_caisse: idCaisse,
        code_user: user?.code_user || "",
        etat: 1, // Validé par défaut
        solde: soldeActuel + values.montant, // Calcul du nouveau solde
      };

      // Lier au colis si numéro de dossier fourni
      if (values.numero_dossier && colisData) {
        const colis = colisData.find(
          (c) => c.ref_colis === values.numero_dossier
        );
        if (colis) {
          data.id_colis = colis.id;
        }
      }

      await createEntreeCaisse(data);

      message.success("Entrée de caisse créée avec succès");
      form.resetFields();
      onSuccess();
    } catch (error: any) {
      console.error("Erreur lors de la création de l'entrée de caisse:", error);
      message.error(
        error?.response?.data?.message ||
        "Erreur lors de la création de l'entrée de caisse"
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
      title={
        typeEntree === "ENTREE_CHEQUE"
          ? "Nouvelle Entrée de Caisse - Chèque"
          : typeEntree === "ENTREE_VIREMENT"
            ? "Nouvelle Entrée de Caisse - Virement"
            : "Nouvelle Entrée de Caisse - Espèce"
      }
      open={visible}
      onCancel={onCancel}
      onOk={() => form.submit()}
      confirmLoading={loading}
      width={800}
      okText="Enregistrer"
      cancelText="Annuler"
    >
      <Form
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={{
          date: dayjs(),
          mode_reglement: modeReglement,
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
          name="mode_reglement"
          label="Mode de règlement"
          rules={[
            {
              required: true,
              message: "Veuillez sélectionner un mode de règlement",
            },
          ]}
        >
          <Select
            onChange={(value: ModeReglement) => setModeReglement(value)}
            disabled={typeEntree !== undefined}
          >
            <Option value="ESPECE">Espèce</Option>
            <Option value="CHEQUE">Chèque</Option>
            <Option value="VIREMENT">Virement</Option>
          </Select>
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
          name="nom_client"
          label="Nom du client"
          rules={[
            { required: true, message: "Veuillez saisir le nom du client" },
          ]}
        >
          <Input placeholder="Nom du client" />
        </Form.Item>

        {modeReglement === "CHEQUE" && (
          <>
            <Form.Item
              name="numero_cheque"
              label="Numéro de chèque"
              rules={[
                {
                  required: true,
                  message: "Veuillez saisir le numéro de chèque",
                },
              ]}
            >
              <Input placeholder="Numéro du chèque" />
            </Form.Item>

            <Form.Item
              name="banque_remise"
              label="Banque de remise"
              rules={[
                {
                  required: true,
                  message: "Veuillez saisir la banque de remise",
                },
              ]}
            >
              <Input placeholder="Banque où le chèque est remis" />
            </Form.Item>
          </>
        )}

        {modeReglement === "ESPECE" && (
          <Form.Item
            name="numero_recu"
            label="Numéro de reçu"
            rules={[
              { required: true, message: "Veuillez saisir le numéro de reçu" },
            ]}
          >
            <Input placeholder="Numéro de reçu" />
          </Form.Item>
        )}

        {modeReglement === "VIREMENT" && (
          <>
            <Form.Item
              name="numero_virement"
              label="Numéro d'ordre de virement"
              rules={[
                {
                  required: true,
                  message: "Veuillez saisir le numéro d'ordre de virement",
                },
              ]}
            >
              <Input placeholder="Numéro d'ordre de virement" />
            </Form.Item>

            <Form.Item
              name="banque_creditee"
              label="Banque créditée"
              rules={[
                {
                  required: true,
                  message: "Veuillez saisir la banque créditée",
                },
              ]}
            >
              <Input placeholder="Banque créditée" />
            </Form.Item>

            <Form.Item
              name="numero_recu"
              label="Numéro du reçu"
              rules={[
                {
                  required: true,
                  message: "Veuillez saisir le numéro du reçu",
                },
              ]}
            >
              <Input placeholder="Numéro du reçu" />
            </Form.Item>
          </>
        )}

        <Form.Item
          name="numero_fiche_recette"
          label="Numéro de fiche recette"
          tooltip="Numéro généré automatiquement par le système"
        >
          <Input placeholder="Généré automatiquement" disabled />
        </Form.Item>

        <Form.Item
          name="numero_bordereau_versement"
          label="Numéro de bordereau de versement interne"
          tooltip="Numéro généré automatiquement par le système"
        >
          <Input placeholder="Généré automatiquement" disabled />
        </Form.Item>

        <Form.Item
          name="montant"
          label="Montant perçu"
          rules={[
            { required: true, message: "Veuillez saisir un montant" },
            {
              type: "number",
              min: 0.01,
              message: "Le montant doit être supérieur à 0",
            },
          ]}
        >
          <InputNumber
            style={{ width: "100%" }}
            placeholder="Montant en FCFA"
            min={0}
            step={1000}
            formatter={(value: any) =>
              `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, " ")
            }
          />
        </Form.Item>

        <Form.Item
          name="reste_a_payer"
          label="Reste à payer"
          rules={[
            {
              type: "number",
              min: 0,
              message: "Le reste à payer doit être positif ou nul",
            },
          ]}
        >
          <InputNumber
            style={{ width: "100%" }}
            placeholder="Reste à payer en FCFA"
            min={0}
            step={1000}
            formatter={(value: any) =>
              `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, " ")
            }
          />
        </Form.Item>

        <Form.Item name="libelle" label="Libellé">
          <TextArea rows={2} placeholder="Description (optionnel)" />
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

        {soldeActuel !== undefined && form.getFieldValue("montant") && (
          <Form.Item label="Solde après entrée">
            <Input
              value={
                (
                  soldeActuel + (form.getFieldValue("montant") || 0)
                ).toLocaleString("fr-FR") + " FCFA"
              }
              disabled
              style={{ backgroundColor: "#d4edda" }}
            />
          </Form.Item>
        )}
      </Form>
    </Modal>
  );
};
