import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['source'];

    async copy() {
        const text = (this.sourceTarget.textContent || '').trim();
        if (!text) {
            return;
        }
        await navigator.clipboard.writeText(text);
        this.element.classList.add('ring-2', 'ring-green-300');
        setTimeout(() => this.element.classList.remove('ring-2', 'ring-green-300'), 600);
    }
}
