import 'vuetify/styles'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { VBtn } from 'vuetify/components/VBtn'
import defaults from './defaults'
import { icons } from './icons'
import { themes } from './theme'

const storedTheme = localStorage.getItem('theme')
const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches
const defaultTheme = storedTheme === 'dark' || storedTheme === 'light'
  ? storedTheme
  : storedTheme === 'system' && systemDark
    ? 'dark'
    : 'light'

export default createVuetify({
  aliases: {
    IconBtn: VBtn,
  },
  components,
  directives,
  defaults,
  icons,
  theme: {
    defaultTheme,
    themes,
  },
})
