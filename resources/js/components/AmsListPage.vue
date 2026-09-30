<script setup>
defineProps({
  addLabel: { type: String, required: true },
  filterLabel: { type: String, default: 'Name:' },
  filterPlaceholder: { type: String, default: '' },
  loading: { type: Boolean, default: false },
})

const filter = defineModel('filter', { type: String, default: '' })
const emit = defineEmits(['submit', 'reset', 'add'])
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard title="Filter" elevation="6">
        <VCardItem class="custom-search">
          <VRow>
            <VCol cols="12" sm="4" md="4">
              <label>{{ filterLabel }}</label>
              <VTextField
                v-model="filter"
                :placeholder="filterPlaceholder"
                @keyup.enter="emit('submit')"
              />
            </VCol>
          </VRow>
        </VCardItem>
        <VCardActions class="pb-5 pr-6">
          <VSpacer />
          <VBtn
            variant="elevated"
            class="button-class"
            width="96"
            :disabled="loading"
            @click="emit('submit')"
          >
            Submit
          </VBtn>
          <VBtn
            variant="outlined"
            class="button-class"
            width="96"
            :disabled="loading"
            @click="emit('reset')"
          >
            Reset
          </VBtn>
          <VProgressCircular
            v-if="loading"
            class="ms-1"
            color="primary"
            size="22"
            width="2"
            indeterminate
          />
        </VCardActions>
      </VCard>

      <VCard elevation="6">
        <VCardItem>
          <VRow>
            <VCol class="d-flex justify-end mb-1" cols="12">
              <VBtn color="primary" @click="emit('add')">{{ addLabel }}</VBtn>
            </VCol>
          </VRow>
          <slot />
        </VCardItem>
      </VCard>

      <slot name="dialog" />
    </VCol>
  </VRow>
</template>
