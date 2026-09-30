<script setup>
import { ref } from 'vue'
import { useToast } from 'vue-toastification'
import AppTextField from '@/components/AppTextField.vue'
import loginBanner from '../../images/login-banner.jpg'
import authMask from '../../images/misc-mask-light.png'

const toast = useToast()
const username = ref('')

function submit() {
  if (!username.value.trim()) {
    toast.error('Please enter your username')
    return
  }
  toast.error('Password reset is not available.')
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
    <VCol cols="12" md="4" class="d-flex align-center justify-center">
      <VCard flat :max-width="500" class="mt-12 mt-sm-0 pa-4">
        <VCardText>
          <h4 class="text-h4 mb-1">Forgot Password?</h4>
          <p class="mb-0">
            Enter your username and we'll send you instructions to reset your password
          </p>
        </VCardText>
        <VCardText>
          <VForm @submit.prevent="submit">
            <VRow>
              <VCol cols="12">
                <AppTextField v-model="username" autofocus label="Username" placeholder="Enter your username" />
              </VCol>
              <VCol cols="12">
                <VBtn block type="submit">Send Reset Link</VBtn>
              </VCol>
              <VCol cols="12">
                <RouterLink class="d-flex align-center justify-center" :to="{ name: 'login' }">
                  <VIcon icon="tabler-chevron-left" size="20" class="me-1" />
                  <span>Back to login</span>
                </RouterLink>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
