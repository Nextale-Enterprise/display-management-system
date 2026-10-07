<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { BrowserMultiFormatReader } from '@zxing/browser'
import { useCommonStore } from '@/store/common'
import { useDeviceStore } from '@/store/device'
import { parseClaimCode } from '@/utils/claimCode'
import { errorText } from '@/utils/feedback'

const props = defineProps({
  initialCode: { type: String, default: '' },
})
const emit = defineEmits(['accepted'])

const common = useCommonStore()
const devices = useDeviceStore()
const video = ref(null)
const typed = ref(parseClaimCode(props.initialCode))
const deviceId = ref(null)
const cameraError = ref('')
const formError = ref('')
const busy = ref(false)
let controls = null

const organizationId = computed(() => common.organizationSelected)
const openDevices = computed(() => devices.getList.filter(item => !item.linked))
const canConfirm = computed(() => Boolean(organizationId.value) && Boolean(deviceId.value) && typed.value.length >= 4 && !busy.value)

watch(() => props.initialCode, (value) => {
  if (value) typed.value = parseClaimCode(value)
})

watch(openDevices, (items) => {
  if (!items.some(item => item.id === deviceId.value)) deviceId.value = null
})

function onResult(result) {
  const code = parseClaimCode(result?.getText?.() || '')
  if (!code) return
  typed.value = code
}

async function startCamera() {
  cameraError.value = ''
  try {
    const reader = new BrowserMultiFormatReader()
    controls = await reader.decodeFromVideoDevice(undefined, video.value, onResult)
  } catch (error) {
    cameraError.value = 'Camera is unavailable. Type the code instead.'
  }
}

function stopCamera() {
  controls?.stop()
  controls = null
}

async function confirmClaim() {
  formError.value = ''
  busy.value = true
  try {
    const result = await devices.acceptClaim(typed.value, organizationId.value, deviceId.value)
    emit('accepted', result)
  } catch (error) {
    formError.value = errorText(error, 'Could not link this TV.')
  } finally {
    busy.value = false
  }
}

onMounted(() => {
  devices.refreshList()
  startCamera()
})
onBeforeUnmount(stopCamera)
</script>

<template>
  <div class="claim-scan">
    <p class="text-body-1 mb-3">
      Point the camera at the code on the TV, or type it. Pick a device that is not linked yet.
    </p>
    <video ref="video" class="claim-scan__video" muted playsinline />
    <p v-if="cameraError" class="text-medium-emphasis mt-2">{{ cameraError }}</p>
    <VTextField
      v-model="typed"
      class="mt-4"
      label="Code"
      placeholder="Code under the QR"
      autocapitalize="characters"
      @update:model-value="typed = parseClaimCode($event)"
    />
    <VSelect
      v-model="deviceId"
      class="mt-2"
      label="Device"
      :items="openDevices"
      item-title="name"
      item-value="id"
      :disabled="openDevices.length === 0"
      placeholder="Not linked yet"
    />
    <p v-if="!organizationId" class="text-body-2">
      Pick an organization first.
    </p>
    <p v-else-if="openDevices.length === 0" class="text-body-2">
      Add a device before scanning. Every device here is already linked.
    </p>
    <VAlert v-if="formError" type="error" variant="tonal" class="mt-3" :text="formError" />
    <VBtn
      class="mt-4"
      color="primary"
      size="large"
      block
      :disabled="!canConfirm"
      :loading="busy"
      @click="confirmClaim"
    >
      Link TV
    </VBtn>
  </div>
</template>

<style scoped>
.claim-scan__video {
  width: 100%;
  max-height: 42vh;
  background: #111;
  border-radius: 12px;
  object-fit: cover;
}
</style>
