import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { abilitiesPlugin } from '@casl/vue'
import App from './App.vue'
import router from './router'
import vuetify from './plugins/vuetify'
import { ability } from './plugins/casl'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(vuetify)
app.use(abilitiesPlugin, ability)
app.mount('#app')
