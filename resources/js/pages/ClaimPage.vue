<script setup>
import { useRoute, useRouter } from 'vue-router'
import ClaimScan from '@/components/ClaimScan.vue'
import { useToast } from 'vue-toastification'

const route = useRoute()
const router = useRouter()
const toast = useToast()

function onAccepted() {
  toast.success('Device claimed')
  router.push({ name: 'device' })
}
</script>

<template>
  <VContainer class="py-6" style="max-width: 480px;">
    <h1 class="text-h5 mb-4">Scan device</h1>
    <ClaimScan :initial-code="String(route.params.code || '')" @accepted="onAccepted" />
  </VContainer>
</template>
