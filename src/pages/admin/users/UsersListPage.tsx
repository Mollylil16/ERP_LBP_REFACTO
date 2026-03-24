import React, { useState } from "react";
import {
  Typography,
  Table,
  Button,
  Space,
  Input,
  Modal,
  Tag,
  Popconfirm,
  Tooltip,
  Card,
  Row,
  Col,
  Select,
  Switch,
  message,
  Form,
} from "antd";
import {
  EditOutlined,
  DeleteOutlined,
  PlusOutlined,
  SearchOutlined,
  ReloadOutlined,
  EyeOutlined,
  KeyOutlined,
  UserOutlined,
  SendOutlined,
  CopyOutlined,
} from "@ant-design/icons";
import type { ColumnsType } from "antd/es/table";
import { User, UserRole, Agency } from "@types";
import { formatDate } from "@utils/format";
import { usersService } from "@services/users.service";
import { apiService } from "@services/api.service";
import { PERMISSIONS } from "@constants/permissions";
import { WithPermission } from "@components/common/WithPermission";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { UsersListSkeleton } from "@components/common/SkeletonLoader";
import { EmptyUsersList, EmptySearchState, EmptyErrorState } from "@components/common/EmptyState";
import { VirtualTable } from "@components/common/VirtualTable";

const { Title } = Typography;
const { Option } = Select;

