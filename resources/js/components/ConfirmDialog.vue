<script setup>
import { confirmState, settleConfirm } from '@/plugins/confirm'

function onUpdate(open) {
  if (!open) settleConfirm(false)
}
</script>

<template>
  <VDialog
    :model-value="confirmState.open"
    width="400"
    persistent
    @update:model-value="onUpdate"
  >
    <VCard>
      <VCardTitle class="text-h5 pt-6 px-6">{{ confirmState.title }}</VCardTitle>
      <VCardText class="text-body-1">{{ confirmState.text }}</VCardText>
      <VCardActions class="px-6 pb-6">
        <VSpacer />
        <VBtn variant="outlined" @click="settleConfirm(false)">Cancel</VBtn>
        <VBtn
          variant="elevated"
          :color="confirmState.confirmColor"
          @click="settleConfirm(true)"
        >
          {{ confirmState.confirmText }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
