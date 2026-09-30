<script setup>
import { computed, onMounted, ref } from 'vue'
import { useField, useForm } from 'vee-validate'
import * as yup from 'yup'
import { useUserStore } from '@/store/user'
import { useCommonStore } from '@/store/common'
import { $api } from '@/utils/api'
import AmsListPage from '@/components/AmsListPage.vue'

const store = useUserStore()
const common = useCommonStore()
const showModal = ref(false)
const editingId = ref(null)
const filterName = ref('')
const formError = ref('')
const organizations = ref([])

const schema = yup.object({
  name: yup.string().trim().required('Name cannot be empty'),
  email: yup.string().trim().email('Email is invalid').required('Email cannot be empty'),
  password: yup.string().nullable(),
  role: yup.string().required('Role cannot be empty'),
})

const creating = ref(true)
const { handleSubmit, errors, resetForm } = useForm({
  validationSchema: schema,
  initialValues: { name: '', email: '', password: '', role: 'merchant', organization_ids: [] },
})
const { value: name } = useField('name')
const { value: email } = useField('email')
const { value: password } = useField('password')
const { value: role } = useField('role')
const { value: organizationIds } = useField('organization_ids')

const orgItems = computed(() => organizations.value)

onMounted(async () => {
  organizations.value = await $api('/api/organizations')
  await store.refreshList()
})

function openCreate() {
  creating.value = true
  editingId.value = null
  formError.value = ''
  resetForm({ values: { name: '', email: '', password: '', role: 'merchant', organization_ids: [] } })
  showModal.value = true
}

function openEdit(item) {
  creating.value = false
  editingId.value = item.id
  formError.value = ''
  resetForm({
    values: {
      name: item.name,
      email: item.email,
      password: '',
      role: item.role,
      organization_ids: (item.organizations || []).map(org => org.id),
    },
  })
  showModal.value = true
}

const submit = handleSubmit(async (values) => {
  formError.value = ''
  if (creating.value && !values.password) {
    formError.value = 'Password cannot be empty.'
    return
  }
  if (values.password && values.password.length < 8) {
    formError.value = 'At least 8 characters.'
    return
  }
  const payload = {
    name: values.name,
    email: values.email,
    role: values.role,
    organization_ids: values.role === 'merchant' ? (values.organization_ids || []) : [],
  }
  if (values.password) payload.password = values.password
  try {
    if (editingId.value) await store.update({ id: editingId.value, ...payload })
    else await store.create(payload)
    showModal.value = false
  } catch (error) {
    formError.value = error?.data?.message || 'Could not save the user.'
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
  if (!confirm(`Delete ${item.email}?`)) return
  await store.remove(item.id)
}
</script>

<template>
  <AmsListPage
    v-model:filter="filterName"
    add-label="Add User"
    filter-label="Name:"
    filter-placeholder="Enter user name"
    @submit="applyFilter"
    @reset="resetFilter"
    @add="openCreate"
  >
    <v-data-table
      :loading="store.getIsLoading"
      :items="store.getList"
      :headers="[
        { title: 'Name', key: 'name' },
        { title: 'Email', key: 'email' },
        { title: 'Role', key: 'role' },
        { title: '', key: 'actions', sortable: false },
      ]"
    >
      <template #item.actions="{ item }">
        <v-btn variant="outlined" color="primary" icon="mdi-pencil" size="small" class="me-2" @click="openEdit(item)" />
        <v-btn variant="outlined" color="error" icon="mdi-delete" size="small" :disabled="item.id === common.loginUserDetails.id" @click="remove(item)" />
      </template>
    </v-data-table>
    <template #dialog>
      <v-dialog v-model="showModal" max-width="600" persistent>
        <v-card>
          <v-card-title>{{ editingId ? 'Edit User' : 'Add User' }}</v-card-title>
          <v-card-text>
            <v-alert v-if="formError" type="error" class="mb-3" :text="formError" />
            <label>Name:</label>
            <v-text-field v-model="name" placeholder="Enter name" :error-messages="errors.name" />
            <label>Email:</label>
            <v-text-field v-model="email" placeholder="Enter email" :error-messages="errors.email" />
            <label>Password:</label>
            <v-text-field v-model="password" type="password" :error-messages="errors.password" />
            <label>Role:</label>
            <v-select v-model="role" :items="['operator', 'merchant']" :error-messages="errors.role" />
            <template v-if="role === 'merchant'">
              <label>Organizations:</label>
              <v-select v-model="organizationIds" :items="orgItems" item-title="name" item-value="id" multiple chips />
            </template>
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
