/**
 * Notification Bell System
 * Handles real-time notification updates and display
 */

class NotificationBell {
    constructor() {
        this.checkInterval = 30000; // Check every 30 seconds
        this.lastCheck = Date.now();
        this.init();
    }

    init() {
        // Load initial notifications
        this.updateNotifications();

        // Set interval to check for new notifications
        setInterval(() => {
            this.updateNotifications();
        }, this.checkInterval);

        // Mark as read when dropdown is opened
        const bellDropdown = document.getElementById('notificationBell');
        if (bellDropdown) {
            bellDropdown.addEventListener('show.bs.dropdown', () => {
                this.markAllAsRead();
            });
        }
    }

    async updateNotifications() {
        try {
            const response = await fetch(BASE_URL + '/api/get_notifications.php');
            const data = await response.json();

            if (data.success) {
                this.updateBell(data.unread_count);
                this.updateDropdown(data.notifications);
            }
        } catch (error) {
            console.error('Error fetching notifications:', error);
        }
    }

    updateBell(count) {
        const badge = document.getElementById('notification-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'inline-block';

                // Add pulse animation for new notifications
                badge.classList.add('pulse');
                setTimeout(() => badge.classList.remove('pulse'), 2000);
            } else {
                badge.style.display = 'none';
            }
        }
    }

    updateDropdown(notifications) {
        const dropdown = document.getElementById('notification-list');
        if (!dropdown) return;

        if (notifications.length === 0) {
            dropdown.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-bell-slash fa-2x mb-2"></i>
                    <p class="mb-0">No hay notificaciones</p>
                </div>
            `;
            return;
        }

        dropdown.innerHTML = notifications.map(n => this.renderNotification(n)).join('');
    }

    renderNotification(notification) {
        const isUnread = !notification.read_at;
        const iconMap = {
            'quotation_accepted': { icon: 'fa-check-circle', color: 'success' },
            'quotation_rejected': { icon: 'fa-times-circle', color: 'danger' },
            'quotation_created': { icon: 'fa-file-invoice', color: 'primary' },
            'default': { icon: 'fa-bell', color: 'info' }
        };

        const config = iconMap[notification.type] || iconMap.default;
        const timeAgo = this.getTimeAgo(notification.created_at);

        return `
            <a href="${notification.related_url || '#'}"
               class="dropdown-item notification-item ${isUnread ? 'unread' : ''}"
               data-notification-id="${notification.id}">
                <div class="d-flex">
                    <div class="notification-icon text-${config.color}">
                        <i class="fas ${config.icon}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">
                            <i class="fas fa-clock"></i> ${timeAgo}
                        </div>
                    </div>
                    ${isUnread ? '<div class="unread-dot"></div>' : ''}
                </div>
            </a>
        `;
    }

    getTimeAgo(timestamp) {
        const now = new Date();
        const past = new Date(timestamp);
        const diffMs = now - past;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Ahora mismo';
        if (diffMins < 60) return `Hace ${diffMins} min`;
        if (diffHours < 24) return `Hace ${diffHours}h`;
        if (diffDays < 7) return `Hace ${diffDays}d`;

        return past.toLocaleDateString('es-PE', { day: '2-digit', month: 'short' });
    }

    async markAllAsRead() {
        try {
            await fetch(BASE_URL + '/api/mark_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            // Update badge
            this.updateBell(0);

            // Remove unread styling
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                const dot = item.querySelector('.unread-dot');
                if (dot) dot.remove();
            });
        } catch (error) {
            console.error('Error marking notifications as read:', error);
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (typeof BASE_URL !== 'undefined') {
        window.notificationBell = new NotificationBell();
    }
});
