<script setup>
defineOptions({ inheritAttrs: false })

defineProps({
  label: { type: String, default: '' },
})

const model = defineModel({ type: String, default: '' })
</script>

<template>
  <div class="app-text-field flex-grow-1">
    <VLabel
      v-if="label"
      class="mb-1 text-body-2"
      :text="label"
    />
    <VTextField v-model="model" v-bind="$attrs" />
  </div>
</template>
