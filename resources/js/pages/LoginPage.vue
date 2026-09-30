<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCommonStore } from '@/store/common'

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
  <v-app>
    <v-main>
      <v-row no-gutters class="fill-height">
        <v-col md="8" class="d-none d-md-flex bg-background align-center justify-center">
          <div class="text-center px-8">
            <div class="text-h3 text-primary mb-2">Signage</div>
            <div class="text-body-1 text-medium-emphasis">Control plane</div>
          </div>
        </v-col>
        <v-col cols="12" md="4" class="d-flex align-center justify-center">
          <v-card flat max-width="500" class="pa-4 w-100">
            <v-card-text>
              <h4 class="text-h4 mb-1">Signage</h4>
              <v-alert
                v-if="showError"
                class="mt-4"
                color="#fbdddd"
                density="compact"
                :icon="false"
                text="Invalid Username or Password"
              />
            </v-card-text>
            <v-card-text>
              <v-form @submit.prevent="submit">
                <v-row>
                  <v-col cols="12">
                    <v-text-field v-model="username" label="Username" autofocus />
                  </v-col>
                  <v-col cols="12">
                    <v-text-field
                      v-model="password"
                      label="Password"
                      :type="showPassword ? 'text' : 'password'"
                      :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                      @click:append-inner="showPassword = !showPassword"
                      @keyup.enter="submit"
                    />
                    <v-btn class="mt-4" block type="submit" color="primary">Login</v-btn>
                  </v-col>
                </v-row>
              </v-form>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-main>
  </v-app>
</template>
