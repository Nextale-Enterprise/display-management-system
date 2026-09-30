<script setup>
import { onMounted, ref } from 'vue'
import { useField, useForm } from 'vee-validate'
import * as yup from 'yup'
import { useOrganizationStore } from '@/store/organization'
import AmsListPage from '@/components/AmsListPage.vue'

const store = useOrganizationStore()
const showModal = ref(false)
const editingId = ref(null)
const filterName = ref('')
const formError = ref('')

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
  editingId.value = null
  formError.value = ''
  resetForm({ values: { name: '' } })
  showModal.value = true
}

function openEdit(item) {
  editingId.value = item.id
  formError.value = ''
  resetForm({ values: { name: item.name } })
  showModal.value = true
}

const submit = handleSubmit(async (values) => {
  formError.value = ''
  try {
    if (editingId.value) await store.update({ id: editingId.value, name: values.name })
    else await store.create({ name: values.name })
    showModal.value = false
  } catch (error) {
    formError.value = error?.data?.message || 'Could not save the organization.'
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
  if (!confirm(`Delete ${item.name}?`)) return
  await store.remove(item.id)
}
</script>

<template>
  <AmsListPage
    v-model:filter="filterName"
    add-label="Add Organization"
    filter-placeholder="Enter organization name"
    @submit="applyFilter"
    @reset="resetFilter"
    @add="openCreate"
  >
    <v-data-table
      :loading="store.getIsLoading"
      :items="store.getList"
      :headers="[{ title: 'Name', key: 'name' }, { title: '', key: 'actions', sortable: false }]"
    >
      <template #item.actions="{ item }">
        <v-btn variant="outlined" color="primary" icon="mdi-pencil" size="small" class="me-2" @click="openEdit(item)" />
        <v-btn variant="outlined" color="error" icon="mdi-delete" size="small" @click="remove(item)" />
      </template>
    </v-data-table>
    <template #dialog>
      <v-dialog v-model="showModal" max-width="600" persistent>
        <v-card>
          <v-card-title>{{ editingId ? 'Edit Organization' : 'Add Organization' }}</v-card-title>
          <v-card-text>
            <v-alert v-if="formError" type="error" class="mb-3" :text="formError" />
            <v-form @submit.prevent="submit">
              <label>Name:</label>
              <v-text-field v-model="name" placeholder="Enter organization name" :error-messages="errors.name" />
            </v-form>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="outlined" @click="showModal = false">Cancel</v-btn>
            <v-btn variant="elevated" color="primary" @click="submit">{{ editingId ? 'Save' : 'Create' }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </template>
  </AmsListPage>
</template>