export const UsersListPage: React.FC = () => {
  const queryClient = useQueryClient();
  const [searchTerm, setSearchTerm] = useState("");
  const [selectedUser, setSelectedUser] = useState<User | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isCreateMode, setIsCreateMode] = useState(false);
  const [isPasswordModalOpen, setIsPasswordModalOpen] = useState(false);
  const [visiblePassword, setVisiblePassword] = useState<string | null>(null);

  // Récupérer les utilisateurs
  const { data: users, isLoading, error, refetch } = useQuery<User[]>({
    queryKey: ['users'],
    queryFn: () => usersService.getAll(),
  });

  // Récupérer les agences pour les sélecteurs
  const { data: agences } = useQuery<Agency[]>({
    queryKey: ['agences'],
    queryFn: () => apiService.get('/agences'),
  });

  // Mutation pour reset mdp
  const resetPwdMutation = useMutation({
    mutationFn: ({ id, pwd }: { id: number, pwd: string }) => usersService.resetPassword(id, pwd),
    onSuccess: () => {
      message.success("Mot de passe réinitialisé");
      refetch();
    },
    onError: (err: any) => message.error(err.message || "Erreur lors du reset"),
  });

  // Mutation pour envoi du mdp temporaire
  const sendTempPwdMutation = useMutation({
    mutationFn: (id: number) => usersService.sendTemporaryPassword(id),
    onSuccess: (res) => {
      if (res.sent) {
        message.success(res.message || "Mot de passe temporaire envoyé.");
      } else {
        message.warning(res.message || "Envoi impossible.");
      }
    },
    onError: (err: any) => message.error(err.message || "Erreur lors de l'envoi du mot de passe temporaire"),
  });

  // Mutation pour toggle actif
  const toggleActiveMutation = useMutation({
    mutationFn: (id: number) => usersService.toggleActive(id),
    onSuccess: () => {
      message.success("Statut mis à jour");
      queryClient.invalidateQueries({ queryKey: ['users'] });
    },
  });

  const showPassword = async (id: number) => {
    try {
      const res = await usersService.getPasswordPlain(id);
      if (res.password_plain) {
        setVisiblePassword(res.password_plain);
        setIsPasswordModalOpen(true);
      } else {
        message.info("L'utilisateur a déjà changé son mot de passe initial.");
      }
    } catch (error) {
      message.error("Erreur lors de la récupération du mot de passe.");
    }
  };

  const columns: ColumnsType<User> = [
    {
      title: "Utilisateur",
      key: "user",
      width: 250,
      render: (_: any, record: User) => (
        <Space direction="vertical" size={0}>
          <span style={{ fontWeight: 'bold' }}>{record.nom_complet}</span>
          <span style={{ fontSize: '12px', color: '#8c8c8c' }}>@{record.username}</span>
        </Space>
      )
    },
    {
      title: "Rôle",
      key: "role",
      width: 150,
      render: (_: any, record: User) => (
        <Tag color={record.role.code === "DIRECTEUR" ? "volcano" : "blue"}>
          {record.role.name}
        </Tag>
      ),
    },
    {
      title: "Agence",
      key: "agence",
      width: 180,
      render: (_: any, record: User) =>
        (record.agency?.name || (record.agency as any)?.nom || record.agency?.code) || <Tag>Non assignée</Tag>,
    },
    {
      title: "Statut",
      key: "status",
      width: 120,
      render: (_: any, record: User) => (
        <Space>
          <Switch
            size="small"
            checked={record.actif}
            onChange={() => toggleActiveMutation.mutate(record.id)}
            loading={toggleActiveMutation.isPending}
          />
          <Tag color={record.actif ? "success" : "error"}>
            {record.actif ? "Actif" : "Inactif"}
          </Tag>
        </Space>
      ),
    },
    {
      title: "Actions",
      key: "actions",
      fixed: "right",
      width: 260,
      render: (_: any, record: User) => (
        <Space size="small">
          <Tooltip title="Voir mdp temporaire">
            <Button
              size="small"
              icon={<EyeOutlined />}
              onClick={() => showPassword(record.id)}
              disabled={!record.password_plain}
            />
          </Tooltip>

          <Tooltip title="Copier mdp temporaire">
            <Button
              size="small"
              icon={<CopyOutlined />}
              disabled={!record.password_plain}
              onClick={async () => {
                if (!record.password_plain) return;
                try {
                  await navigator.clipboard.writeText(record.password_plain);
                  message.success("Mot de passe temporaire copié");
                } catch {
                  message.error("Impossible de copier le mot de passe");
                }
              }}
            />
          </Tooltip>

          <Tooltip title="Reset mot de passe">
            <Popconfirm
              title="Générer un nouveau mot de passe temporaire ?"
              onConfirm={() => {
                const newPlain = Math.random().toString(36).slice(-8);
                resetPwdMutation.mutate({ id: record.id, pwd: newPlain });
              }}
            >
              <Button size="small" icon={<KeyOutlined />} />
            </Popconfirm>
          </Tooltip>

          <Tooltip title="Envoyer mdp temporaire (WhatsApp/SMS)">
            <Button
              size="small"
              icon={<SendOutlined />}
              loading={sendTempPwdMutation.isPending}
              onClick={() => sendTempPwdMutation.mutate(record.id)}
            />
          </Tooltip>

          <Button
            type="primary"
            size="small"
            icon={<EditOutlined />}
            onClick={() => {
              setSelectedUser(record);
              setIsCreateMode(false);
              setIsModalOpen(true);
            }}
          />

          <Popconfirm
            title="Supprimer cet utilisateur ?"
            onConfirm={() => usersService.delete(record.id).then(() => refetch())}
            okText="Oui"
            cancelText="Non"
          >
            <Button type="primary" danger size="small" icon={<DeleteOutlined />} />
          </Popconfirm>
        </Space>
      ),
    },
  ];

  if (isLoading && !users) return <UsersListSkeleton />;

  return (
    <div className="page-container">
      <div className="page-header">
        <Title level={2}>Gestion des Utilisateurs</Title>
        <Space>
          <Button icon={<ReloadOutlined />} onClick={() => refetch()} loading={isLoading}>
            Actualiser
          </Button>
          <Button
            type="primary"
            icon={<PlusOutlined />}
            onClick={() => {
              setSelectedUser(null);
              setIsCreateMode(true);
              setIsModalOpen(true);
            }}
          >
            Nouvel Utilisateur
          </Button>
        </Space>
      </div>

      <Card style={{ marginBottom: 16 }}>
        <Input
          placeholder="Rechercher par nom, username, email..."
          prefix={<SearchOutlined />}
          allowClear
          value={searchTerm}
          onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSearchTerm(e.target.value)}
          size="large"
        />
      </Card>

      <Card bodyStyle={{ padding: 0 }}>
        <Table<User>
          columns={columns}
          dataSource={users?.filter(u =>
            u.nom_complet.toLowerCase().includes(searchTerm.toLowerCase()) ||
            u.username.toLowerCase().includes(searchTerm.toLowerCase())
          )}
          rowKey="id"
          pagination={{ pageSize: 15 }}
          scroll={{ x: 1000 }}
        />
      </Card>

      {/* MODAL FORMULAIRE */}
      <Modal
        title={isCreateMode ? "Nouvel Utilisateur" : "Modifier Utilisateur"}
        open={isModalOpen}
        onCancel={() => setIsModalOpen(false)}
        footer={null}
        destroyOnClose
      >
        <UserForm
          user={selectedUser}
          agences={agences || []}
          onSuccess={() => {
            setIsModalOpen(false);
            refetch();
          }}
          onCancel={() => setIsModalOpen(false)}
        />
      </Modal>

      {/* MODAL PASSWORD VIEW */}
      <Modal
        title="Mot de passe temporaire"
        open={isPasswordModalOpen}
        onCancel={() => setIsPasswordModalOpen(false)}
        footer={[
          <Button key="close" onClick={() => setIsPasswordModalOpen(false)}>Fermer</Button>
        ]}
      >
        <div style={{ textAlign: 'center', padding: '20px' }}>
          <p>Le mot de passe initial de cet utilisateur est :</p>
          <Title level={3} copyable style={{ color: '#1890ff' }}>{visiblePassword}</Title>
          <p style={{ fontSize: '12px', color: '#8c8c8c' }}>
            Ce mot de passe disparaîtra dès que l'utilisateur le changera lui-même.
          </p>
        </div>
      </Modal>
    </div>
  );
};

