import { vi } from 'vitest'
Object.defineProperty(window, 'matchMedia', { value: vi.fn(() => ({ matches: false, addEventListener() {}, removeEventListener() {} })) })
HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open', '') }
HTMLDialogElement.prototype.close = function () { this.removeAttribute('open') }
window.scrollTo = vi.fn()
