<script setup>
import { computed, onMounted, ref } from 'vue'
import { useField, useForm } from 'vee-validate'
import { useToast } from 'vue-toastification'
import * as yup from 'yup'
import { useOrganizationStore } from '@/store/organization'
import { useCommonStore } from '@/store/common'
import { confirm } from '@/plugins/confirm'
import { errorText } from '@/utils/feedback'
import AmsListPage from '@/components/AmsListPage.vue'
import AmsDataTable from '@/components/AmsDataTable.vue'

const store = useOrganizationStore()
const common = useCommonStore()
const toast = useToast()
const showModal = ref(false)
const editingId = ref(null)
const filterName = ref('')
const formError = ref('')
const canSetSubscription = computed(() => common.loginUserDetails.role === 'superadmin')

const emptyLimit = (value, original) => (original === '' || original === null || original === undefined ? null : value)
const schema = yup.object({
  name: yup.string().trim().required('Name cannot be empty'),
  device_limit: yup.number().nullable().transform(emptyLimit).min(0).integer(),
  branch_limit: yup.number().nullable().transform(emptyLimit).min(0).integer(),
  subscription_starts_at: yup.string().nullable(),
  subscription_ends_at: yup.string().nullable(),
})
const { handleSubmit, errors, resetForm } = useForm({
  validationSchema: schema,
  initialValues: {
    name: '',
    device_limit: null,
    branch_limit: null,
    subscription_starts_at: null,
    subscription_ends_at: null,
  },
})
const { value: name } = useField('name')
const { value: deviceLimit } = useField('device_limit')
const { value: branchLimit } = useField('branch_limit')
const { value: startsAt } = useField('subscription_starts_at')
const { value: endsAt } = useField('subscription_ends_at')

onMounted(() => store.refreshList())

function blankSubscription() {
  return {
    name: '',
    device_limit: null,
    branch_limit: null,
    subscription_starts_at: null,
    subscription_ends_at: null,
  }
}

function openCreate() {
  editingId.value = null
  formError.value = ''
  resetForm({ values: blankSubscription() })
  showModal.value = true
}

function openEdit(item) {
  editingId.value = item.id
  formError.value = ''
  resetForm({
    values: {
      name: item.name,
      device_limit: item.device_limit,
      branch_limit: item.branch_limit,
      subscription_starts_at: item.subscription_starts_at,
      subscription_ends_at: item.subscription_ends_at,
    },
  })
  showModal.value = true
}

const submit = handleSubmit(async (values) => {
  formError.value = ''
  const payload = { name: values.name }
  if (canSetSubscription.value) {
    payload.device_limit = values.device_limit
    payload.branch_limit = values.branch_limit
    payload.subscription_starts_at = values.subscription_starts_at || null
    payload.subscription_ends_at = values.subscription_ends_at || null
  }
  try {
    if (editingId.value) await store.update({ id: editingId.value, ...payload })
    else await store.create(payload)
    showModal.value = false
    toast.success(editingId.value ? 'Organization updated' : 'Organization created')
  } catch (error) {
    formError.value = errorText(error, 'Could not save the organization.')
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

function quota(count, limit) {
  return limit == null ? String(count) : `${count} / ${limit}`
}

function period(item) {
  if (!item.subscription_starts_at && !item.subscription_ends_at) return '—'
  return `${item.subscription_starts_at || '…'} – ${item.subscription_ends_at || '…'}`
}

async function remove(item) {
  const accepted = await confirm({
    title: 'Delete organization',
    text: `Delete ${item.name}?`,
    confirmText: 'Delete',
    confirmColor: 'error',
  })
  if (!accepted) return
  try {
    await store.remove(item.id)
    toast.success('Organization deleted')
  } catch (error) {
    toast.error(errorText(error, 'Could not delete the organization.'))
  }
}
</script>

<template>
  <AmsListPage
    v-model:filter="filterName"
    add-label="Add Organization"
    filter-placeholder="Enter organization name"
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
        { title: 'Devices', key: 'devices' },
        { title: 'Branches', key: 'branches' },
        { title: 'Period', key: 'period' },
      ]"
    >
      <template #item.devices="{ item }">{{ quota(item.devices_count, item.device_limit) }}</template>
      <template #item.branches="{ item }">{{ quota(item.branches_count, item.branch_limit) }}</template>
      <template #item.period="{ item }">{{ period(item) }}</template>
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <IconBtn color="primary" aria-label="Edit" @click="openEdit(item)">
            <VIcon icon="tabler-pencil" />
            <VTooltip activator="parent">Edit</VTooltip>
          </IconBtn>
          <IconBtn color="error" aria-label="Delete" @click="remove(item)">
            <VIcon icon="tabler-trash" />
            <VTooltip activator="parent">Delete</VTooltip>
          </IconBtn>
        </div>
      </template>
    </AmsDataTable>
    <template #dialog>
      <VDialog v-model="showModal">
        <VCard>
          <VCardTitle class="text-h5 pt-6 px-6">{{ editingId ? 'Edit Organization' : 'Add Organization' }}</VCardTitle>
          <VCardText>
            <VAlert v-if="formError" type="error" variant="tonal" class="mb-3" :text="formError" />
            <VForm @submit.prevent="submit">
              <label>Name:</label>
              <VTextField v-model="name" placeholder="Enter organization name" :error-messages="errors.name" />
              <template v-if="canSetSubscription">
                <label>Device limit:</label>
                <VTextField v-model="deviceLimit" type="number" min="0" placeholder="No limit" :error-messages="errors.device_limit" />
                <label>Branch limit:</label>
                <VTextField v-model="branchLimit" type="number" min="0" placeholder="No limit" :error-messages="errors.branch_limit" />
                <label>Period start:</label>
                <VTextField v-model="startsAt" type="date" :error-messages="errors.subscription_starts_at" />
                <label>Period end:</label>
                <VTextField v-model="endsAt" type="date" :error-messages="errors.subscription_ends_at" />
              </template>
            </VForm>
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
