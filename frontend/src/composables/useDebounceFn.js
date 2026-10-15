import { onBeforeUnmount } from 'vue';

/**
 * Returns a debounced wrapper around `fn`. Useful for search inputs so a
 * keystroke doesn't fire an HTTP request per character.
 *
 * @param {Function} fn
 * @param {number} delay milliseconds
 */
export function useDebounceFn(fn, delay = 400) {
    let timer = null;

    const debounced = (...args) => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
            timer = null;
            fn(...args);
        }, delay);
    };

    const cancel = () => {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
    };

    onBeforeUnmount(cancel);

    return { debounced, cancel };
}
