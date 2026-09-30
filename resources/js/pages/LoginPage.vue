<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCommonStore } from '@/store/common'
import AppTextField from '@/components/AppTextField.vue'

const router = useRouter()
const common = useCommonStore()
const username = ref('')
const password = ref('')
const showPassword = ref(false)
const showError = ref(false)

async function submit() {
  showError.value = false
  try {
    await common.authenticate(username.value, password.value)
    await common.fetchUser()
    router.push({ name: common.homeRoute })
  } catch {
    showError.value = true
  }
}
</script>

<template>
  <VRow no-gutters class="auth-wrapper">
    <VCol md="8" class="d-none d-md-flex">
      <div class="auth-hero">
        <div class="auth-hero__mark">Foodtale</div>
        <div class="auth-hero__product">Signage</div>
      </div>
    </VCol>
    <VCol cols="12" md="4" class="auth-card-v2 d-flex align-center justify-center">
      <VCard flat max-width="500" class="mt-12 mt-sm-0 pa-4 w-100">
        <VCardText>
          <h4 class="text-h4 mb-1">Signage</h4>
          <VAlert
            v-if="showError"
            class="auth-error mt-4"
            color="#fbdddd"
            density="compact"
            :icon="false"
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
                <VBtn class="mt-2" block type="submit">Login</VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
