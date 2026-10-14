import { Controller } from '@hotwired/stimulus';
import { renderStreamMessage } from '@hotwired/turbo';

const POLL_INTERVAL_MS = 15000;

export default class extends Controller {
    static values = {
        hubUrl: String,
        topics: Array,
        pollUrl: String,
        secure: Boolean,
    };

    connect() {
        this.wasCalled = this.isCalled();
        this.poller = null;
        this.source = null;
        this.connectStream();
    }

    disconnect() {
        this.stopPolling();
        this.source?.close();
        this.source = null;
    }

    connectStream() {
        if (!this.hasHubUrlValue || !this.topicsValue.length || !window.EventSource) {
            this.startPolling();
            return;
        }

        const url = new URL(this.hubUrlValue, window.location.href);
        this.topicsValue.forEach((topic) => url.searchParams.append('topic', topic));

        this.source = new EventSource(url, { withCredentials: this.secureValue });
        this.source.onopen = () => this.stopPolling();
        this.source.onmessage = (event) => this.render(event.data);
        this.source.onerror = () => this.startPolling();
    }

    startPolling() {
        if (this.poller || !this.hasPollUrlValue) {
            return;
        }

        this.poller = setInterval(() => this.poll(), POLL_INTERVAL_MS);
    }

    stopPolling() {
        if (this.poller) {
            clearInterval(this.poller);
            this.poller = null;
        }
    }

    async poll() {
        try {
            const response = await fetch(this.pollUrlValue, {
                headers: { Accept: 'text/vnd.turbo-stream.html' },
                credentials: 'same-origin',
            });
            if (response.ok) {
                this.render(await response.text());
            }
        } catch (error) {
            // Network blips are retried on the next interval.
        }
    }

    render(streamHtml) {
        renderStreamMessage(streamHtml);
        setTimeout(() => this.checkCalledAlert(), 0);
    }

    checkCalledAlert() {
        const called = this.isCalled();
        if (called && !this.wasCalled) {
            this.alertPatient();
        }
        this.wasCalled = called;
    }

    isCalled() {
        return this.element.querySelector('[data-called-banner]') !== null;
    }

    alertPatient() {
        navigator.vibrate?.([400, 150, 400]);

        try {
            const context = new AudioContext();
            const oscillator = context.createOscillator();
            oscillator.frequency.value = 880;
            oscillator.connect(context.destination);
            oscillator.start();
            oscillator.stop(context.currentTime + 0.4);
        } catch (error) {
            // Audio is blocked until the patient interacts with the page; vibration still applies.
        }
    }
}