const UserForm: React.FC<{ user: any, agences: Agency[], onSuccess: () => void, onCancel: () => void }> = ({
  user, agences, onSuccess, onCancel
}) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [createdPassword, setCreatedPassword] = useState<string | null>(null);

  const onFinish = async (values: any) => {
    setLoading(true);
    try {
      if (user) {
        await usersService.update(user.id, values);
        message.success("Utilisateur mis à jour");
        onSuccess();
      } else {
        const created: any = await usersService.create(values);
        if (created?.password_plain) {
          setCreatedPassword(created.password_plain);
        } else {
          onSuccess();
        }
        message.success("Utilisateur créé avec succès");
      }
    } catch (error: any) {
      message.error(error.message || "Erreur lors de l'enregistrement");
    } finally {
      setLoading(false);
    }
  };

  return (
    <Form
      form={form}
      layout="vertical"
      initialValues={user ? {
        ...user,
        role: user.role?.code,
        agence_id: user.agency?.id
      } : {
        role: 'AGENT_EXPLOITATION',
        actif: true
      }}
      onFinish={onFinish}
    >
      <Form.Item name="nom_complet" label="Nom complet" rules={[{ required: true }]}>
        <Input placeholder="Ex: Jean Dupont" />
      </Form.Item>
      <Form.Item name="username" label="Nom d'utilisateur" rules={[{ required: true }]}>
        <Input placeholder="Ex: jdupont" disabled={!!user} />
      </Form.Item>

      {!user && (
        <div style={{ marginBottom: 12, color: "#8c8c8c", fontSize: 13 }}>
          Le mot de passe temporaire est généré automatiquement à la création.
        </div>
      )}

      <Row gutter={16}>
        <Col span={12}>
          <Form.Item name="role" label="Rôle" rules={[{ required: true }]}>
            <Select>
              {Object.values(UserRole).map(r => (
                <Option key={r} value={r}>{r}</Option>
              ))}
            </Select>
          </Form.Item>
        </Col>
        <Col span={12}>
          <Form.Item name="agence_id" label="Agence" rules={[{ required: true, message: "Sélectionnez une agence" }]}>
            <Select placeholder="Sélectionner une agence" allowClear>
              {agences.map(a => (
                <Option key={a.id} value={a.id}>{a.name || (a as any).nom || a.code}</Option>
              ))}
            </Select>
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={16}>
        <Col span={12}>
          <Form.Item
            name="email"
            label="Email"
            rules={user ? [] : [{ required: true, message: "Email obligatoire" }]}
          >
            <Input type="email" />
          </Form.Item>
        </Col>
        <Col span={12}>
          <Form.Item
            name="phone"
            label="Téléphone"
            rules={user ? [] : [{ required: true, message: "Téléphone obligatoire" }]}
          >
            <Input />
          </Form.Item>
        </Col>
      </Row>

      <Form.Item name="actif" valuePropName="checked">
        <Switch checkedChildren="Actif" unCheckedChildren="Inactif" />
      </Form.Item>

      <Form.Item>
        <Space style={{ width: '100%', justifyContent: 'flex-end' }}>
          <Button onClick={onCancel}>Annuler</Button>
          <Button type="primary" htmlType="submit" loading={loading}>
            Enregistrer
          </Button>
        </Space>
      </Form.Item>

      <Modal
        title="Utilisateur créé - mot de passe temporaire"
        open={!!createdPassword}
        onCancel={() => {
          setCreatedPassword(null);
          onSuccess();
        }}
        footer={[
          <Button
            key="copy"
            icon={<CopyOutlined />}
            onClick={async () => {
              if (!createdPassword) return;
              await navigator.clipboard.writeText(createdPassword);
              message.success("Mot de passe temporaire copié");
            }}
          >
            Copier
          </Button>,
          <Button
            key="close"
            type="primary"
            onClick={() => {
              setCreatedPassword(null);
              onSuccess();
            }}
          >
            Fermer
          </Button>,
        ]}
      >
        <p>Communiquez ce mot de passe temporaire à l'utilisateur.</p>
        <Title level={4} copyable>{createdPassword}</Title>
      </Modal>
    </Form>
  );
};
