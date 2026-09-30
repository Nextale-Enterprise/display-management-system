import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { abilitiesPlugin } from '@casl/vue'
import Toast from 'vue-toastification'
import App from './App.vue'
import router from './router'
import vuetify from './plugins/vuetify'
import { ability } from './plugins/casl'
import 'vue-toastification/dist/index.css'
import '../styles/foodtale.scss'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(vuetify)
app.use(abilitiesPlugin, ability)
app.use(Toast, {
  position: 'top-right',
  timeout: 4000,
  closeOnClick: true,
  pauseOnHover: true,
})
app.mount('#app')
