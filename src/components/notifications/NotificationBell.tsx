import React, { useState } from "react";
import { Badge, Popover, Button, List, Typography, Space, Empty, Divider, Modal, Tag, Card } from "antd";
import {
  BellOutlined,
  CheckOutlined,
  DeleteOutlined,
  CloseOutlined,
  InfoCircleOutlined,
  WarningOutlined,
  CheckCircleOutlined,
  ExclamationCircleOutlined,
} from "@ant-design/icons";
import { useNotifications } from "@contexts/NotificationsContext";
import { formatDate } from "@utils/format";
import "./NotificationBell.css";

const { Text, Title, Paragraph } = Typography;

export const NotificationBell: React.FC = () => {
  const {
    notifications,
    unreadCount,
    markAsRead,
    markAllAsRead,
    removeNotification,
    clearAll,
  } = useNotifications();
  const [open, setOpen] = useState(false);
  const [detailVisible, setDetailVisible] = useState(false);
  const [selectedNotif, setSelectedNotif] = useState<any>(null);

  const handleMarkAsRead = (id: string) => {
    markAsRead(id);
  };

  const handleRemove = (id: string, e: React.MouseEvent) => {
    e.stopPropagation();
    removeNotification(id);
  };

  const handleClickNotification = (notification: any) => {
    if (!notification.read) {
      handleMarkAsRead(notification.id);
    }

    if (notification.problem || notification.solution || notification.audit_data) {
      setSelectedNotif(notification);
      setDetailVisible(true);
      setOpen(false);
    } else if (notification.actionUrl) {
      window.location.href = notification.actionUrl;
      setOpen(false);
    }
  };

  const getNotificationIcon = (type: string) => {
    switch (type) {
      case "success":
        return <CheckCircleOutlined style={{ color: "#52c41a" }} />;
      case "warning":
        return <WarningOutlined style={{ color: "#faad14" }} />;
      case "error":
        return <ExclamationCircleOutlined style={{ color: "#ff4d4f" }} />;
      default:
        return <InfoCircleOutlined style={{ color: "#1890ff" }} />;
    }
  };

  const content = (
    <div className="notification-popover">
      <div className="notification-header">
        <Text strong>Notifications</Text>
        <Space>
          {unreadCount > 0 && (
            <Button
              type="link"
              size="small"
              icon={<CheckOutlined />}
              onClick={(e: React.MouseEvent) => {
                e.stopPropagation();
                markAllAsRead();
              }}
            >
              Tout marquer lu
            </Button>
          )}
          {notifications.length > 0 && (
            <Button
              type="link"
              size="small"
              danger
              icon={<DeleteOutlined />}
              onClick={(e: React.MouseEvent) => {
                e.stopPropagation();
                clearAll();
              }}
            >
              Tout effacer
            </Button>
          )}
        </Space>
      </div>
      <Divider style={{ margin: "8px 0" }} />
      <div className="notification-list">
        {notifications.length === 0 ? (
          <Empty
            image={Empty.PRESENTED_IMAGE_SIMPLE}
            description="Aucune notification"
            style={{ padding: "20px 0" }}
          />
        ) : (
          <List
            dataSource={notifications}
            renderItem={(item: any) => (
              <List.Item
                className={`notification-item ${!item.read ? "unread" : ""}`}
                onClick={() => handleClickNotification(item)}
                style={{ cursor: "pointer" }}
              >
                <div className="notification-content">
                  <div className="notification-icon">
                    {getNotificationIcon(item.type)}
                  </div>
                  <div className="notification-body">
                    <div className="notification-title">
                      <Text strong={!item.read}>{item.title}</Text>
                      <Button
                        type="text"
                        size="small"
                        icon={<CloseOutlined />}
                        onClick={(e: React.MouseEvent) => handleRemove(item.id, e)}
                        className="notification-close"
                      />
                    </div>
                    <Text type="secondary" className="notification-message" style={{ display: 'block', maxWidth: 250, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                      {item.message}
                    </Text>
                    <div className="notification-footer">
                      <Text type="secondary" style={{ fontSize: 11 }}>
                        {formatDate(item.timestamp.toISOString())}
                      </Text>
                      {item.category && (
                        <Tag size="small" style={{ fontSize: 10, marginLeft: 8 }}>
                          {item.category}
                        </Tag>
                      )}
                    </div>
                  </div>
                </div>
              </List.Item>
            )}
          />
        )}
      </div>
    </div>
  );

  return (
    <>
      <Popover
        content={content}
        title={null}
        trigger="click"
        open={open}
        onOpenChange={setOpen}
        placement="bottomRight"
        overlayClassName="notification-popover-overlay"
      >
        <Badge count={unreadCount} offset={[-5, 5]}>
          <Button type="text" icon={<BellOutlined />} style={{ fontSize: 18 }} aria-label="Notifications" />
        </Badge>
      </Popover>

      <Modal
        title={selectedNotif?.title}
        open={detailVisible}
        onCancel={() => setDetailVisible(false)}
        footer={[
          <Button key="close" onClick={() => setDetailVisible(false)}>Fermer</Button>,
          selectedNotif?.actionUrl && (
            <Button key="action" type="primary" onClick={() => window.location.href = selectedNotif.actionUrl}>
              Voir les détails
            </Button>
          )
        ]}
        width={600}
      >
        {selectedNotif && (
          <Space direction="vertical" style={{ width: '100%' }} size="large">
            <Card title="🚨 Problématique" headStyle={{ background: '#fff1f0' }}>
              <Paragraph>{selectedNotif.problem || selectedNotif.message}</Paragraph>
            </Card>

            <Card title="💡 Solution Proposée" headStyle={{ background: '#f6ffed' }}>
              <Paragraph style={{ whiteSpace: 'pre-line' }}>{selectedNotif.solution || "Aucune solution spécifique définie."}</Paragraph>
            </Card>

            {selectedNotif.audit_data && (
              <Card title="📊 Audit & Évaluation" headStyle={{ background: '#e6f7ff' }}>
                <Paragraph>
                  <strong>Contexte :</strong> {selectedNotif.category}<br />
                  <strong>Détails techniques :</strong>
                  <pre style={{ fontSize: 11, marginTop: 10, background: '#f5f5f5', padding: 8 }}>
                    {JSON.stringify(selectedNotif.audit_data, null, 2)}
                  </pre>
                </Paragraph>
                <Text type="secondary" italic>
                  Cet audit permet de vérifier si l'utilisateur (caissier) gère correctement les seuils et les mouvements de fonds.
                </Text>
              </Card>
            )}
          </Space>
        )}
      </Modal>
    </>
  );
};
