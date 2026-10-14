import { onBeforeUnmount, onMounted } from 'vue'

/**
 * Closes a popover when the user clicks outside it or presses Escape.
 * `open` is a ref(boolean); `root` is a ref to the element that wraps the
 * trigger and the panel.
 */
export function useDismiss(open, root) {
  function onPointer(event) {
    if (open.value && root.value && !root.value.contains(event.target)) open.value = false
  }
  function onKey(event) {
    if (event.key === 'Escape' && open.value) open.value = false
  }
  onMounted(() => {
    document.addEventListener('pointerdown', onPointer)
    document.addEventListener('keydown', onKey)
  })
  onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointer)
    document.removeEventListener('keydown', onKey)
  })
}
