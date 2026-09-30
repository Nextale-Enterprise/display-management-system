<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCommonStore } from '@/store/common'
import AppTextField from '@/components/AppTextField.vue'
import loginBanner from '../../images/login-banner.jpg'
import authMask from '../../images/misc-mask-light.png'

const REMEMBER_KEY = 'signage.rememberUsername'

const router = useRouter()
const common = useCommonStore()
const username = ref('')
const password = ref('')
const rememberMe = ref(false)
const showPassword = ref(false)
const showError = ref(false)

onMounted(() => {
  const saved = localStorage.getItem(REMEMBER_KEY)
  if (!saved) return
  username.value = saved
  rememberMe.value = true
})

async function submit() {
  showError.value = false
  try {
    await common.authenticate(username.value, password.value)
    if (rememberMe.value) localStorage.setItem(REMEMBER_KEY, username.value)
    else localStorage.removeItem(REMEMBER_KEY)
    await common.fetchUser()
    router.push({ name: common.homeRoute })
  } catch {
    showError.value = true
  }
}
</script>

<template>
  <VRow no-gutters class="auth-wrapper bg-surface">
    <VCol md="8" class="d-none d-md-flex">
      <div class="bg-background w-100 me-0">
        <div class="d-flex align-center justify-center">
          <VImg :src="loginBanner" class="auth-img-full" cover />
        </div>
        <img class="auth-footer-mask" :src="authMask" alt="" height="280" width="100">
      </div>
    </VCol>
    <VCol cols="12" md="4" class="auth-card-v2 d-flex align-center justify-center">
      <VCard flat :max-width="500" class="mt-12 mt-sm-0 pa-4">
        <VCardText>
          <h4 class="text-h4 mb-1">
            Admin Management System
          </h4>
          <VAlert
            v-if="showError"
            color="#fbdddd"
            style="font-size: 0.8rem; color: #6e6b7b;"
            density="compact"
            :icon="false"
            type="error"
            text="Invalid Username or Password"
          />
        </VCardText>
        <VCardText>
          <VForm @submit.prevent="submit">
            <VRow>
              <VCol cols="12">
                <AppTextField v-model="username" label="Username" autofocus />
              </VCol>
              <VCol cols="12">
                <AppTextField
                  v-model="password"
                  label="Password"
                  :type="showPassword ? 'text' : 'password'"
                  :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="showPassword = !showPassword"
                  @keyup.enter="submit"
                />
                <div class="d-flex align-center flex-wrap justify-space-between my-6">
                  <VCheckbox v-model="rememberMe" label="Remember me" />
                  <RouterLink class="text-primary auth-forgot ms-2 mb-1" :to="{ name: 'forgot-password' }">
                    Forgot Password?
                  </RouterLink>
                </div>
                <VBtn block type="submit">Login</VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
