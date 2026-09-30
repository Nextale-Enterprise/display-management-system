<script setup>
import { computed, ref, useSlots, watch } from 'vue'

const props = defineProps({
  items: { type: Array, default: () => [] },
  headers: { type: Array, required: true },
  loading: { type: Boolean, default: false },
})

const slots = useSlots()
const page = ref(1)
const itemsPerPage = ref(20)
const pageSizes = [5, 10, 20, 50, 100]

const forwardedSlots = computed(() => {
  return Object.fromEntries(
    Object.entries(slots).filter(([name]) => name !== 'bottom' && name !== 'no-data'),
  )
})

const total = computed(() => props.items.length)
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / itemsPerPage.value) || 1))
const rangeStart = computed(() => (total.value === 0 ? 0 : (page.value - 1) * itemsPerPage.value + 1))
const rangeEnd = computed(() => Math.min(page.value * itemsPerPage.value, total.value))
const meta = computed(() => `Showing ${rangeStart.value} to ${rangeEnd.value} of ${total.value} entries`)

watch(totalPages, (value) => {
  if (page.value > value) page.value = value
})

function setPageSize(value) {
  itemsPerPage.value = value
  page.value = 1
}
</script>

<template>
  <VDataTable
    v-model:page="page"
    v-model:items-per-page="itemsPerPage"
    class="ams-table"
    :headers="headers"
    :items="items"
    :loading="loading"
    hover
  >
    <template v-for="(_, slotName) in forwardedSlots" :key="slotName" #[slotName]="slotProps">
      <slot :name="slotName" v-bind="slotProps || {}" />
    </template>
    <template #no-data>
      <div class="text-medium-emphasis py-8">No data available</div>
    </template>
    <template #bottom>
      <VDivider />
      <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 px-6 py-3">
        <p class="text-disabled mb-0">{{ meta }}</p>
        <div class="d-flex align-center flex-wrap justify-center gap-3">
          <div class="d-flex align-center gap-2">
            <span class="text-body-2">Items per page:</span>
            <VSelect
              :model-value="itemsPerPage"
              :items="pageSizes"
              density="compact"
              hide-details
              style="min-inline-size: 5rem; max-inline-size: 6rem;"
              @update:model-value="setPageSize"
            />
          </div>
          <VPagination
            v-model="page"
            :length="totalPages"
            :total-visible="5"
            density="comfortable"
          />
          <span class="text-body-2 text-disabled">
            Total: {{ totalPages }} {{ totalPages === 1 ? 'page' : 'pages' }}
          </span>
        </div>
      </div>
    </template>
  </VDataTable>
</template>
