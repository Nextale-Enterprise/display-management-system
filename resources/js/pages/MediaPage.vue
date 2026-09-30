<script setup>
import { onMounted, ref } from 'vue'
import { useField, useForm } from 'vee-validate'
import { useToast } from 'vue-toastification'
import * as yup from 'yup'
import { useMediaStore } from '@/store/media'
import { confirm } from '@/plugins/confirm'
import { errorText } from '@/utils/feedback'
import AmsListPage from '@/components/AmsListPage.vue'
import AmsDataTable from '@/components/AmsDataTable.vue'

const store = useMediaStore()
const toast = useToast()
const showModal = ref(false)
const filterName = ref('')
const formError = ref('')
const file = ref(null)

const schema = yup.object({
  name: yup.string().trim().required('Name cannot be empty'),
})
const { handleSubmit, errors, resetForm } = useForm({
  validationSchema: schema,
  initialValues: { name: '' },
})
const { value: name } = useField('name')

onMounted(() => store.refreshList())

function openCreate() {
  formError.value = ''
  file.value = null
  resetForm({ values: { name: '' } })
  showModal.value = true
}

const submit = handleSubmit(async (values) => {
  formError.value = ''
  if (!file.value) {
    formError.value = 'Choose a file.'
    return
  }
  try {
    await store.create({ name: values.name, file: file.value })
    showModal.value = false
    toast.success('Media uploaded')
  } catch (error) {
    formError.value = errorText(error, 'Could not upload the file.')
  }
})

async function applyFilter() {
  store.query = filterName.value ? [{ field: 'name', value: filterName.value }] : []
  await store.refreshList()
}

async function resetFilter() {
  filterName.value = ''
  store.query = []
  await store.refreshList()
}

async function remove(item) {
  const accepted = await confirm({
    title: 'Delete media',
    text: `Delete ${item.name}?`,
    confirmText: 'Delete',
    confirmColor: 'error',
  })
  if (!accepted) return
  try {
    await store.remove(item.id)
    toast.success('Media deleted')
  } catch (error) {
    toast.error(errorText(error, 'Could not delete the media.'))
  }
}

function statusColor(status) {
  if (status === 'ready') return 'success'
  if (status === 'failed') return 'error'
  return 'warning'
}
</script>

<template>
  <AmsListPage
    v-model:filter="filterName"
    add-label="Add Media"
    filter-placeholder="Enter media name"
    :loading="store.getIsLoading"
    @submit="applyFilter"
    @reset="resetFilter"
    @add="openCreate"
  >
    <AmsDataTable
      :loading="store.getIsLoading"
      :items="store.getList"
      :headers="[
        { title: 'Action', key: 'actions', sortable: false, width: 90 },
        { title: 'Name', key: 'name' },
        { title: 'Type', key: 'type' },
        { title: 'Status', key: 'status' },
      ]"
    >
      <template #item.status="{ item }">
        <VChip size="small" variant="tonal" :color="statusColor(item.status)">
          {{ item.status }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <IconBtn color="error" aria-label="Delete" @click="remove(item)">
          <VIcon icon="tabler-trash" />
          <VTooltip activator="parent">Delete</VTooltip>
        </IconBtn>
      </template>
    </AmsDataTable>
    <template #dialog>
      <VDialog v-model="showModal">
        <VCard>
          <VCardTitle class="text-h5 pt-6 px-6">Add Media</VCardTitle>
          <VCardText>
            <VAlert v-if="formError" type="error" variant="tonal" class="mb-3" :text="formError" />
            <label>Name:</label>
            <VTextField v-model="name" placeholder="Enter media name" :error-messages="errors.name" />
            <label>File:</label>
            <VFileInput
              v-model="file"
              accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime"
              placeholder="Choose an image or video"
              prepend-icon=""
              prepend-inner-icon="tabler-upload"
            />
          </VCardText>
          <VCardActions class="px-6 pb-6">
            <VSpacer />
            <VBtn variant="outlined" @click="showModal = false">Cancel</VBtn>
            <VBtn variant="elevated" @click="submit">Create</VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
    </template>
  </AmsListPage>
</template>
