import { Controller } from '@hotwired/stimulus';
import { visit } from '@hotwired/turbo';

const SCAN_PATH = '/staff/check-in/scan';

export default class extends Controller {
    connect() {
        if (!window.Html5Qrcode || !this.element.id) {
            this.showMessage('Camera scanning is unavailable. Use manual search below.');
            return;
        }

        this.scanner = new window.Html5Qrcode(this.element.id);
        this.scanner
            .start({ facingMode: 'environment' }, { fps: 10, qrbox: 240 }, (text) => this.onScan(text), () => {})
            .catch(() => this.showMessage('Camera permission was denied. Use manual search below.'));
    }

    disconnect() {
        this.scanner?.stop().catch(() => {}).finally(() => this.scanner?.clear());
        this.scanner = null;
    }

    onScan(text) {
        const url = new URL(text, window.location.origin);
        if (url.origin !== window.location.origin || url.pathname !== SCAN_PATH) {
            this.showMessage('That QR code is not a QueueBreeze check-in code.');
            return;
        }

        this.scanner?.stop().catch(() => {});
        visit(url.href);
    }

    showMessage(message) {
        this.element.textContent = message;
    }
}
