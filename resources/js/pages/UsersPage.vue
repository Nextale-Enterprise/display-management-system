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
  username: yup.string().trim().required('Username cannot be empty'),
  password: yup.string().nullable(),
  password_confirmation: yup.string()
    .nullable()
    .oneOf([yup.ref('password')], 'Password confirmation does not match'),
  role: yup.string().required('Role cannot be empty'),
})

const blankUser = () => ({
  name: '',
  username: '',
  password: 'asd123',
  password_confirmation: 'asd123',
  role: 'merchant',
  organization_ids: [],
})

const creating = ref(true)
const { handleSubmit, errors, resetForm } = useForm({
  validationSchema: schema,
  initialValues: blankUser(),
})
const { value: name } = useField('name')
const { value: username } = useField('username')
const { value: password } = useField('password')
const { value: passwordConfirmation } = useField('password_confirmation')
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
  resetForm({ values: blankUser() })
  showModal.value = true
}

function openEdit(item) {
  creating.value = false
  editingId.value = item.id
  formError.value = ''
  resetForm({
    values: {
      name: item.name,
      username: item.username,
      password: 'asd123',
      password_confirmation: 'asd123',
      role: item.role,
      organization_ids: (item.organizations || []).map(org => org.id),
    },
  })
  showModal.value = true
}

const submit = handleSubmit(async (values) => {
  formError.value = ''
  const payload = {
    name: values.name,
    username: values.username,
    password: values.password || 'asd123',
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
    text: `Delete ${item.username || item.name}?`,
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
        { title: 'Action', key: 'actions', sortable: false, width: 120 },
        { title: 'Name', key: 'name' },
        { title: 'Username', key: 'username' },
        { title: 'Role', key: 'role' },
      ]"
    >
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <IconBtn color="primary" aria-label="Edit" :disabled="item.role === 'superadmin'" @click="openEdit(item)">
            <VIcon icon="tabler-pencil" />
            <VTooltip activator="parent">Edit</VTooltip>
          </IconBtn>
          <IconBtn color="error" aria-label="Delete" :disabled="item.id === common.loginUserDetails.id || item.role === 'superadmin'" @click="remove(item)">
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
            <p class="text-body-2 text-medium-emphasis mb-4">default password: asd123</p>
            <VAlert v-if="formError" type="error" variant="tonal" class="mb-3" :text="formError" />
            <label>Name:</label>
            <VTextField v-model="name" placeholder="Enter name" :error-messages="errors.name" />
            <label>Username:</label>
            <VTextField v-model="username" placeholder="Enter username" :error-messages="errors.username" />
            <label>Password:</label>
            <VTextField v-model="password" type="password" :error-messages="errors.password" />
            <label>Confirm password:</label>
            <VTextField v-model="passwordConfirmation" type="password" :error-messages="errors.password_confirmation" />
            <label>Role:</label>
            <VSelect v-model="role" :items="['admin', 'merchant']" :error-messages="errors.role" />
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
