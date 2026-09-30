import { reactive } from 'vue'

export const confirmState = reactive({
  open: false,
  title: 'Please confirm',
  text: '',
  confirmText: 'Confirm',
  confirmColor: 'primary',
  resolve: null,
})

export function confirm(options) {
  confirmState.title = options.title || 'Please confirm'
  confirmState.text = options.text || ''
  confirmState.confirmText = options.confirmText || 'Confirm'
  confirmState.confirmColor = options.confirmColor || 'primary'
  confirmState.open = true

  return new Promise((resolve) => {
    confirmState.resolve = (value) => {
      confirmState.open = false
      confirmState.resolve = null
      resolve(value)
    }
  })
}

export function settleConfirm(value) {
  if (!confirmState.resolve) {
    confirmState.open = false
    return
  }
  confirmState.resolve(value)
}
