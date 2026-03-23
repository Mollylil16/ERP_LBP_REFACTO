import React from "react";
import { Typography } from "antd";
import { useNavigate } from "react-router-dom";
import { FactureList } from "@components/factures/FactureList";
import { FactureColis } from "@/types";

const { Title } = Typography;

export const FacturesListPage: React.FC = () => {
  const navigate = useNavigate();

  const handleView = (facture: FactureColis) => {
    navigate(`/factures/${facture.id}/preview`);
  };

  return (
    <div>
      <Title level={2}>Gestion de la Facturation</Title>
      <FactureList onView={handleView} />
    </div>
  );
};
