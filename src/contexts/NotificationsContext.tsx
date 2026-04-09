import React, {
  createContext,
  useContext,
  useState,
  useCallback,
  useEffect,
  useRef,
} from "react";
import { notification } from "antd";
import { notificationsService } from "@services/notifications.service";

export type NotificationType = "info" | "success" | "warning" | "error";

export interface Notification {
  id: string;
  type: NotificationType;
  title: string;
  message: string;
  timestamp: Date;
  read: boolean;
  actionUrl?: string;
  actionLabel?: string;
  category?: "colis" | "facture" | "caisse" | "system" | "rappel";
}

interface NotificationsContextType {
  notifications: Notification[];
  unreadCount: number;
  addNotification: (notification: Omit<Notification, "id" | "timestamp" | "read">) => void;
  markAsRead: (id: string) => void;
  markAllAsRead: () => void;
  removeNotification: (id: string) => void;
  clearAll: () => void;
}

const NotificationsContext = createContext<NotificationsContextType | undefined>(
  undefined
);

const MAX_NOTIFICATIONS = 50;

export const NotificationsProvider: React.FC<{
  children: React.ReactNode;
}> = ({ children }) => {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [api, contextHolder] = notification.useNotification();
  const intervalRef = useRef<NodeJS.Timeout | null>(null);

  // Charger les notifications depuis le backend
  const fetchNotifications = useCallback(async () => {
    const token =
      sessionStorage.getItem("lbp_token") ?? localStorage.getItem("lbp_token");
    const hashPath = window.location.hash.replace(/^#/, "");
    const isOnLoginPage =
      window.location.pathname === "/login" ||
      hashPath === "/login" ||
      hashPath.startsWith("/login?");

    // Ne pas appeler l'API notifications sans session active
    if (!token || isOnLoginPage) {
      return;
    }

    try {
      const data = await notificationsService.getUnread();
      const formatted = data.map((n: any) => ({
        ...n,
        timestamp: new Date(n.created_at),
        id: String(n.id)
      }));
      setNotifications(formatted);
    } catch (error) {
      console.error("Erreur lors du chargement des notifications:", error);
    }
  }, []);

  useEffect(() => {
    fetchNotifications();
    // Polling toutes les 2 minutes pour les nouvelles notifications
    const interval = setInterval(fetchNotifications, 120000);
    return () => clearInterval(interval);
  }, [fetchNotifications]);

  // Sauvegarder les notifications dans localStorage (en backup)
  useEffect(() => {
    if (notifications.length > 0) {
      localStorage.setItem("lbp_notifications", JSON.stringify(notifications));
    }
  }, [notifications]);

  // Afficher une notification toast
  const showToast = useCallback(
    (notif: Notification) => {
      const config: any = {
        message: notif.title,
        description: notif.message,
        duration: notif.type === "error" ? 0 : 4.5,
        placement: "topRight",
        onClick: () => {
          if (notif.actionUrl) {
            window.location.href = notif.actionUrl;
          }
        },
      };

      switch (notif.type) {
        case "success":
          api.success(config);
          break;
        case "warning":
          api.warning(config);
          break;
        case "error":
          api.error(config);
          break;
        default:
          api.info(config);
      }
    },
    [api]
  );

  const addNotification = useCallback(
    (notif: Omit<Notification, "id" | "timestamp" | "read">) => {
      // Pour les notifications "locales" si besoin, mais on privilégie le backend désormais
      const newNotification: Notification = {
        ...notif,
        id: `local-${Date.now()}`,
        timestamp: new Date(),
        read: false,
      };

      setNotifications((prev) => [newNotification, ...prev].slice(0, MAX_NOTIFICATIONS));
      showToast(newNotification);
    },
    [showToast]
  );

  const markAsRead = useCallback(async (id: string) => {
    if (!id.startsWith('local-')) {
      await notificationsService.markAsRead(Number(id));
    }
    setNotifications((prev) =>
      prev.map((n) => (n.id === id ? { ...n, read: true } : n))
    );
  }, []);

  const markAllAsRead = useCallback(async () => {
    await notificationsService.markAllAsRead();
    setNotifications((prev) => prev.map((n) => ({ ...n, read: true })));
  }, []);

  const removeNotification = useCallback((id: string) => {
    setNotifications((prev) => prev.filter((n) => n.id !== id));
  }, []);

  const clearAll = useCallback(() => {
    setNotifications([]);
    localStorage.removeItem("lbp_notifications");
  }, []);

  const unreadCount = notifications.filter((n) => !n.read).length;

  return (
    <NotificationsContext.Provider
      value={{
        notifications,
        unreadCount,
        addNotification,
        markAsRead,
        markAllAsRead,
        removeNotification,
        clearAll,
      }}
    >
      {contextHolder}
      {children}
    </NotificationsContext.Provider>
  );
};

export const useNotifications = () => {
  const context = useContext(NotificationsContext);
  if (!context) {
    throw new Error(
      "useNotifications must be used within a NotificationsProvider"
    );
  }
  return context;
};
