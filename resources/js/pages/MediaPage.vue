<script setup>
import { onMounted, ref } from 'vue'
import { useField, useForm } from 'vee-validate'
import * as yup from 'yup'
import { useMediaStore } from '@/store/media'
import AmsListPage from '@/components/AmsListPage.vue'

const store = useMediaStore()
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
  } catch (error) {
    formError.value = error?.data?.message || 'Could not upload the file.'
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
</script>

<template>
  <AmsListPage
    v-model:filter="filterName"
    add-label="Add Media"
    filter-placeholder="Enter media name"
    @submit="applyFilter"
    @reset="resetFilter"
    @add="openCreate"
  >
    <v-data-table
      :loading="store.getIsLoading"
      :items="store.getList"
      :headers="[
        { title: 'Name', key: 'name' },
        { title: 'Type', key: 'type' },
        { title: 'Status', key: 'status' },
        { title: '', key: 'actions', sortable: false },
      ]"
    >
      <template #item.status="{ item }">
        <v-chip size="small" :color="item.status === 'ready' ? 'success' : item.status === 'failed' ? 'error' : 'warning'">
          {{ item.status }}
        </v-chip>
      </template>
      <template #item.actions="{ item }">
        <v-btn variant="outlined" color="error" icon="mdi-delete" size="small" @click="store.remove(item.id)" />
      </template>
    </v-data-table>
    <template #dialog>
      <v-dialog v-model="showModal" max-width="600" persistent>
        <v-card>
          <v-card-title>Add Media</v-card-title>
          <v-card-text>
            <v-alert v-if="formError" type="error" class="mb-3" :text="formError" />
            <label>Name:</label>
            <v-text-field v-model="name" placeholder="Enter media name" :error-messages="errors.name" />
            <label>File:</label>
            <v-file-input
              v-model="file"
              accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime"
              placeholder="Choose an image or video"
              prepend-icon="mdi-camera"
              variant="outlined"
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="outlined" @click="showModal = false">Cancel</v-btn>
            <v-btn variant="elevated" color="primary" @click="submit">Create</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </template>
  </AmsListPage>
</template>
