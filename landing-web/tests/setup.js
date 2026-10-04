import { vi } from 'vitest'
Object.defineProperty(window, 'matchMedia', { writable: true, value: vi.fn(() => ({ matches: true, addEventListener: vi.fn(), removeEventListener: vi.fn() })) })
HTMLElement.prototype.scrollIntoView = vi.fn()
HTMLElement.prototype.scrollBy = vi.fn()
HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open', '') }
HTMLDialogElement.prototype.close = function () { this.removeAttribute('open') }
