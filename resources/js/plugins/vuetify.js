import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { aliases, mdi } from 'vuetify/iconsets/mdi'

export default createVuetify({
  components,
  directives,
  icons: {
    defaultSet: 'mdi',
    aliases,
    sets: { mdi },
  },
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        colors: {
          primary: '#7367F0',
          secondary: '#A8AAAE',
          error: '#FF4C51',
          success: '#28C76F',
          warning: '#FF9F43',
          background: '#F8F7FA',
          surface: '#FFFFFF',
        },
      },
    },
  },
  defaults: {
    VBtn: { style: 'text-transform: none; letter-spacing: normal;' },
  },
})
