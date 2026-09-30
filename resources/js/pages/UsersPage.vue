<script setup>
import { computed, onMounted, ref } from 'vue'
import { useField, useForm } from 'vee-validate'
import { useToast } from 'vue-toastification'
import * as yup from 'yup'
import { useUserStore } from '@/store/user'
import { useCommonStore } from '@/store/common'
import { $api } from '@/utils/api'
import { confirm } from '@/plugins/confirm'
import { errorText } from '@/utils/feedback'
import AmsListPage from '@/components/AmsListPage.vue'
import AmsDataTable from '@/components/AmsDataTable.vue'

const store = useUserStore()
const common = useCommonStore()
const toast = useToast()
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
    toast.success(editingId.value ? 'User updated' : 'User created')
  } catch (error) {
    formError.value = errorText(error, 'Could not save the user.')
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
    title: 'Delete user',
    text: `Delete ${item.email}?`,
    confirmText: 'Delete',
    confirmColor: 'error',
  })
  if (!accepted) return
  try {
    await store.remove(item.id)
    toast.success('User deleted')
  } catch (error) {
    toast.error(errorText(error, 'Could not delete the user.'))
  }
}
</script>

<template>
  <AmsListPage
    v-model:filter="filterName"
    add-label="Add User"
    filter-label="Name:"
    filter-placeholder="Enter user name"
    :loading="store.getIsLoading"
    @submit="applyFilter"
    @reset="resetFilter"
    @add="openCreate"
  >
    <AmsDataTable
      :loading="store.getIsLoading"
      :items="store.getList"
      :headers="[
        { title: 'Name', key: 'name' },
        { title: 'Email', key: 'email' },
        { title: 'Role', key: 'role' },
        { title: 'Action', key: 'actions', sortable: false, width: 120 },
      ]"
    >
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <IconBtn color="primary" aria-label="Edit" @click="openEdit(item)">
            <VIcon icon="tabler-pencil" />
            <VTooltip activator="parent">Edit</VTooltip>
          </IconBtn>
          <IconBtn color="error" aria-label="Delete" :disabled="item.id === common.loginUserDetails.id" @click="remove(item)">
            <VIcon icon="tabler-trash" />
            <VTooltip activator="parent">Delete</VTooltip>
          </IconBtn>
        </div>
      </template>
    </AmsDataTable>
    <template #dialog>
      <VDialog v-model="showModal">
        <VCard>
          <VCardTitle class="text-h5 pt-6 px-6">{{ editingId ? 'Edit User' : 'Add User' }}</VCardTitle>
          <VCardText>
            <VAlert v-if="formError" type="error" variant="tonal" class="mb-3" :text="formError" />
            <label>Name:</label>
            <VTextField v-model="name" placeholder="Enter name" :error-messages="errors.name" />
            <label>Email:</label>
            <VTextField v-model="email" placeholder="Enter email" :error-messages="errors.email" />
            <label>Password:</label>
            <VTextField v-model="password" type="password" :error-messages="errors.password" />
            <label>Role:</label>
            <VSelect v-model="role" :items="['operator', 'merchant']" :error-messages="errors.role" />
            <template v-if="role === 'merchant'">
              <label>Organizations:</label>
              <VSelect v-model="organizationIds" :items="orgItems" item-title="name" item-value="id" multiple chips />
            </template>
          </VCardText>
          <VCardActions class="px-6 pb-6">
            <VSpacer />
            <VBtn variant="outlined" @click="showModal = false">Cancel</VBtn>
            <VBtn variant="elevated" @click="submit">{{ editingId ? 'Save' : 'Create' }}</VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
    </template>
  </AmsListPage>
</template>
