import { reactive } from 'vue';

/**
 * Module-level singleton so every component pushes into the same stack
 * rendered by <ToastHub />.
 */
const toasts = reactive([]);

let nextId = 1;

function push(type, message, { duration = 4500 } = {}) {
    if (!message) {
        return;
    }
    const id = nextId++;
    toasts.push({ id, type, message });
    if (duration > 0) {
        setTimeout(() => dismiss(id), duration);
    }
    return id;
}

function dismiss(id) {
    const index = toasts.findIndex((t) => t.id === id);
    if (index !== -1) {
        toasts.splice(index, 1);
    }
}

export function useToast() {
    return {
        toasts,
        dismiss,
        success: (message, options) => push('success', message, options),
        error: (message, options) => push('error', message, options),
        warning: (message, options) => push('warning', message, options),
    };
}
