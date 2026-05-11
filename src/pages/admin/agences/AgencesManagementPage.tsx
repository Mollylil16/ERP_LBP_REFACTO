import React, { useState } from 'react';
import {
    Typography, Table, Button, Space, Input, Modal,
    Card, Row, Col, Form, message, Popconfirm, Tag
} from 'antd';
import {
    PlusOutlined, EditOutlined, DeleteOutlined,
    ReloadOutlined, SearchOutlined, EnvironmentOutlined
} from '@ant-design/icons';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiService } from '@services/api.service';
import { Agency } from '@types';

const { Title, Text } = Typography;

export const AgencesManagementPage: React.FC = () => {
    const queryClient = useQueryClient();
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingAgency, setEditingAgency] = useState<Agency | null>(null);

    const { data: agences, isLoading, refetch } = useQuery<Agency[]>({
        queryKey: ['agences-admin'],
        queryFn: () => apiService.get('/agences'),
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => apiService.delete(`/agences/${id}`),
        onSuccess: () => {
            message.success("Agence désactivée");
            queryClient.invalidateQueries({ queryKey: ['agences-admin'] });
        }
    });

    const columns = [
        { title: 'Code', dataIndex: 'code', key: 'code', width: 100 },
        { title: 'Nom', dataIndex: 'name', key: 'name', width: 200 },
        { title: 'Ville', dataIndex: 'ville', key: 'ville', width: 150 },
        {
            title: 'Coordonnées GPS',
            key: 'gps',
            width: 200,
            render: (_: any, record: any) => record.latitude ? (
                <Tag icon={<EnvironmentOutlined />} color="processing">
                    {record.latitude.toFixed(4)}, {record.longitude.toFixed(4)}
                </Tag>
            ) : <Tag color="default">Non géolocalisé</Tag>
        },
        {
            title: 'Actions',
            key: 'actions',
            width: 150,
            render: (_: any, record: Agency) => (
                <Space>
                    <Button
                        size="small"
                        icon={<EditOutlined />}
                        onClick={() => { setEditingAgency(record); setIsModalOpen(true); }}
                    />
                    <Popconfirm title="Désactiver cette agence ?" onConfirm={() => deleteMutation.mutate(record.id)}>
                        <Button size="small" danger icon={<DeleteOutlined />} />
                    </Popconfirm>
                </Space>
            )
        }
    ];

    return (
        <div className="page-container">
            <div className="page-header">
                <Title level={2}>Gestion des Agences</Title>
                <Space>
                    <Button icon={<ReloadOutlined />} onClick={() => refetch()} />
                    <Button type="primary" icon={<PlusOutlined />} onClick={() => { setEditingAgency(null); setIsModalOpen(true); }}>
                        Nouvelle Agence
                    </Button>
                </Space>
            </div>

            <Card bodyStyle={{ padding: 0 }}>
                <Table
                    dataSource={agences}
                    columns={columns}
                    rowKey="id"
                    loading={isLoading}
                    pagination={{ pageSize: 12 }}
                />
            </Card>

            <Modal
                title={editingAgency ? "Modifier l'agence" : "Nouvelle agence"}
                open={isModalOpen}
                onCancel={() => setIsModalOpen(false)}
                footer={null}
                destroyOnClose
                width={600}
            >
                <AgencyForm
                    initialValues={editingAgency}
                    onSuccess={() => { setIsModalOpen(false); refetch(); }}
                    onCancel={() => setIsModalOpen(false)}
                />
            </Modal>
        </div>
    );
};

const AgencyForm: React.FC<{ initialValues: any, onSuccess: () => void, onCancel: () => void }> = ({
    initialValues, onSuccess, onCancel
}) => {
    const [form] = Form.useForm();
    const [loading, setLoading] = useState(false);
    const [geoLoading, setGeoLoading] = useState(false);

    const lookupGPS = async () => {
        const ville = form.getFieldValue('ville');
        const adresse = form.getFieldValue('adresse');
        if (!ville) return message.warning("Veuillez saisir au moins la ville");

        setGeoLoading(true);
        try {
            const q = `${adresse ? adresse + ', ' : ''}${ville}`;
            const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=1`);
            const data = await res.json();

            if (data && data.length > 0) {
                form.setFieldsValue({
                    latitude: parseFloat(data[0].lat),
                    longitude: parseFloat(data[0].lon),
                    place_id: data[0].place_id?.toString()
                });
                message.success("Coordonnées GPS récupérées !");
            } else {
                message.warning("Lieu introuvable. Veuillez entrer les coordonnées manuellement.");
            }
        } catch (e) {
            message.error("Erreur SSH lors de la géolocalisation");
        } finally {
            setGeoLoading(false);
        }
    };

    const onFinish = async (values: any) => {
        setLoading(true);
        try {
            if (initialValues) {
                await apiService.patch(`/agences/${initialValues.id}`, values);
                message.success("Agence mise à jour");
            } else {
                await apiService.post('/agences', values);
                message.success("Agence créée");
            }
            onSuccess();
        } catch (error: any) {
            message.error("Erreur lors de l'enregistrement");
        } finally {
            setLoading(false);
        }
    };

    return (
        <Form form={form} layout="vertical" initialValues={initialValues} onFinish={onFinish}>
            <Row gutter={16}>
                <Col xs={24} sm={8}><Form.Item name="code" label="Code" rules={[{ required: true }]}><Input placeholder="ABC" /></Form.Item></Col>
                <Col xs={24} sm={16}><Form.Item name="nom" label="Nom de l'agence" rules={[{ required: true }]}><Input placeholder="Agence Paris Sud" /></Form.Item></Col>
            </Row>

            <Row gutter={16}>
                <Col xs={24} sm={12}><Form.Item name="pays" label="Pays" rules={[{ required: true }]}><Input /></Form.Item></Col>
                <Col xs={24} sm={12}><Form.Item name="ville" label="Ville" rules={[{ required: true }]}><Input /></Form.Item></Col>
            </Row>

            <Form.Item name="adresse" label="Adresse exacte">
                <Input.TextArea rows={2} placeholder="Saisissez l'adresse pour aider la géolocalisation" />
            </Form.Item>

            <Card size="small" title="Géolocalisation GPS" style={{ marginBottom: 20, background: '#f0f5ff' }}>
                <Space direction="vertical" style={{ width: '100%' }}>
                    <Button
                        icon={<EnvironmentOutlined />}
                        onClick={lookupGPS}
                        loading={geoLoading}
                        block
                    >
                        Récupérer les coordonnées automatiquement depuis l'adresse
                    </Button>
                    <Row gutter={16}>
                        <Col xs={24} sm={12}><Form.Item name="latitude" label="Latitude"><Input type="number" step="any" /></Form.Item></Col>
                        <Col xs={24} sm={12}><Form.Item name="longitude" label="Longitude"><Input type="number" step="any" /></Form.Item></Col>
                    </Row>
                    <Form.Item name="place_id" hidden><Input /></Form.Item>
                </Space>
            </Card>

            <Form.Item>
                <Space style={{ width: '100%', justifyContent: 'flex-end' }}>
                    <Button onClick={onCancel}>Annuler</Button>
                    <Button type="primary" htmlType="submit" loading={loading}>Enregistrer</Button>
                </Space>
            </Form.Item>
        </Form>
    );
};
